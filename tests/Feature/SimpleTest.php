<?php

namespace Tests\Feature;

use Tests\TestCase;

class SimpleTest extends TestCase
{
    /** @test */
    public function test_basic_functionality()
    {
        $this->assertTrue(true);
    }

    /** @test */
    public function test_api_response_structure()
    {
        $response = $this->getJson('/api/test');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'timestamp'
        ]);
    }

    /** @test */
    public function test_authentication_required()
    {
        $response = $this->getJson('/api/courses');
        
        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }
}
