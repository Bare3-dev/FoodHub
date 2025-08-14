<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\DeviceToken;

class FCMService
{
    private ?string $serverKey;
    private string $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct()
    {
        $this->serverKey = config('services.fcm.server_key');
    }

    public function sendToToken(string $token, array $data, array $notification = []): bool
    {
        $payload = [
            'to' => $token,
            'data' => $data,
            'notification' => $notification,
            'priority' => 'high',
            'content_available' => true,
        ];

        return $this->sendRequest($payload);
    }

    public function sendToMultipleTokens(array $tokens, array $data, array $notification = []): bool
    {
        if (empty($tokens)) {
            return false;
        }

        $payload = [
            'registration_ids' => $tokens,
            'data' => $data,
            'notification' => $notification,
            'priority' => 'high',
            'content_available' => true,
        ];

        return $this->sendRequest($payload);
    }

    public function sendToTopic(string $topic, array $data, array $notification = []): bool
    {
        $payload = [
            'to' => '/topics/' . $topic,
            'data' => $data,
            'notification' => $notification,
            'priority' => 'high',
            'content_available' => true,
        ];

        return $this->sendRequest($payload);
    }

    public function sendToUserType(string $userType, int $userId, array $data, array $notification = []): bool
    {
        $tokens = DeviceToken::active()
            ->byUserType($userType)
            ->where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("No active device tokens found for {$userType} ID: {$userId}");
            return false;
        }

        return $this->sendToMultipleTokens($tokens, $data, $notification);
    }

    public function sendToAllCustomers(array $data, array $notification = []): bool
    {
        $tokens = DeviceToken::active()
            ->byUserType('customer')
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("No active customer device tokens found");
            return false;
        }

        // Split into chunks to avoid FCM limits (1000 tokens per request)
        $chunks = array_chunk($tokens, 1000);
        $success = true;

        foreach ($chunks as $chunk) {
            if (!$this->sendToMultipleTokens($chunk, $data, $notification)) {
                $success = false;
            }
        }

        return $success;
    }

    private function sendRequest(array $payload): bool
    {
        if (!$this->serverKey) {
            Log::warning('FCM server key not configured, skipping notification', [
                'payload' => $payload
            ]);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                if (isset($result['success']) && $result['success'] > 0) {
                    Log::info('FCM notification sent successfully', [
                        'success_count' => $result['success'],
                        'failure_count' => $result['failure'] ?? 0,
                        'payload' => $payload
                    ]);
                    return true;
                } else {
                    Log::warning('FCM notification failed', [
                        'response' => $result,
                        'payload' => $payload
                    ]);
                    return false;
                }
            } else {
                Log::error('FCM request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('FCM service error', [
                'message' => $e->getMessage(),
                'payload' => $payload
            ]);
            return false;
        }
    }

    public function validateToken(string $token): bool
    {
        if (!$this->serverKey) {
            Log::warning('FCM server key not configured, cannot validate token');
            return false;
        }

        // Send a test message to validate token
        $payload = [
            'to' => $token,
            'data' => ['test' => 'validation'],
            'priority' => 'high',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                $result = $response->json();
                return isset($result['success']) && $result['success'] > 0;
            }
        } catch (\Exception $e) {
            Log::error('Token validation error', ['message' => $e->getMessage()]);
        }

        return false;
    }
}
