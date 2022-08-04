<?php

namespace LiveIntent\LaravelCommon\Testing;

use Illuminate\Testing\TestResponse;

trait InteractsWithJSONResponses
{
    /**
     * Register macros on the TestResponse class.
     */
    public function registerResponseMacros()
    {
        TestResponse::macro('assertValidationErrors', function (array|string $invalidFields): TestResponse {
            /** @var TestResponse $this */
            return $this->assertStatus(422)->assertJsonValidationErrors($invalidFields);
        });

        TestResponse::macro('assertResponseData', function (array $data): TestResponse {
            /** @var TestResponse $this */
            return $this->assertOk()->assertJson(['data' => $data]);
        });

        TestResponse::macro('assertResponseCount', function (int $count): TestResponse {
            /** @var TestResponse $this */
            return $this->assertOk()->assertJsonCount($count, 'data');
        });
    }
}
