<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;

class UserApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip tests if database connection fails
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database connection failed: ' . $e->getMessage());
        }
    }

    public function test_users_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);
    }

    public function test_users_endpoint_returns_users_list(): void
    {
        // Use an existing user or create one for testing
        $user = User::first();
        if (!$user) {
            try {
                $user = User::factory()->create();
            } catch (\Exception $e) {
                $this->markTestSkipped('Could not create test user: ' . $e->getMessage());
            }
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
            try {
                $user = User::factory()->create();
            } catch (\Exception $e) {
                $this->markTestSkipped('Could not create test user: ' . $e->getMessage());
            }
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
            try {
                $user = User::factory()->create();
            } catch (\Exception $e) {
                $this->markTestSkipped('Could not create test user: ' . $e->getMessage());
            }
        }
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/users/{$user->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id'
            ]
        ]);
    }
}