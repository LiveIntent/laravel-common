<?php

namespace LiveIntent\LaravelCommon\Http\Resources;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use JsonException;
use Illuminate\Http\Request;
use LiveIntent\LaravelCommon\Http\AbstractResource;
use LiveIntent\LaravelCommon\Http\AllowedScope;
use LiveIntent\LaravelCommon\Http\AllowedFilter;
use RuntimeException;
use Orion\Drivers\Standard\QueryBuilder as OrionFilterQueryBuilder;
use LiveIntent\LaravelCommon\Http\Exceptions\InvalidResourceScopeException;
use LiveIntent\LaravelCommon\Http\Exceptions\InvalidResourceFilterException;
use LiveIntent\LaravelCommon\Http\Exceptions\OperatorNotAllowedException;

class SearchRequestQueryBuilder
{
    /**
     * @var string $resourceModelClass
     */
    private $resourceModelClass;

    /**
     * @var \Orion\Contracts\ParamsValidator $paramsValidator
     */
    private $paramsValidator;

    /**
     * @var \Orion\Contracts\RelationsResolver $relationsResolver
     */
    private $relationsResolver;

    /**
     * @var bool $intermediateMode
     */
    private $intermediateMode;

    private $resource;

    /**
     * @inheritDoc
     */
    public function __construct(
        AbstractResource $resource,
        // \Orion\Contracts\ParamsValidator $paramsValidator,
        RelationsResolver $relationsResolver,
        bool $intermediateMode = false,
    ) {
        $this->resourceModelClass = $resource->getModel();
        // $this->paramsValidator = $paramsValidator;
        $this->relationsResolver = $relationsResolver;
        $this->intermediateMode = $intermediateMode;
        $this->resource = $resource;
    }

    /**
     * Get Eloquent query builder for the model and apply filters, searching and sorting.
     *
     * @param Builder|Relation $query
     * @param Request $request
     * @return Builder|Relation
     * @throws JsonException
     */
    public function buildQuery($query, Request $request)
    {
        $actionMethod = $request->route()->getActionMethod();

        if (!$this->intermediateMode && in_array($actionMethod, ['index', 'search', 'show'])) {
            if ($actionMethod === 'search') {
                $this->applyScopesToQuery($query, $request);
                $this->applyFiltersToQuery($query, $request);
                $this->applySearchingToQuery($query, $request);
                $this->applySortingToQuery($query, $request);
            }
            $this->applySoftDeletesToQuery($query, $request);
        }

        return $query;
    }

    /**
     * Apply scopes to the given query builder based on the query parameters.
     *
     * @param Builder|Relation $query
     * @param Request $request
     */
    public function applyScopesToQuery($query, Request $request): void
    {
        $allowedScopes = collect($this->resource->allowedScopes())
            ->each(function ($allowedScope) {
                if (! $allowedScope instanceof AllowedScope) {
                    throw new InvalidResourceScopeException($allowedScope);
                }
            })
            ->keyBy
            ->getName();

        $scopeDescriptors = collect($request->get('scopes', []))
            ->map(fn ($scope) => $allowedScopes->get($scope['name'])?->withArgs($scope['parameters'] ?? []))
            ->filter();

        foreach ($scopeDescriptors as $scopeDescriptor) {
            $query->{$scopeDescriptor->getInternalName()}(...$scopeDescriptor->getArgs());
        }
    }

