<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    const LOGIN_URL = '/api/auth/login';

    #[Test]
    public function user_can_login_via_api_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'is_mfa_enabled' => false,
            'status' => 'active'
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'token',
                         'user' => [
                             'id', 'name', 'email', 'role'
                         ]
                     ]
                 ])
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class
        ]);
    }

    #[Test]
    public function user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'status' => 'active'
        ]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email' => $user->email,
            'password' => 'wrong-password'
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'email' => [
                __('auth.failed')
            ]
        ]);
    }

    #[Test]
    public function inactive_user_cannot_login()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'status' => 'inactive'
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function user_with_mfa_enabled_receives_mfa_challenge()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'is_mfa_enabled' => true,
            'status' => 'active'
        ]);

        $response = $this->postJson('/api/auth/login', [
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

    #[Test]
    public function user_can_complete_mfa_verification()
    {
        $user = User::factory()->create([
            'is_mfa_enabled' => true,
            'email_otp_code' => Hash::make('123456'),
            'email_otp_expires_at' => now()->addMinutes(5)
        ]);

        $tempToken = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(5),
            'purpose' => 'mfa_verification'
        ]);

        $response = $this->postJson('/api/auth/mfa/verify', [
            'temp_token' => $tempToken,
            'otp' => '123456'
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
    }

    #[Test]
    public function mfa_verification_fails_with_invalid_code()
    {
        $user = User::factory()->create([
            'is_mfa_enabled' => true,
            'email_otp_code' => Hash::make('123456'),
            'email_otp_expires_at' => now()->addMinutes(5)
        ]);

        $tempToken = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(5),
            'purpose' => 'mfa_verification'
        ]);

        $response = $this->postJson('/api/auth/mfa/verify', [
            'temp_token' => $tempToken,
            'otp' => '000000'
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function mfa_verification_fails_with_expired_temp_token()
    {
        $user = User::factory()->create([
            'is_mfa_enabled' => true,
            'email_otp_code' => Hash::make('123456'),
            'email_otp_expires_at' => now()->subMinutes(1)
        ]);

        $expiredTempToken = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->subMinutes(1),
            'purpose' => 'mfa_verification'
        ]);

        $response = $this->postJson('/api/auth/mfa/verify', [
            'temp_token' => $expiredTempToken,
            'otp' => '123456'
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id
        ]);
    }

    #[Test]
    public function login_validates_required_fields()
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function login_validates_email_format()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password123'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function mfa_verification_validates_required_fields()
    {
        $response = $this->postJson('/api/auth/mfa/verify', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['temp_token']);
    }

    #[Test]
    public function mfa_verification_requires_either_code_or_backup_code()
    {
        $user = User::factory()->create(['is_mfa_enabled' => true]);
        $tempToken = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(5),
            'purpose' => 'mfa_verification'
        ]);

        $response = $this->postJson('/api/auth/mfa/verify', [
            'temp_token' => $tempToken
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['otp']);
    }

    #[Test]
    public function api_responses_include_security_headers()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'is_mfa_enabled' => false,
            'status' => 'active'
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
                 ->assertHeader('X-Frame-Options', 'DENY')
                 ->assertHeader('X-XSS-Protection', '1; mode=block');
    }
} 