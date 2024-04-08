<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        //$response = $this->get('/app');
        //$response->assertStatus(200);
    }

    public function test_the_api_returns_a_successful_response(): void
    {
        $response = $this->get('/api/cd/check');
        $response->assertJson([
            'message' => 'API is working!',
        ]);
        $response->assertStatus(200);
    }
}