    /**
     * Apply filters to the given query builder based on the query parameters.
     *
     * @param Builder|Relation $query
     * @param Request $request
     * @param array $filterDescriptors
     * @throws JsonException
     */
    public function applyFiltersToQuery($query, Request $request, array $filterDescriptors = []): void
    {
        $allowedFilters = collect($this->resource->allowedFilters())
            ->each(function ($allowedFilter) {
                if (! $allowedFilter instanceof AllowedFilter) {
                    throw new InvalidResourceFilterException($allowedFilter);
                }
            })
            ->keyBy
            ->getName();

        $filterDescriptors = collect($filterDescriptors ?: $request->get('filters', []))
            ->map(function ($filter) use ($allowedFilters) {
                if (!$allowedFilter = $allowedFilters->get($filter['field'] ?? '')) {
                    return null;
                }

                $filter['operator'] ??= '=';
                if (!in_array($filter['operator'], $allowedFilter->getAllowedOperators())) {
                    throw OperatorNotAllowedException::make($filter['field'], $filter['operator']);
                }

                $internalName = $allowedFilter?->getInternalName();
                $filter['field'] = $internalName;

                return $filter;
            })
            ->filter();

        foreach ($filterDescriptors as $filterDescriptor) {
            $or = Arr::get($filterDescriptor, 'type', 'and') === 'or';

            if (is_array($childrenDescriptors = Arr::get($filterDescriptor, 'nested'))) {
                $query->{$or ? 'orWhere' : 'where'}(function ($query) use ($request, $childrenDescriptors) { $this->applyFiltersToQuery($query, $request, $childrenDescriptors); });
            } elseif (strpos($filterDescriptor['field'], '.') !== false) {
                $relation = $this->relationsResolver->relationFromParamConstraint($filterDescriptor['field']);
                $relationField = $this->relationsResolver->relationFieldFromParamConstraint($filterDescriptor['field']);

                if ($relation === 'pivot') {
                    $this->buildPivotFilterQueryWhereClause($relationField, $filterDescriptor, $query, $or);
                } else {
                    $query->{$or ? 'orWhereHas' : 'whereHas'}(
                        $relation,
                        function ($relationQuery) use ($relationField, $filterDescriptor) {
                            $this->buildFilterQueryWhereClause($relationField, $filterDescriptor, $relationQuery);
                        }
                    );
                }
            } else {
                $this->buildFilterQueryWhereClause(
                    $this->getQualifiedFieldName($filterDescriptor['field']),
                    $filterDescriptor,
                    $query,
                    $or
                );
            }
        }
    }

    /**
     * Builds filter's query where clause based on the given filterable.
     *
     * @param string $field
     * @param array $filterDescriptor
     * @param Builder|Relation $query
     * @param bool $or
     * @return Builder|Relation
     * @throws JsonException
     */
    protected function buildFilterQueryWhereClause(string $field, array $filterDescriptor, $query, bool $or = false)
    {
        if (is_array($filterDescriptor['value']) && in_array(null, $filterDescriptor['value'], true)) {
            $query = $query->{$or ? 'orWhereNull' : 'whereNull'}($field);

            $filterDescriptor['value'] = collect($filterDescriptor['value'])->filter()->values()->toArray();

            if (!count($filterDescriptor['value'])) {
                return $query;
            }
        }

        return $this->buildFilterNestedQueryWhereClause($field, $filterDescriptor, $query, $or);
    }

    /**
     * @param string $field
     * @param array $filterDescriptor
     * @param Builder|Relation $query
     * @param bool $or
     * @return Builder|Relation
     * @throws JsonException
     */
    protected function buildFilterNestedQueryWhereClause(
        string $field,
        array $filterDescriptor,
        $query,
        bool $or = false
    ) {
        $treatAsDateField = $filterDescriptor['value'] !== null &&
            in_array($filterDescriptor['field'], (new $this->resourceModelClass)->getDates(), true);

        if ($treatAsDateField && Carbon::parse($filterDescriptor['value'])->toTimeString() === '00:00:00') {
            $constraint = 'whereDate';
        } elseif (in_array(Arr::get($filterDescriptor, 'operator'), ['all in', 'any in'])) {
            $constraint = 'whereJsonContains';
        } else {
            $constraint = 'where';
        }

        if ($constraint !== 'whereJsonContains' && (!is_array(
                    $filterDescriptor['value']
                ) || $constraint === 'whereDate')) {
            $query->{$or ? 'or'.ucfirst($constraint) : $constraint}(
                $field,
                $filterDescriptor['operator'] ?? '=',
                $filterDescriptor['value']
            );
        } elseif ($constraint === 'whereJsonContains') {
            if (!is_array($filterDescriptor['value'])) {
                $query->{$or ? 'orWhereJsonContains' : 'whereJsonContains'}(
                    $field,
                    $filterDescriptor['value']
                );
            } else {
                $query->{$or ? 'orWhere' : 'where'}(function ($nestedQuery) use ($filterDescriptor, $field) {
                    foreach ($filterDescriptor['value'] as $value) {
                        $nestedQuery->{$filterDescriptor['operator'] === 'any in' ? 'orWhereJsonContains' : 'whereJsonContains'}(
                            $field,
                            $value
                        );
                    }
                });
            }
        } else {
            $query->{$or ? 'orWhereIn' : 'whereIn'}(
                $field,
                $filterDescriptor['value'],
                'and',
                $filterDescriptor['operator'] === 'not in'
            );
        }

        return $query;
    }

