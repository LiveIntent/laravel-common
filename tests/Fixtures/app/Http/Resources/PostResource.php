<?php

namespace LiveIntent\LaravelCommon\Tests\Fixtures\App\Http\Resources;

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
}
