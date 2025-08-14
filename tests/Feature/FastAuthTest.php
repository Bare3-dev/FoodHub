<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FastAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials()
    {
        // Create a simple user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'CASHIER',
            'permissions' => [],
            'status' => 'active',
            'is_mfa_enabled' => false,
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        // Test login
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'token',
                         'user'
                     ]
                 ])
                 ->assertJson(['success' => true]);

        // Verify token was created
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class
        ]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'CASHIER',
            'permissions' => [],
            'status' => 'active',
            'is_mfa_enabled' => false,
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password'
        ]);

        $response->assertStatus(422)
                 ->assertJson(['success' => false]);
    }

    public function test_inactive_user_cannot_login()
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'CASHIER',
            'permissions' => [],
            'status' => 'inactive',
            'is_mfa_enabled' => false,
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(403);
    }

    public function test_user_with_mfa_enabled_receives_challenge()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'CASHIER',
            'permissions' => [],
            'status' => 'active',
            'is_mfa_enabled' => true,
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'mfa_required',
                         'temp_token',
                         'user_id'
                     ]
                 ])
                 ->assertJson([
                     'success' => true,
                     'data' => ['mfa_required' => true]
                 ]);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'CASHIER',
            'permissions' => [],
            'status' => 'active',
            'is_mfa_enabled' => false,
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        // Token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id
        ]);
    }
}
