<?php

namespace LiveIntent\LaravelCommon\Tests\Unit;

use Mockery;
use Illuminate\Routing\Route;
use Illuminate\Http\Request;
use LiveIntent\LaravelCommon\Http\AbstractResource;
use LiveIntent\LaravelCommon\Http\AllowedFilter;
use LiveIntent\LaravelCommon\Http\AllowedScope;
use LiveIntent\LaravelCommon\Http\Exceptions\InvalidResourceScopeException;
use LiveIntent\LaravelCommon\Http\Resources\FullTextSearchBuilder;
use LiveIntent\LaravelCommon\Http\Resources\RelationsResolver;
// use Orion\Http\Requests\Request;
//

use LiveIntent\LaravelCommon\Http\Resources\SearchRequestQueryBuilder;
use LiveIntent\LaravelCommon\Tests\TestCase;
use Orion\Drivers\Standard\QueryBuilder;
use Orion\Drivers\Standard\SearchBuilder;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\Post;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\User;
use Orion\Tests\Fixtures\App\Models\Team;
use Orion\Drivers\Standard\ParamsValidator;
use Orion\Tests\Unit\Drivers\Standard\Stubs\ControllerStub;

class SearchQueryBuilderTest extends TestCase
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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

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

        $queryBuilder = new SearchRequestQueryBuilder(
            $resource,
            new RelationsResolver([], []),
            new FullTextSearchBuilder([])
        );

        $results = tap(
            Post::query(),
            fn ($query) => $queryBuilder->applyFiltersToQuery($query, $request)
        )->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    // /** @test */
    // public function searching_on_model_fields()
    // {
    //     $request = $this->makeRequestWithSearch(
    //         [
    //             'value' => 'example',
    //         ]
    //     );

    //     $postA = factory(Post::class)->create(['title' => 'title example']);
    //     $postB = factory(Post::class)->create(['title' => 'example title']);
    //     $postC = factory(Post::class)->create(['title' => 'title with example in the middle']);
    //     $postD = factory(Post::class)->create(['title' => 'not matching title', 'body' => 'but matching example body']);
    //     $postE = factory(Post::class)->create(['title' => 'not matching title']);

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], []),
    //         new RelationsResolver([], []),
    //         new SearchBuilder(['title', 'body'])
    //     );
    //     $queryBuilder->applySearchingToQuery($query, $request);

    //     $posts = $query->get();

    //     $this->assertCount(4, $posts);
    //     $this->assertTrue($posts->contains('id', $postA->id));
    //     $this->assertTrue($posts->contains('id', $postB->id));
    //     $this->assertTrue($posts->contains('id', $postC->id));
    //     $this->assertTrue($posts->contains('id', $postD->id));
    //     $this->assertFalse($posts->contains('id', $postE->id));
    // }

    // protected function makeRequestWithSearch(array $search)
    // {
    //     $request = new Request();
    //     $request->setRouteResolver(
    //         function () {
    //             return new Route('GET', '/api/tags', [ControllerStub::class, 'index']);
    //         }
    //     );
    //     $request->query->set('search', $search);

    //     return $request;
    // }

    // /** @test */
    // public function searching_on_relation_fields()
    // {
    //     $request = $this->makeRequestWithSearch(
    //         [
    //             'value' => 'example',
    //         ]
    //     );

    //     $postAUser = factory(User::class)->create(['name' => 'name example']);
    //     $postA = factory(Post::class)->create(['user_id' => $postAUser->id]);

    //     $postBUser = factory(User::class)->create(['name' => 'example name']);
    //     $postB = factory(Post::class)->create(['user_id' => $postBUser->id]);

    //     $postCUser = factory(User::class)->create(['name' => 'name with example in the middle']);
    //     $postC = factory(Post::class)->create(['user_id' => $postCUser->id]);

    //     $postDUser = factory(User::class)->create(['name' => 'not matching name', 'email' => 'but-matching-email@example.com']);
    //     $postD = factory(Post::class)->create(['user_id' => $postDUser->id]);

    //     $postEUser = factory(User::class)->create(['name' => 'not matching name', 'email' => 'test@domain.com']);
    //     $postE = factory(Post::class)->create(['user_id' => $postEUser->id]);

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], []),
    //         new RelationsResolver([], []),
    //         new SearchBuilder(['user.name', 'user.email'])
    //     );
    //     $queryBuilder->applySearchingToQuery($query, $request);

    //     $posts = $query->get();

    //     $this->assertCount(4, $posts);
    //     $this->assertTrue($posts->contains('id', $postA->id));
    //     $this->assertTrue($posts->contains('id', $postB->id));
    //     $this->assertTrue($posts->contains('id', $postC->id));
    //     $this->assertTrue($posts->contains('id', $postD->id));
    //     $this->assertFalse($posts->contains('id', $postE->id));
    // }

    // /** @test */
    // public function search_query_constraints_are_not_applied_if_descriptor_is_missing_in_request()
    // {
    //     $request = new Request();
    //     $request->setRouteResolver(
    //         function () {
    //             return new Route('GET', '/api/posts', [ControllerStub::class, 'index']);
    //         }
    //     );

    //     factory(Post::class)->times(3)->create(['title' => 'not matching']);

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], []),
    //         new RelationsResolver([], []),
    //         new SearchBuilder(['title', 'body'])
    //     );
    //     $queryBuilder->applySearchingToQuery($query, $request);

    //     $posts = $query->get();

    //     $this->assertCount(3, $posts);
    // }

    // //TODO: test sorting on different relation types

    // /** @test */
    // public function default_sorting_based_on_model_fields()
    // {
    //     $request = $this->makeRequestWithSort(
    //         [
    //             ['field' => 'title'],
    //         ]
    //     );

    //     $postA = factory(Post::class)->create(['title' => 'post A']);
    //     $postB = factory(Post::class)->create(['title' => 'post B']);
    //     $postC = factory(Post::class)->create(['title' => 'post C']);

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], [], ['title']),
    //         new RelationsResolver([], []),
    //         new SearchBuilder([])
    //     );
    //     $queryBuilder->applySortingToQuery($query, $request);

    //     $posts = $query->get();

    //     $this->assertEquals($postA->id, $posts[0]->id);
    //     $this->assertEquals($postB->id, $posts[1]->id);
    //     $this->assertEquals($postC->id, $posts[2]->id);
    // }

    // protected function makeRequestWithSort(array $sort)
    // {
    //     $request = new Request();
    //     $request->setRouteResolver(
    //         function () {
    //             return new Route('GET', '/api/tags', [ControllerStub::class, 'index']);
    //         }
    //     );
    //     $request->query->set('sort', $sort);

    //     return $request;
    // }

    // /** @test */
    // public function desc_sorting_based_on_model_fields()
    // {
    //     $request = $this->makeRequestWithSort(
    //         [
    //             ['field' => 'title', 'direction' => 'desc'],
    //         ]
    //     );

    //     $postA = factory(Post::class)->create(['title' => 'post A']);
    //     $postB = factory(Post::class)->create(['title' => 'post B']);
    //     $postC = factory(Post::class)->create(['title' => 'post C']);

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], [], ['title']),
    //         new RelationsResolver([], []),
    //         new SearchBuilder([])
    //     );
    //     $queryBuilder->applySortingToQuery($query, $request);

    //     $posts = $query->get();

    //     $this->assertEquals($postC->id, $posts[0]->id);
    //     $this->assertEquals($postB->id, $posts[1]->id);
    //     $this->assertEquals($postA->id, $posts[2]->id);
    // }

    // /** @test */
    // public function default_sorting_based_on_relation_fields()
    // {
    //     $request = $this->makeRequestWithSort(
    //         [
    //             ['field' => 'user.name'],
    //         ]
    //     );

    //     $postAUser = factory(User::class)->create(['name' => 'user A']);
    //     $postA = factory(Post::class)->create(['user_id' => $postAUser->id]);

    //     $postBUser = factory(User::class)->create(['name' => 'user B']);
    //     $postB = factory(Post::class)->create(['user_id' => $postBUser->id]);

    //     $postCUser = factory(User::class)->create(['name' => 'user C']);
    //     $postC = factory(Post::class)->create(['user_id' => $postCUser->id]);

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], [], ['user.name']),
    //         new RelationsResolver([], []),
    //         new SearchBuilder([])
    //     );
    //     $queryBuilder->applySortingToQuery($query, $request);

    //     $posts = $query->get();

    //     $this->assertEquals($postA->id, $posts[0]->id);
    //     $this->assertEquals($postB->id, $posts[1]->id);
    //     $this->assertEquals($postC->id, $posts[2]->id);
    // }

    // /** @test */
    // public function desc_sorting_based_on_relation_fields()
    // {
    //     $request = $this->makeRequestWithSort(
    //         [
    //             ['field' => 'user.name', 'direction' => 'desc'],
    //         ]
    //     );

    //     $postAUser = factory(User::class)->create(['name' => 'user A']);
    //     $postA = factory(Post::class)->create(['user_id' => $postAUser->id]);

    //     $postBUser = factory(User::class)->create(['name' => 'user B']);
    //     $postB = factory(Post::class)->create(['user_id' => $postBUser->id]);

    //     $postCUser = factory(User::class)->create(['name' => 'user C']);
    //     $postC = factory(Post::class)->create(['user_id' => $postCUser->id]);

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], [], ['user.name']),
    //         new RelationsResolver([], []),
    //         new SearchBuilder([])
    //     );
    //     $queryBuilder->applySortingToQuery($query, $request);

    //     $posts = $query->get();

    //     $this->assertEquals($postC->id, $posts[0]->id);
    //     $this->assertEquals($postB->id, $posts[1]->id);
    //     $this->assertEquals($postA->id, $posts[2]->id);
    // }

    // /** @test */
    // public function soft_deletes_query_constraints_are_not_applied_if_model_is_not_soft_deletable()
    // {
    //     $request = new Request();
    //     $request->setRouteResolver(
    //         function () {
    //             return new Route('GET', '/api/teams', [ControllerStub::class, 'index']);
    //         }
    //     );

    //     $queryMock = Mockery::mock(Team::query())->makePartial();
    //     $queryMock->shouldNotReceive('withTrashed');
    //     $queryMock->shouldNotReceive('onlyTrashed');

    //     $queryBuilder = new QueryBuilder(
    //         Team::class,
    //         new ParamsValidator([], []),
    //         new RelationsResolver([], []),
    //         new SearchBuilder([])
    //     );

    //     $this->assertFalse($queryBuilder->applySoftDeletesToQuery($queryMock, $request));
    // }

    // /** @test */
    // public function trashed_models_are_returned_when_requested()
    // {
    //     $request = new Request();
    //     $request->setRouteResolver(
    //         function () {
    //             return new Route('GET', '/api/posts', [ControllerStub::class, 'index']);
    //         }
    //     );
    //     $request->query->set('with_trashed', true);

    //     $post = factory(Post::class)->create();
    //     $softDeletedPost = factory(Post::class)->state('trashed')->create();

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], []),
    //         new RelationsResolver([], []),
    //         new SearchBuilder([])
    //     );
    //     $this->assertTrue($queryBuilder->applySoftDeletesToQuery($query, $request));

    //     $posts = $query->get();

    //     $this->assertCount(2, $posts);
    //     $this->assertTrue($posts->contains('id', $post->id));
    //     $this->assertTrue($posts->contains('id', $softDeletedPost->id));
    // }

    // /** @test */
    // public function only_trashed_models_are_returned_when_requested()
    // {
    //     $request = new Request();
    //     $request->setRouteResolver(
    //         function () {
    //             return new Route('GET', '/api/posts', [ControllerStub::class, 'index']);
    //         }
    //     );
    //     $request->query->set('only_trashed', true);

    //     $post = factory(Post::class)->create();
    //     $softDeletedPost = factory(Post::class)->state('trashed')->create();

    //     $query = Post::query();

    //     $queryBuilder = new QueryBuilder(
    //         Post::class,
    //         new ParamsValidator([], []),
    //         new RelationsResolver([], []),
    //         new SearchBuilder([])
    //     );
    //     $this->assertTrue($queryBuilder->applySoftDeletesToQuery($query, $request));

    //     $posts = $query->get();

    //     $this->assertCount(1, $posts);
    //     $this->assertFalse($posts->contains('id', $post->id));
    //     $this->assertTrue($posts->contains('id', $softDeletedPost->id));
    // }
}