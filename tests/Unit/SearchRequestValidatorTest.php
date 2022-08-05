<?php

namespace LiveIntent\LaravelCommon\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use LiveIntent\LaravelCommon\Tests\TestCase;
use LiveIntent\LaravelCommon\Http\AllowedScope;
use LiveIntent\LaravelCommon\Http\AbstractResource;
use LiveIntent\LaravelCommon\Http\AllowedFilter;
use LiveIntent\LaravelCommon\Http\SearchRequestValidator;

class SearchRequestValidatorTest extends TestCase
{
    /** @test */
    public function scopes_must_be_whitelisted()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedScopes()
            {
                return [
                    AllowedScope::name('valid')
                ];
            }
        };

        $this->assertInvalid($resource, [
            'scopes' => [['name' => 'published']]
        ]);

        $this->assertInvalid($resource, [
            'scopes' => [['name' => 'published'], ['name' => 'valid']]
        ]);

        $this->assertValid($resource, [
            'scopes' => [['name' => 'valid']]
        ]);
    }

    /** @test */
    public function filters_must_have_a_field()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color')
                ];
            }
        };

        $this->assertInvalid($resource, [
            'filters' => [['value' => 'color']]
        ]);

        $this->assertValid($resource, [
            'filters' => [['field' => 'color']]
        ]);
    }

    /** @test */
    public function filters_may_use_boolean_logic()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color')
                ];
            }
        };

        $this->assertInvalid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'color', 'value' => 'red', 'type' => 'xor'],
            ]
        ]);

        $this->assertInvalid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'color', 'value' => 'red', 'type' => 'cookies'],
            ]
        ]);

        $this->assertValid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'color', 'value' => 'red', 'type' => 'and'],
            ]
        ]);

        $this->assertValid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'color', 'value' => 'red', 'type' => 'or'],
            ]
        ]);
    }

    /** @test */
    public function filter_fields_must_be_whitelisted()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color')
                ];
            }
        };

        $this->assertInvalid($resource, [
            'filters' => [['field' => 'colour', 'value' => 'red']]
        ]);

        $this->assertInvalid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'colour', 'value' => 'red']
            ]
        ]);

        $this->assertInvalid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['type' => 'or', 'nested' => [
                    ['field' => 'color', 'value' => 'red'],
                    ['field' => 'colour', 'value' => 'red'],
                ]],
            ]
        ]);

        $this->assertValid($resource, [
            'filters' => [['field' => 'color', 'value' => 'red']]
        ]);

        $this->assertValid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['type' => 'or', 'nested' => [
                    ['field' => 'color', 'value' => 'red'],
                ]],
            ]
        ]);
    }

    /** @test */
    public function filters_may_only_be_nested_to_a_certain_configured_max_depth()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color')
                ];
            }
        };

        Config::set('liveintent.search.max_nested_depth', 3);
        $this->assertValid($resource, [
            'filters' => [
                ['nested' => [
                    ['nested' => [
                        ['nested' => [
                            ['field' => 'color', 'value' => 'green']
                        ]]
                    ]]
                ]]
            ]
        ]);

        Config::set('liveintent.search.max_nested_depth', 2);
        $this->expectException(ValidationException::class);
        $this->assertInvalid($resource, [
            'filters' => [
                ['nested' => [
                    ['nested' => [
                        ['nested' => [
                            ['field' => 'color', 'value' => 'green']
                        ]]
                    ]]
                ]]
            ]
        ]);
    }

    /** @test */
    public function string_fields_are_only_filterable_with_relevant_operators_and_values()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color')
                ];
            }
        };

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<=', 'like', 'not like'])
            ->crossJoin(['red', 'blue', '', null])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'color', 'value' => $value, 'operator' => $operator]]
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin([['red'], ['red', 'blue'], ['red', null]])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'color', 'value' => $value, 'operator' => $operator]]
                ]);
        });

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<=', 'like', 'not like'])
            ->crossJoin([1, 100, [], ['red'], false])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'color', 'value' => $value, 'operator' => $operator]]
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin(['red', 'blue', '', null, false, [], [100], [false]])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'color', 'value' => $value, 'operator' => $operator]]
                ]);
            });
    }

    /** @test */
    public function number_fields_are_only_filterable_with_relevant_operators_and_values()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::number('likes')
                ];
            }
        };

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<='])
            ->crossJoin([-1, 0, 1, 2, null, ''])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]]
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin([[1], [1, 2], [3, null]])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]]
                ]);
        });

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<='])
            ->crossJoin(['1', '100', 'red', [], ['red'], false])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]]
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin(['red', 'blue', '', null, false, 1, 100, [], ['100'], ['red']])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]]
                ]);
            });

        collect()
            ->concat(['like', 'not like'])
            ->crossJoin([-1, 0, 1, 2, null])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]]
                ]);
            });
    }

    /** @test */
    public function timestamp_fields_are_only_filterable_with_relevant_operators_and_values()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::timestamp('went_to_darkside_at')
                ];
            }
        };

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<='])
            ->crossJoin([null, '', '2022-01-01', '2022-01-01 00:00:00', '2022-01-01T00:00:00'])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'went_to_darkside_at', 'value' => $value, 'operator' => $operator]]
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin([[null], ['2022-01-01'], ['2022-01-01 00:00:00'], ['2022-01-01T00:00:00']])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'went_to_darkside_at', 'value' => $value, 'operator' => $operator]]
                ]);
        });

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<='])
            ->crossJoin(['1', '100', 'red', [], ['red'], false, 1659571200, 'now', 'yesterday', '+1 week'])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'went_to_darkside_at', 'value' => $value, 'operator' => $operator]]
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin(['red', 'blue', '', null, false, 1, 100, [], ['100'], ['red'], [1659571200], ['now'], ['yesterday'], ['+1 week']])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'went_to_darkdside_at', 'value' => $value, 'operator' => $operator]]
                ]);
            });

        collect()
            ->concat(['like', 'not like'])
            ->crossJoin([null, '2022-01-01', '2022-01-01 00:00:00', '2022-01-01T00:00:00'])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'went_to_darkdside_at', 'value' => $value, 'operator' => $operator]]
                ]);
            });
    }

    /** @test */
    public function searches_must_have_a_valid_string_value()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;
        };

        $this->assertValid($resource, ['search' => ['value' => 'foobar']]);
        $this->assertValid($resource, ['search' => ['value' => 'f']]);
        $this->assertValid($resource, ['search' => ['value' => '100']]);
        $this->assertValid($resource, ['search' => ['value' => null]]);
        $this->assertValid($resource, ['search' => ['value' => '']]);

        $this->assertInvalid($resource, ['search' => null]);
        $this->assertInvalid($resource, ['search' => 'foobar']);
        $this->assertInvalid($resource, ['search' => ['value' => 100]]);
        $this->assertInvalid($resource, ['search' => ['value' => false]]);
        $this->assertInvalid($resource, ['search' => ['value' => true]]);
        $this->assertInvalid($resource, ['search' => ['value' => []]]);
        $this->assertInvalid($resource, ['search' => ['value' => ['foobar']]]);
    }

    /** @test */
    public function searches_may_have_a_boolean_arg_for_case_sensitive()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;
        };

        $this->assertValid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => true]]);
        $this->assertValid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => false]]);
        $this->assertValid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 0]]);
        $this->assertValid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 1]]);
        $this->assertValid($resource, ['search' => ['case_sensitive' => true]]);

        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 'true']]);
        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 'false']]);
        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 100]]);
        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 'yes']]);
        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 'no']]);
    }

    // sort
    // pagination

    private function assertInvalid(AbstractResource $resource, $payload)
    {
        $this->assertTrue(SearchRequestValidator::make(new Request($payload), $resource)->fails(), sprintf("Expected payload to be invalid. Used: %s", json_encode($payload)));
    }

    private function assertValid(AbstractResource $resource, $payload)
    {
        $this->assertFalse(SearchRequestValidator::make(new Request($payload), $resource)->fails(), sprintf("Expected payload to be valid. Used: %s", json_encode($payload)));
    }
}
