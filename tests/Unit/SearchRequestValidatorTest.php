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
    public function filters_must_have_a_field_and_a_value()
    {
        $resource = new class (null) extends AbstractResource {
            protected static $model = Post::class;
        };

        $this->assertInvalid($resource, [
            'filters' => [['field' => 'published_at']]
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

    // /** @test */
    // public function filters_must_have_a_field_and_a_value()
    // {
    //     $resource = new class (null) extends AbstractResource {
    //         protected static $model = Post::class;
    //     };

    //     $this->assertInvalid($resource, [
    //         'filters' => [['field' => 'published_at']]
    //     ]);
    // }

    private function assertInvalid(AbstractResource $resource, $payload)
    {
        $this->assertTrue(SearchRequestValidator::make(new Request($payload), $resource)->fails());
    }

    private function assertValid(AbstractResource $resource, $payload)
    {
        $this->assertFalse(SearchRequestValidator::make(new Request($payload), $resource)->fails());
    }
}
