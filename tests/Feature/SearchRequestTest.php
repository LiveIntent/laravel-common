<?php

namespace LiveIntent\LaravelCommon\Tests\Feature;

use LiveIntent\LaravelCommon\Tests\TestCase;

class SearchRequestTest extends TestCase
{
    /** @test */
    public function search_requests_can_be_made()
    {
        $response = $this->post('/api/posts/search');

        $response->assertOk();
    }
}
