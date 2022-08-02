<?php

namespace LiveIntent\LaravelCommon\Http;

use ReflectionClass;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LiveIntent\LaravelCommon\Http\Exceptions\InvalidResourceModelException;
use Orion\Drivers\Standard\SearchBuilder;
use Orion\Drivers\Standard\ParamsValidator;
use Orion\Drivers\Standard\RelationsResolver;
use Orion\Http\Requests\Request as OrionRequest;
use Spatie\QueryBuilder\AllowedSort;

abstract class AbstractResource extends JsonResource
{
    /**
     * The base model of the resource.
     *
     * @var string
     */
    protected static $model;

    /**
     * The fields to use when performing full text search.
     */
    public function searchableBy()
    {
        return [];
    }

    /**
     * The allowed query scopes for the resource.
     *
     * @return array
     */
    public function allowedScopes()
    {
        return [];
    }

    /**
     * The allowed sortable fields for the resource.
     *
     * @return array
     */
    public function allowedSorts()
    {
        return [];
    }

    /**
     * The allowed filters for the resource.
     *
     * @return array
     */
    public function allowedFilters()
    {
        return [];
    }

    /**
     * The allowed relationships to load for the resource.
     *
     * @return array
     */
    public function allowedIncludes()
    {
        return [];
    }

    /**
     * The relationships that should always be loaded for the resource.
     *
     * @return array
     */
    public function alwaysInclude()
    {
        return [];
    }

    /**
     *
     */
    public static function filteredCollectionTwo(Builder $query = null): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $request = request();

        $conditions = collect($request->input('conditions', []));

        $modelClass = static::$model;

        $query = $modelClass::query();

        $conditions->each(function ($condition) {
            if ($this->isFilterCondition($condition)) {
                return $this->applyFitlerCondition($query, $condition);
            }

            return $this->applyGroupCondition($query, $condition);
        });

        // for each conditions of the body
            // if the condition has a field, then apply the condition
                // apply field, value, operator
            // else its a grouper, so apply it as a group


        $collection = $query->get();

        return static::collection($collection);
    }

    /**
     * Create a new filtered collection for the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public static function filteredCollection(Builder $query = null): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $request = request();
        $modelClass = static::$model;

        // The QueryBuilder will require a valid database model
        // so before we do anything we'll assert it is valid
        if (! (new ReflectionClass($modelClass))->isSubclassOf(Model::class)) {
            throw new InvalidResourceModelException(static::class, $modelClass);
        }

        // Next we'll build up the child resource so that we'll
        // have access to some of the vital instance methods
        $resource = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        // Okay, it's decision time! We need to determine which
        // search method we'll use based on the user's input
        $builderClass = $request->isMethod('GET') ? BasicQueryBuilder::class : AdvancedQueryBuilder::class;

        // do validation rules here i guess

        // Finally, we use the QueryBuilder to magically filter
        // the collection based on the user's provided query
        $builder = $builderClass::for($query ?? $modelClass);

        $model = $builder->getModel();

        $collection = $builder
            // ->select($model->getTable() . '.*')
            // ->distinct($model->getTable() . '.' . $model->getKeyName())
            ->with($resource->alwaysInclude())
            ->allowedFilters($resource->allowedFilters())
            ->allowedIncludes($resource->allowedIncludes())
            ->allowedSorts(
                array_merge(
                    $resource->allowedSorts(),
                    $model->usesTimestamps() ? [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()] : []
                )
            )
            ->jsonPaginate();

        return static::collection($collection);
    }

    /**
     * Create a new filtered collection for the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public static function search()
    {
        $request = request();
        $modelClass = static::$model;

        // The QueryBuilder will require a valid database model
        // so before we do anything we'll assert it is valid
        if (! (new ReflectionClass($modelClass))->isSubclassOf(Model::class)) {
            throw new InvalidResourceModelException(static::class, $modelClass);
        }

        // Next we'll build up the child resource so that we'll
        // have access to some of the vital instance methods
        $resource = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        $paramsValidator = new ParamsValidator(
            ['withDeactivated'],
            collect($resource->allowedFilters())->map->getName()->toArray(),
            collect($resource->allowedSorts())->map(function ($sort) {
                if (is_string($sort)) {
                    return AllowedSort::field($sort);
                }

                return $sort;
            })->map->getName()->toArray()
        );

        $searchBuilder = new SearchBuilder($resource->searchableBy());
        $relationsResolver = new RelationsResolver([], []);

        $builder = app()->make(FilterQueryBuilder::class, [
            'resourceModelClass' => $modelClass,
            'paramsValidator' => $paramsValidator,
            'relationsResolver' => $relationsResolver,
            'searchBuilder' => $searchBuilder,
            'intermediateMode' => false,
            'resource' => $resource
        ]);

        $query = $builder->buildQuery($modelClass::query(), OrionRequest::createFrom($request));

        $collection = $query->get();
        // Okay, it's decision time! We need to determine which
        // search method we'll use based on the user's input
        // $builderClass = $request->isMethod('GET') ? BasicQueryBuilder::class : AdvancedQueryBuilder::class;

        // do validation rules here i guess

        // Finally, we use the QueryBuilder to magically filter
        // the collection based on the user's provided query
        // $builder = $builderClass::for($query ?? $modelClass);

        // $model = $builder->getModel();

        // $collection = $builder
        //     // ->select($model->getTable() . '.*')
        //     // ->distinct($model->getTable() . '.' . $model->getKeyName())
        //     ->with($resource->alwaysInclude())
        //     ->allowedFilters($resource->allowedFilters())
        //     ->allowedIncludes($resource->allowedIncludes())
        //     ->allowedSorts(
        //         array_merge(
        //             $resource->allowedSorts(),
        //             $model->usesTimestamps() ? [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()] : []
        //         )
        //     )
        //     ->jsonPaginate();

        return static::collection($collection);
    }
}
