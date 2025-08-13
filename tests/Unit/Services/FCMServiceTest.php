<?php

namespace Tests\Unit\Services;

use App\Models\DeviceToken;
use App\Services\FCMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FCMServiceTest extends TestCase
{
    use RefreshDatabase;

    private FCMService $fcmService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('services.fcm.server_key', 'test_server_key');
        $this->fcmService = new FCMService();
    }

    /** @test */
    public function it_can_send_to_single_token()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 1,
                'failure' => 0,
            ], 200),
        ]);

        $token = 'test_token_123';
        $data = ['type' => 'test'];
        $notification = ['title' => 'Test'];

        $result = $this->fcmService->sendToToken($token, $data, $notification);

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) use ($token) {
            return $request->url() === 'https://fcm.googleapis.com/fcm/send' &&
                   $request->header('Authorization')[0] === 'key=test_server_key' &&
                   $request->data()['to'] === $token;
        });
    }

    /** @test */
    public function it_can_send_to_multiple_tokens()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 2,
                'failure' => 0,
            ], 200),
        ]);

        $tokens = ['token1', 'token2'];
        $data = ['type' => 'test'];
        $notification = ['title' => 'Test'];

        $result = $this->fcmService->sendToMultipleTokens($tokens, $data, $notification);

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->data()['registration_ids'] === ['token1', 'token2'];
        });
    }

    /** @test */
    public function it_returns_false_for_empty_tokens()
    {
        $result = $this->fcmService->sendToMultipleTokens([], ['type' => 'test']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_send_to_topic()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 1,
                'failure' => 0,
            ], 200),
        ]);

        $topic = 'test_topic';
        $data = ['type' => 'test'];
        $notification = ['title' => 'Test'];

        $result = $this->fcmService->sendToTopic($topic, $data, $notification);

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) use ($topic) {
            return $request->data()['to'] === "/topics/{$topic}";
        });
    }

    /** @test */
    public function it_can_send_to_user_type()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 1,
                'failure' => 0,
            ], 200),
        ]);

        // Create test device tokens
        DeviceToken::factory()->count(2)->create([
            'user_type' => 'customer',
            'user_id' => 1,
            'is_active' => true,
        ]);

        $data = ['type' => 'test'];
        $notification = ['title' => 'Test'];

        $result = $this->fcmService->sendToUserType('customer', 1, $data, $notification);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_no_tokens_found()
    {
        $result = $this->fcmService->sendToUserType('customer', 999, ['type' => 'test']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_send_to_all_customers()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 1,
                'failure' => 0,
            ], 200),
        ]);

        // Create test customer device tokens
        DeviceToken::factory()->count(3)->create([
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $data = ['type' => 'promotional'];
        $notification = ['title' => 'Promo', 'body' => 'Test promo'];

        $result = $this->fcmService->sendToAllCustomers($data, $notification);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_fcm_failure_response()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 0,
                'failure' => 1,
            ], 200),
        ]);

        $result = $this->fcmService->sendToToken('test_token', ['type' => 'test']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_http_error_response()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([], 500),
        ]);

        $result = $this->fcmService->sendToToken('test_token', ['type' => 'test']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_network_exception()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::throw(fn() => new \Exception('Network error')),
        ]);

        $result = $this->fcmService->sendToToken('test_token', ['type' => 'test']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_validate_token()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 1,
                'failure' => 0,
            ], 200),
        ]);

        $result = $this->fcmService->validateToken('test_token');

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_for_invalid_token()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 0,
                'failure' => 1,
            ], 200),
        ]);

        $result = $this->fcmService->validateToken('invalid_token');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_chunks_large_token_lists()
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 1000,
                'failure' => 0,
            ], 200),
        ]);

        // Create 2500 customer device tokens in database
        DeviceToken::factory()->count(2500)->create([
            'user_type' => 'customer',
            'is_active' => true,
        ]);
        
        $data = ['type' => 'bulk'];
        $notification = ['title' => 'Bulk Test'];

        $result = $this->fcmService->sendToAllCustomers($data, $notification);

        $this->assertTrue($result);
        
        // Should have made 3 requests (2500 tokens / 1000 per chunk)
        Http::assertSentCount(3);
    }
}
