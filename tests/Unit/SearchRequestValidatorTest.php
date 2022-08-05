<?php

namespace LiveIntent\LaravelCommon\Tests\Unit;

use Illuminate\Http\Request;
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
        //
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
    public function number_fields_are_only_filterable_with_relevant_operators()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::number('views')
                ];
            }
        };

        collect([
            '=', '!=', 'in', 'not in', '>', '>=', '<', '<='
        ])->each(function ($operator) use ($resource) {
            $this->assertValid($resource, [
                'filters' => [['field' => 'views', 'value' => 'red', 'operator' => $operator]]
            ]);
        });

        $this->assertInvalid($resource, [
            'filters' => [['field' => 'color', 'value' => 'red', 'operator' => 'cookies']]
        ]);
    }

    /** @test */
    public function timestamp_fields_are_only_filterable_with_relevant_operators()
    {
        //
    }

    // search
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
