<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceToken\RegisterDeviceTokenRequest;
use App\Http\Requests\DeviceToken\RemoveDeviceTokenRequest;
use App\Models\DeviceToken;
use App\Services\FCMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    private FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Register a device token for push notifications
     */
    public function register(RegisterDeviceTokenRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Determine user type and ID from the request
            $userType = $validated['user_type'];
            $userId = $validated['user_id'];
            $token = $validated['token'];
            $platform = $validated['platform'];

            // Check if token already exists
            $existingToken = DeviceToken::where('token', $token)->first();
            
            if ($existingToken) {
                // Update existing token
                $existingToken->update([
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'platform' => $platform,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
                
                Log::info("Device token updated", [
                    'token' => $token,
                    'user_type' => $userType,
                    'user_id' => $userId
                ]);
            } else {
                // Create new token
                DeviceToken::create([
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'token' => $token,
                    'platform' => $platform,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
                
                Log::info("New device token registered", [
                    'token' => $token,
                    'user_type' => $userType,
                    'user_id' => $userId
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Device token registered successfully',
                'data' => [
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'platform' => $platform,
                    'registered_at' => now()->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Device token registration failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register device token',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove a device token
     */
    public function remove(RemoveDeviceTokenRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $token = $validated['token'];
            $userType = $validated['user_type'];
            $userId = $validated['user_id'];

            $deviceToken = DeviceToken::where('token', $token)
                ->where('user_type', $userType)
                ->where('user_id', $userId)
                ->first();

            if (!$deviceToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device token not found'
                ], 404);
            }

            $deviceToken->update(['is_active' => false]);
            
            Log::info("Device token deactivated", [
                'token' => $token,
                'user_type' => $userType,
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device token removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Device token removal failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove device token',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all active tokens for a user
     */
    public function getUserTokens(Request $request): JsonResponse
    {
        try {
            $userType = $request->input('user_type');
            $userId = $request->input('user_id');

            if (!$userType || !$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User type and user ID are required'
                ], 400);
            }

            $tokens = DeviceToken::active()
                ->byUserType($userType)
                ->where('user_id', $userId)
                ->select(['id', 'platform', 'last_used_at', 'created_at'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'tokens' => $tokens,
                    'count' => $tokens->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user tokens', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve device tokens',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate a device token
     */
    public function validateToken(Request $request): JsonResponse
    {
        try {
            $token = $request->input('token');
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token is required'
                ], 400);
            }

            $isValid = $this->fcmService->validateToken($token);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'is_valid' => $isValid,
                    'validated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Token validation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate token',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Send test notification to a specific user
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        try {
            $userType = $request->input('user_type');
            $userId = $request->input('user_id');

            if (!$userType || !$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User type and user ID are required'
                ], 400);
            }

            // Check if user has active tokens
            $hasTokens = DeviceToken::active()
                ->byUserType($userType)
                ->where('user_id', $userId)
                ->exists();

            if (!$hasTokens) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active device tokens found for this user'
                ], 404);
            }

            $success = $this->fcmService->sendToUserType($userType, $userId, [
                'type' => 'test',
                'timestamp' => now()->toISOString(),
            ], [
                'title' => 'Test Notification',
                'body' => 'This is a test notification from your app',
            ]);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test notification sent successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test notification'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Test notification failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification',
                'error' => 'Internal server error'
            ], 500);
        }
    }
}
