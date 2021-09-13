<?php

namespace LiveIntent\LaravelCommon\Routing;

use ReflectionClass;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use LiveIntent\LaravelCommon\Routing\Exceptions\InvalidResourceModelException;

abstract class AbstractResource extends JsonResource
{
    /**
     * The base model of the resource.
     *
     * @var \Illuminate\Database\Eloquent\Model
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
     */
    public static function filteredCollection()
    {
        $model = static::$model;

        // The QueryBuilder will require a valid database model
        // so before we do anything we'll assert it is valid
        if (! (new ReflectionClass($model))->isSubclassOf(Model::class)) {
            throw new InvalidResourceModelException(static::class, $model);
        }

        // Next we'll build up the child resource so that we'll
        // have access to some of the vital instance methods
        $resource = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        // Finally, we use the QueryBuilder to magically filter
        // the collection based on the user's provided query
        $builder = QueryBuilder::for($model);

        $collection = $builder
            ->select($builder->getModel()->getTable() . '.*')
            ->distinct($builder->getModel()->getTable() . '.' . $builder->getModel()->getKeyName())
            ->with($resource->alwaysInclude())
            ->allowedFilters($resource->allowedFilters())
            ->allowedIncludes($resource->allowedIncludes())
            ->allowedSorts($resource->allowedSorts())
            ->jsonPaginate();

        return static::collection($collection);
    }
}
