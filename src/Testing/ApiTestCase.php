<?php

namespace LiveIntent\LaravelCommon\Testing;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class ApiTestCase extends TestCase
{
    use WithFaker;
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        TestResponse::macro('assertValidationErrors', function (array $invalidFields): TestResponse {
            return $this->assertStatus(422)->assertJsonValidationErrors(
                collect($invalidFields)->map(fn ($f) => Str::camel($f))->toArray()
            );
        });

        TestResponse::macro('assertResponseData', function (array $data): TestResponse {
            return $this->assertOk()->assertJson(['data' => $data]);
        });

        TestResponse::macro('assertResponseCount', function (int $count): TestResponse {
            return $this->assertOk()->assertJsonCount($count, 'data');
        });
    }
}