    /**
     * Builds filter's pivot query where clause based on the given filterable.
     *
     * @param string $field
     * @param array $filterDescriptor
     * @param Builder|Relation $query
     * @param bool $or
     * @return Builder|Relation
     */
    protected function buildPivotFilterQueryWhereClause(
        string $field,
        array $filterDescriptor,
        $query,
        bool $or = false
    ) {
        if (is_array($filterDescriptor['value']) && in_array(null, $filterDescriptor['value'], true)) {
            if ((float) app()->version() <= 7.0) {
                throw new RuntimeException(
                    "Filtering by nullable pivot fields is only supported for Laravel version > 8.0"
                );
            }

            $query = $query->{$or ? 'orWherePivotNull' : 'wherePivotNull'}($field);

            $filterDescriptor['value'] = collect($filterDescriptor['value'])->filter()->values()->toArray();

            if (!count($filterDescriptor['value'])) {
                return $query;
            }
        }

        return $this->buildPivotFilterNestedQueryWhereClause($field, $filterDescriptor, $query);
    }

    /**
     * @param string $field
     * @param array $filterDescriptor
     * @param Builder|BelongsToMany $query
     * @param bool $or
     * @return Builder
     */
    protected function buildPivotFilterNestedQueryWhereClause(
        string $field,
        array $filterDescriptor,
        $query,
        bool $or = false
    ) {
        $pivotClass = $query->getPivotClass();
        $pivot = new $pivotClass;

        $treatAsDateField = $filterDescriptor['value'] !== null && in_array($field, $pivot->getDates(), true);

        if ($treatAsDateField && Carbon::parse($filterDescriptor['value'])->toTimeString() === '00:00:00') {
            $query->addNestedWhereQuery(
                $query->newPivotStatement()->whereDate(
                    $query->getTable().".{$field}",
                    $filterDescriptor['operator'] ?? '=',
                    $filterDescriptor['value']
                )
            );
        } elseif (!is_array($filterDescriptor['value'])) {
            $query->{$or ? 'orWherePivot' : 'wherePivot'}(
                $field,
                $filterDescriptor['operator'] ?? '=',
                $filterDescriptor['value']
            );
        } else {
            $query->{$or ? 'orWherePivotIn' : 'wherePivotIn'}(
                $field,
                $filterDescriptor['value'],
                'and',
                $filterDescriptor['operator'] === 'not in'
            );
        }

        return $query;
    }

    /**
     * Builds a complete field name with table.
     *
     * @param string $field
     * @return string
     */
    public function getQualifiedFieldName(string $field): string
    {
        $table = (new $this->resourceModelClass)->getTable();
        $found = collect($this->resource->allowedFilters())->where(function ($f) use ($field) {
            return $f->getName() === $field;
        })->first();

        $field = $found ? $found->getInternalName() : $field;

        return "{$table}.{$field}";
    }

