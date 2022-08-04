<?php

namespace LiveIntent\LaravelCommon\Tests\Feature;

use Illuminate\Support\Facades\Config;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\Post;
use LiveIntent\LaravelCommon\Tests\TestCase;

class SearchRequestTest extends TestCase
{
    /** @test */
    public function search_requests_can_be_made()
    {
        $this->post('/api/posts/search')->assertResponseCount(0);

        Post::factory()->count(10)->create();
        $this->post('/api/posts/search')->assertResponseCount(10);
    }

    /** @test */
    public function search_requests_are_paginated_using_the_configured_default()
    {
        Config::set('json-api-paginate.default_size', $size = 5);

        Post::factory()->count($size * 2)->create();

        $this->post('/api/posts/search')->assertResponseCount($size);
    }

    /** @test */
    public function search_requests_can_be_explicitly_paginated()
    {
        Post::factory()->count(5)->create();

        $this->postJson('/api/posts/search', ['page' => ['size' => 1]])->assertResponseCount(1);
        $this->postJson('/api/posts/search', ['page' => ['size' => 2]])->assertResponseCount(2);
        $this->postJson('/api/posts/search', ['page' => ['size' => 10]])->assertResponseCount(5);

        // TODO
        // Config::set('json-api-paginate.max_results', 10);
        // $this->postJson('/api/posts/search', ['page' => ['size' => 11]])->assertValidationErrors('page.size');
    }
}
