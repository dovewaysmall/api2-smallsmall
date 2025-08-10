<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class UserApiTest extends TestCase
{
    // use RefreshDatabase;

    public function test_users_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);
    }

    public function test_users_endpoint_returns_users_list(): void
    {
        // Use an existing user from the database
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No users found in database');
        }
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id'
                ]
            ]
        ]);
    }

    public function test_users_count_endpoint(): void
    {
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No users found in database');
        }
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users/count');
        $response->assertStatus(200);
        $response->assertJsonStructure(['count']);
    }

    public function test_user_show_endpoint(): void
    {
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No users found in database');
        }
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users/' . $user->id);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id'
            ]
        ]);
    }
}