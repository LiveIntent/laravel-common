<?php

namespace LiveIntent\LaravelCommon\Tests\Fixtures\App\Http\Resources;

use LiveIntent\LaravelCommon\Http\AllowedScope;
use LiveIntent\LaravelCommon\Http\AllowedFilter;
use LiveIntent\LaravelCommon\Http\AbstractResource;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\Post;

class PostResource extends AbstractResource
{
    /**
     * The base model of the resource.
     *
     * @var string
     */
    protected static $model = Post::class;

    /**
     * The allowed query scopes for the resource.
     *
     * @return array
     */
    public function allowedScopes()
    {
        return [
            AllowedScope::name('published')
        ];
    }

    /**
     * The allowed filters for the resource.
     *
     * @return array
     */
    public function allowedFilters()
    {
        return [
            AllowedFilter::string('title')
        ];
    }
}
