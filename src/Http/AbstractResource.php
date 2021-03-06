<?php

namespace LiveIntent\LaravelCommon\Http;

use ReflectionClass;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use LiveIntent\LaravelCommon\Http\Exceptions\InvalidResourceModelException;

abstract class AbstractResource extends JsonResource
{
    /**
     * The base model of the resource.
     *
     * @var string
     */
    protected static $model;

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
     * Create a new filtered collection for the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public static function filteredCollection(Builder $query = null): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $modelClass = static::$model;

        // The QueryBuilder will require a valid database model
        // so before we do anything we'll assert it is valid
        if (! (new ReflectionClass($modelClass))->isSubclassOf(Model::class)) {
            throw new InvalidResourceModelException(static::class, $modelClass);
        }

        // Next we'll build up the child resource so that we'll
        // have access to some of the vital instance methods
        $resource = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        // Finally, we use the QueryBuilder to magically filter
        // the collection based on the user's provided query
        $builder = QueryBuilder::for($query ?? $modelClass);

        $model = $builder->getModel();

        $collection = $builder
            ->select($model->getTable() . '.*')
            ->distinct($model->getTable() . '.' . $model->getKeyName())
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
}
