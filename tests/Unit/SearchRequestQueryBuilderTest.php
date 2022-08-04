<?php

namespace LiveIntent\LaravelCommon\Tests\Unit;

use Illuminate\Http\Request;
use LiveIntent\LaravelCommon\Tests\TestCase;
use LiveIntent\LaravelCommon\Http\AllowedSort;
use LiveIntent\LaravelCommon\Http\AllowedScope;
use LiveIntent\LaravelCommon\Http\AllowedFilter;
use LiveIntent\LaravelCommon\Http\AbstractResource;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\Post;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\User;
use LiveIntent\LaravelCommon\Http\Resources\RelationsResolver;
use LiveIntent\LaravelCommon\Http\Resources\SearchRequestQueryBuilder;
use LiveIntent\LaravelCommon\Http\Exceptions\InvalidResourceScopeException;

class SearchRequestQueryBuilderTest extends TestCase
{
    /** @test */
    public function scopes_can_be_applied_to_the_query()
    {
        $postA = Post::factory()->create(['publish_at' => '2019-01-01 09:35:14']);
        $postB = Post::factory()->create(['publish_at' => '2019-01-01 09:35:14', 'meta' => 'verse']);
        $postC = Post::factory()->create(['publish_at' => '2020-02-01 09:35:14']);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedScopes()
            {
                return [
                    AllowedScope::name('specialMetaAliasName', 'withMeta'),
                    AllowedScope::name('publishedAt'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'scopes',
                [
                    ['name' => 'specialMetaAliasName'],
                    ['name' => 'publishedAt', 'parameters' => ['2019-01-01 09:35:14']],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyScopesToQuery($query, $request)
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals($postB->id, $results->first()->id);
    }

    /** @test */
    public function allowed_scopes_must_be_valid_instances()
    {
        $this->expectException(InvalidResourceScopeException::class);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedScopes()
            {
                return [
                    'publishedAt'
                ];
            }
        };

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $queryBuilder->applyScopesToQuery(Post::query(), new Request());
    }

    /** @test */
    public function fields_are_filterable_with_single_values()
    {
        $postA = Post::factory()->create(['title' => 'test post', 'tracking_id' => 1]);
        $postB = Post::factory()->create(['title' => 'another test post', 'tracking_id' => 5]);
        $postC = Post::factory()->create(['title' => 'different post', 'tracking_id' => 10]);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('myTitleAlias', 'title'),
                    AllowedFilter::number('tracking_id'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'filters',
                [
                    ['field' => 'myTitleAlias', 'operator' => '=', 'value' => 'test post'],
                    ['type' => 'or', 'field' => 'tracking_id', 'operator' => '=', 'value' => 5],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_multiple_values()
    {
        $postA = Post::factory()->create(['title' => 'test post', 'tracking_id' => 1]);
        $postB = Post::factory()->create(['title' => 'another test post', 'tracking_id' => 5]);
        $postC = Post::factory()->create(['title' => 'different post', 'tracking_id' => 10]);
        $postD = Post::factory()->create(['title' => 'different post', 'tracking_id' => 15]);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                    AllowedFilter::number('tracking_id'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'filters',
                [
                    ['field' => 'title', 'operator' => 'in', 'value' => ['test post', 'something else']],
                    ['type' => 'or', 'field' => 'tracking_id', 'operator' => 'in', 'value' => [5, 10]],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(3, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
        $this->assertFalse($results->contains('id', $postD->id));
    }

    /** @test */
    public function related_fields_are_filterable_with_singular_values()
    {
        $postAUser = User::factory()->create(['name' => 'test user A']);
        $postA = Post::factory()->for($postAUser)->create();

        $postBUser = User::factory()->create(['name' => 'test user B']);
        $postB = Post::factory()->for($postBUser)->create();

        $postCUser = User::factory()->create(['name' => 'test user C']);
        $postC = Post::factory()->for($postCUser)->create();

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('user.name'),
                    AllowedFilter::string('user.aliasedName', 'user.name'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'filters',
                [
                    ['field' => 'user.aliasedName', 'operator' => '=', 'value' => 'test user A'],
                    ['type' => 'or', 'field' => 'user.name', 'operator' => '=', 'value' => 'test user B'],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    /** @test */
    public function related_fields_are_filterable_with_multiple_values()
    {
        $postAUser = User::factory()->create(['name' => 'test user A']);
        $postA = Post::factory()->for($postAUser)->create();

        $postBUser = User::factory()->create(['name' => 'test user B']);
        $postB = Post::factory()->for($postBUser)->create();

        $postCUser = User::factory()->create(['name' => 'test user C']);
        $postC = Post::factory()->for($postCUser)->create();

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('user.name'),
                    AllowedFilter::string('user.aliasedName', 'user.name'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'filters',
                [
                    ['field' => 'user.aliasedName', 'operator' => 'in', 'value' => ['test user A', 'test user B']],
                    ['type' => 'or', 'field' => 'user.name', 'operator' => 'in', 'value' => ['test user C']],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(3, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_the_not_in_operator()
    {
        $postA = Post::factory()->create(['title' => 'test post A']);
        $postB = Post::factory()->create(['title' => 'test post B']);
        $postC = Post::factory()->create(['title' => 'test post C']);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'filters',
                [
                    ['field' => 'title', 'operator' => 'not in', 'value' => ['test post A', 'test post B']],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(1, $results);
        $this->assertFalse($results->contains('id', $postA->id));
        $this->assertFalse($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_the_like_and_not_like_operators()
    {
        $postA = Post::factory()->create(['title' => 'test post A']);
        $postB = Post::factory()->create(['title' => 'test post B']);
        $postC = Post::factory()->create(['title' => 'test post C']);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'filters',
                [
                    ['field' => 'title', 'operator' => 'like', 'value' => 'test post%'],
                    ['field' => 'title', 'operator' => 'not like', 'value' => '%B%'],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertFalse($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_the_inequality_operators()
    {
        $postA = Post::factory()->create(['publish_at' => '2019-01-01 09:35:14']);
        $postB = Post::factory()->create(['publish_at' => '2019-01-02 09:35:14']);
        $postC = Post::factory()->create(['publish_at' => '2019-01-03 09:35:14']);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::timestamp('publish_at'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'filters',
                [
                    ['field' => 'publish_at', 'operator' => '>', 'value' => '2019-01-01'],
                    ['field' => 'publish_at', 'operator' => '<=', 'value' => '2019-01-03 08:00:00'],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_nested_filters_up_to_the_max_configured_depth()
    {
        $postA = Post::factory()->create(['title' => 'test post', 'tracking_id' => 1]);
        $postB = Post::factory()->create(['title' => 'another test post', 'tracking_id' => 5]);
        $postC = Post::factory()->create(['title' => 'different post', 'tracking_id' => 10]);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'filters',
                [
                    ['field' => 'title', 'operator' => '=', 'value' => 'test post'],
                    ['type' => 'or', 'nested' => [
                        ['field' => 'title', 'operator' => 'like', 'value' => '%post%'],
                        ['type' => 'and', 'nested' => [
                            ['field' => 'title', 'operator' => 'like', 'value' => '%post%'],
                            ['field' => 'title', 'operator' => 'not like', 'value' => '%different%'],
                        ]],
                    ]],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    /** @test */
    public function full_text_search_can_be_done_on_specified_fields()
    {
        $postA = Post::factory()->create(['title' => 'title example']);
        $postB = Post::factory()->create(['title' => 'example title']);
        $postC = Post::factory()->create(['title' => 'title with example in the middle']);
        $postD = Post::factory()->create(['title' => 'not matching title', 'body' => 'but matching example body']);
        $postE = Post::factory()->create(['title' => 'not matching title']);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function searchableBy()
            {
                return [
                    'title', 'body'
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'search', ['value' => 'example'],
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applySearchingToQuery($query, $request)
        )->get();

        $this->assertCount(4, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
        $this->assertTrue($results->contains('id', $postD->id));
        $this->assertFalse($results->contains('id', $postE->id));
    }

    /** @test */
    public function full_text_search_can_be_done_on_specified_related_fields()
    {
        $postAUser = User::factory()->create(['name' => 'name example']);
        $postA = Post::factory()->for($postAUser)->create();

        $postBUser = User::factory()->create(['name' => 'example name']);
        $postB = Post::factory()->for($postBUser)->create();

        $postCUser = User::factory()->create(['name' => 'name with example in the middle']);
        $postC = Post::factory()->for($postCUser)->create();

        $postDUser = User::factory()->create(['name' => 'not matching name', 'email' => 'but-matching-email@example.com']);
        $postD = Post::factory()->for($postDUser)->create();

        $postEUser = User::factory()->create(['name' => 'not matching name', 'email' => 'test@domain.com']);
        $postE = Post::factory()->for($postEUser)->create();

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function searchableBy()
            {
                return [
                    'user.name', 'user.email'
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'search', ['value' => 'example'],
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applySearchingToQuery($query, $request)
        )->get();

        $this->assertCount(4, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
        $this->assertTrue($results->contains('id', $postD->id));
        $this->assertFalse($results->contains('id', $postE->id));
    }

    /** @test */
    public function sort_can_be_applied_on_model_fields()
    {
        $postC = Post::factory()->create(['title' => 'post C']);
        $postB = Post::factory()->create(['title' => 'post B']);
        $postA = Post::factory()->create(['title' => 'post A']);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedSorts()
            {
                return [
                    AllowedSort::field('title')
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'sort', [
                    ['field' => 'title'],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applySortingToQuery($query, $request)
        )->get();

        $this->assertEquals($postA->id, $results[0]->id);
        $this->assertEquals($postB->id, $results[1]->id);
        $this->assertEquals($postC->id, $results[2]->id);
    }

    /** @test */
    public function sort_can_be_applied_in_reverse_on_model_fields()
    {
        $postA = Post::factory()->create(['title' => 'post A']);
        $postB = Post::factory()->create(['title' => 'post B']);
        $postC = Post::factory()->create(['title' => 'post C']);

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedSorts()
            {
                return [
                    AllowedSort::field('title')
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'sort', [
                    ['field' => 'title', 'direction' => 'desc'],
                ]
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applySortingToQuery($query, $request)
        )->get();

        $this->assertEquals($postC->id, $results[0]->id);
        $this->assertEquals($postB->id, $results[1]->id);
        $this->assertEquals($postA->id, $results[2]->id);
    }

    /** @test */
    public function sort_can_be_applied_on_related_fields()
    {
        $postAUser = User::factory()->create(['name' => 'name C']);
        $postA = Post::factory()->for($postAUser)->create();

        $postBUser = User::factory()->create(['name' => 'name B']);
        $postB = Post::factory()->for($postBUser)->create();

        $postCUser = User::factory()->create(['name' => 'name A']);
        $postC = Post::factory()->for($postCUser)->create();

        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedSorts()
            {
                return [
                    AllowedSort::field('user.name')
                ];
            }
        };

        $request = tap(new Request(), function ($req) {
            $req->query->set(
                'sort', [
                    ['field' => 'user.name']
                ],
            );
        });

        $queryBuilder = new SearchRequestQueryBuilder($resource, new RelationsResolver([], []));

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applySortingToQuery($query, $request)
        )->get();

        $this->assertEquals($postC->id, $results[0]->id);
        $this->assertEquals($postB->id, $results[1]->id);
        $this->assertEquals($postA->id, $results[2]->id);
    }
}
