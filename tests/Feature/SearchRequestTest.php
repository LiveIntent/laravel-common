<?php

namespace LiveIntent\LaravelCommon\Tests\Feature;

use Illuminate\Support\Facades\Config;
use LiveIntent\LaravelCommon\Tests\TestCase;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\Post;

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
        $this->postJson('/api/posts/search?page[size]=1')->assertResponseCount(1);
        $this->postJson('/api/posts/search?page[size]=2')->assertResponseCount(2);
        $this->postJson('/api/posts/search?page[size]=10')->assertResponseCount(5);

        Config::set('json-api-paginate.max_results', 10);
        $this->postJson('/api/posts/search', ['page' => ['size' => 11]])->assertValidationErrors('page.size');
        $this->postJson('/api/posts/search?page[size]=11')->assertValidationErrors('page.size');
    }

    /** @test */
    public function search_requests_can_use_scopes()
    {
        Post::factory(2)->create(['publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create();

        $this->postJson('/api/posts/search', [
            'scopes' => [
                ['name' => 'published']
            ]
        ])->assertResponseCount(2);
    }

    /** @test */
    public function search_requests_can_use_filters()
    {
        Post::factory(2)->create(['title' => 'special title']);
        Post::factory(2)->create(['title' => 'less special title', 'publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create(['title' => 'very special title', 'publish_at' => now()->subSeconds(2)]);

        $this->postJson('/api/posts/search', [
            'scopes' => [
                ['name' => 'published']
            ],
            'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => '%special%'],
                ['field' => 'title', 'operator' => 'like', 'value' => '%very%'],
            ]
        ])->assertResponseCount(2);
    }

    /** @test */
    public function search_requests_can_use_sorts()
    {
        Post::factory(2)->create(['title' => 'special title']);
        Post::factory(2)->create(['title' => 'less special title', 'publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create(['title' => 'very special title', 'publish_at' => now()->subSeconds(10)]);

        $response = $this->postJson('/api/posts/search', [
            'scopes' => [
                ['name' => 'published']
            ],
            'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => '%special%'],
            ],
            'sort' => [
                ['field' => 'publish_at']
            ]
        ])->assertResponseCount(4);

        $this->assertEquals('very special title', $response->json('data')[0]['title']);
    }

    /** @test */
    public function search_requests_can_use_full_text_search()
    {
        Post::factory(2)->create(['title' => 'special title']);
        Post::factory(2)->create(['title' => 'less special title', 'publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create(['title' => 'very special title', 'publish_at' => now()->subSeconds(10)]);

        $response = $this->postJson('/api/posts/search', [
            'scopes' => [
                ['name' => 'published']
            ],
            'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => '%special%'],
            ],
            'search' => [
                'value' => 'very'
            ],
            'sort' => [
                ['field' => 'publish_at']
            ]
        ])->assertResponseCount(2);

        $this->assertEquals('very special title', $response->json('data')[0]['title']);
    }
}
