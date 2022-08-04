<?php

namespace LiveIntent\LaravelCommon\Http\Resources;

use Carbon\Carbon;
use JsonException;
use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use LiveIntent\LaravelCommon\Http\AllowedSort;
use LiveIntent\LaravelCommon\Http\AllowedScope;
use LiveIntent\LaravelCommon\Http\AllowedFilter;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LiveIntent\LaravelCommon\Http\AbstractResource;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LiveIntent\LaravelCommon\Http\Exceptions\OperatorNotAllowedException;
use LiveIntent\LaravelCommon\Http\Exceptions\InvalidResourceScopeException;
use LiveIntent\LaravelCommon\Http\Exceptions\InvalidResourceFilterException;

/**
 * 99% of this code is taken directly from https://github.com/tailflow/laravel-orion/blob/main/src/Drivers/Standard/QueryBuilder.php
 * with just a few modifications to rely less on the setup of orion and more on our
 * eloquent resource object.
 */
class SearchRequestQueryBuilder
{
    /**
     * The resource to search for.
     */
    private AbstractResource $resource;

    /**
     * The model class of the resource.
     */
    private string $resourceModelClass;

    /**
     * Relation resolver helper.
     */
    private RelationsResolver $relationsResolver;

    /**
     * @inheritDoc
     */
    public function __construct(AbstractResource $resource, RelationsResolver $relationsResolver)
    {
        $this->resource = $resource;
        $this->relationsResolver = $relationsResolver;
        $this->resourceModelClass = $resource->getModel();
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
        $this->applyScopesToQuery($query, $request);
        $this->applyFiltersToQuery($query, $request);
        $this->applySearchingToQuery($query, $request);
        $this->applySortingToQuery($query, $request);

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
                if (is_array(Arr::get($filter, 'nested'))) {
                    return $filter;
                }

                if (! $allowedFilter = $allowedFilters->get($filter['field'] ?? '')) {
                    return null;
                }

                $filter['operator'] ??= '=';
                if (! in_array($filter['operator'], $allowedFilter->getAllowedOperators())) {
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
                $query->{$or ? 'orWhere' : 'where'}(function ($query) use ($request, $childrenDescriptors) {
                    $this->applyFiltersToQuery($query, $request, $childrenDescriptors);
                });
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

            if (! count($filterDescriptor['value'])) {
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
            in_array($filterDescriptor['field'], (new $this->resourceModelClass())->getDates(), true);

        if ($treatAsDateField && Carbon::parse($filterDescriptor['value'])->toTimeString() === '00:00:00') {
            $constraint = 'whereDate';
        } elseif (in_array(Arr::get($filterDescriptor, 'operator'), ['all in', 'any in'])) {
            $constraint = 'whereJsonContains';
        } else {
            $constraint = 'where';
        }

        if ($constraint !== 'whereJsonContains' && (! is_array(
            $filterDescriptor['value']
        ) || $constraint === 'whereDate')) {
            $query->{$or ? 'or'.ucfirst($constraint) : $constraint}(
                $field,
                $filterDescriptor['operator'] ?? '=',
                $filterDescriptor['value']
            );
        } elseif ($constraint === 'whereJsonContains') {
            if (! is_array($filterDescriptor['value'])) {
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

            if (! count($filterDescriptor['value'])) {
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
        $pivot = new $pivotClass();

        $treatAsDateField = $filterDescriptor['value'] !== null && in_array($field, $pivot->getDates(), true);

        if ($treatAsDateField && Carbon::parse($filterDescriptor['value'])->toTimeString() === '00:00:00') {
            $query->addNestedWhereQuery(
                $query->newPivotStatement()->whereDate(
                    $query->getTable().".{$field}",
                    $filterDescriptor['operator'] ?? '=',
                    $filterDescriptor['value']
                )
            );
        } elseif (! is_array($filterDescriptor['value'])) {
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
        $table = (new $this->resourceModelClass())->getTable();

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
        if (! $requestedSearchDescriptor = $request->get('search')) {
            return;
        }

        $searchables = $this->resource->searchableBy();

        $query->where(
            function ($whereQuery) use ($searchables, $requestedSearchDescriptor) {
                $requestedSearchString = $requestedSearchDescriptor['value'] ?? '';

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
                                if (! $caseSensitive) {
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

                        if (! $caseSensitive) {
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
        $allowedSorts = collect($this->resource->allowedSorts())
            ->each(function ($allowedSort) {
                if (! $allowedSort instanceof AllowedSort) {
                    throw new InvalidResourceFilterException($allowedSort);
                }
            })
            ->keyBy
            ->getName();

        $sortableDescriptors = collect($request->get('sort', []))
            ->map(function ($sort) use ($allowedSorts) {
                if (! $allowedSort = $allowedSorts->get($sort['field'])) {
                    return null;
                }

                $sort['field'] = $allowedSort?->getInternalName();

                return $sort;
            })
            ->filter();

        foreach ($sortableDescriptors as $sortable) {
            $direction = Arr::get($sortable, 'direction', 'asc');
            $sortableField = $sortable['field'];

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
                $relationInstance = (new $this->resourceModelClass())->{$relation}();

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
}