    /**
     * Apply search query to the given query builder based on the "q" query parameter.
     *
     * @param Builder|Relation $query
     * @param Request $request
     */
    public function applySearchingToQuery($query, Request $request): void
    {
        if (!$requestedSearchDescriptor = $request->get('search')) {
            return;
        }

        // $this->paramsValidator->validateSearch($request);

        $searchables = $this->resource->searchableBy();

        $query->where(
            function ($whereQuery) use ($searchables, $requestedSearchDescriptor) {
                $requestedSearchString = $requestedSearchDescriptor['value'];

                $caseSensitive = (bool) Arr::get(
                    $requestedSearchDescriptor,
                    'case_sensitive',
                    config('orion.search.case_sensitive')
                );

                /**
                 * @var Builder $whereQuery
                 */
                foreach ($searchables as $searchable) {
                    if (strpos($searchable, '.') !== false) {
                        $relation = $this->relationsResolver->relationFromParamConstraint($searchable);
                        $relationField = $this->relationsResolver->relationFieldFromParamConstraint($searchable);

                        $whereQuery->orWhereHas(
                            $relation,
                            function ($relationQuery) use ($relationField, $requestedSearchString, $caseSensitive) {
                                /**
                                 * @var Builder $relationQuery
                                 */
                                if (!$caseSensitive) {
                                    return $relationQuery->whereRaw(
                                        "lower({$relationField}) like lower(?)",
                                        ['%'.$requestedSearchString.'%']
                                    );
                                }

                                return $relationQuery->where(
                                    $relationField,
                                    'like',
                                    '%'.$requestedSearchString.'%'
                                );
                            }
                        );
                    } else {
                        $qualifiedFieldName = $this->getQualifiedFieldName($searchable);

                        if (!$caseSensitive) {
                            $whereQuery->orWhereRaw(
                                "lower({$qualifiedFieldName}) like lower(?)",
                                ['%'.$requestedSearchString.'%']
                            );
                        } else {
                            $whereQuery->orWhere(
                                $qualifiedFieldName,
                                'like',
                                '%'.$requestedSearchString.'%'
                            );
                        }
                    }
                }
            }
        );
    }

    /**
     * Apply sorting to the given query builder based on the "sort" query parameter.
     *
     * @param Builder $query
     * @param Request $request
     */
    public function applySortingToQuery($query, Request $request): void
    {
        // $this->paramsValidator->validateSort($request);
        $sortableDescriptors = $request->get('sort', []);

        foreach ($sortableDescriptors as $sortable) {
            $found = collect($this->resource->allowedSorts())->where(function ($sort) use ($sortable) {
                if (!is_string($sort)) {
                    return $sort->getName() === $sortable['field'];
                }
            })->first();

            $sortableField = $found ? $found->getInternalName() : $sortable['field'];

            $direction = Arr::get($sortable, 'direction', 'asc');

            if (strpos($sortableField, '.') !== false) {
                $relation = $this->relationsResolver->relationFromParamConstraint($sortableField);
                $relationField = $this->relationsResolver->relationFieldFromParamConstraint($sortableField);

                if ($relation === 'pivot') {
                    $query->orderByPivot($relationField, $direction);
                    continue;
                }

                /**
                 * @var Relation $relationInstance
                 */
                $relationInstance = (new $this->resourceModelClass)->{$relation}();

                if ($relationInstance instanceof MorphTo) {
                    continue;
                }

                $relationTable = $this->relationsResolver->relationTableFromRelationInstance($relationInstance);
                $relationForeignKey = $this->relationsResolver->relationForeignKeyFromRelationInstance(
                    $relationInstance
                );
                $relationLocalKey = $this->relationsResolver->relationLocalKeyFromRelationInstance($relationInstance);

                $requiresJoin = collect($query->toBase()->joins ?? [])
                    ->where('table', $relationTable)->isEmpty();

                if ($requiresJoin) {
                    $query->leftJoin($relationTable, $relationForeignKey, '=', $relationLocalKey);
                }

                $query->orderBy("$relationTable.$relationField", $direction)
                    ->select($this->getQualifiedFieldName('*'));
            } else {
                $query->orderBy($this->getQualifiedFieldName($sortableField), $direction);
            }
        }
    }

    /**
     * Apply "soft deletes" query to the given query builder based on either "with_trashed" or "only_trashed" query parameters.
     *
     * @param Builder|Relation|SoftDeletes $query
     * @param Request $request
     * @return bool
     */
    public function applySoftDeletesToQuery($query, Request $request): bool
    {
        if (!$query->getMacro('withTrashed')) {
            return false;
        }

        if (filter_var($request->query('with_trashed', false), FILTER_VALIDATE_BOOLEAN)) {
            $query->withTrashed();
        } elseif (filter_var($request->query('only_trashed', false), FILTER_VALIDATE_BOOLEAN)) {
            $query->onlyTrashed();
        }

        return true;
    }
}
