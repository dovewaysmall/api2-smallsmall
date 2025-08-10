<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Schema;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the user_tbl table for SQLite testing
        if (!Schema::hasTable('user_tbl')) {
            Schema::create('user_tbl', function ($table) {
                $table->id();
                $table->string('userID')->unique();
                $table->string('firstName');
                $table->string('lastName');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('phone')->nullable();
                $table->string('user_type')->default('tenant');
                $table->timestamps();
            });
        }
    }

    public function test_users_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);
    }

    public function test_users_endpoint_returns_users_list(): void
    {
        // Create test user directly
        $user = $this->createTestUser();
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
        $user = $this->createTestUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users/count');
        $response->assertStatus(200);
        $response->assertJsonStructure(['count']);
    }

    public function test_user_show_endpoint(): void
    {
        $user = $this->createTestUser();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/users/{$user->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id'
            ]
        ]);
    }

    private function createTestUser(): User
    {
        return User::create([
            'userID' => 'TEST' . time(),
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'test' . time() . '@example.com',
            'password' => bcrypt('password'),
            'phone' => '1234567890',
            'user_type' => 'tenant'
        ]);
    }
}