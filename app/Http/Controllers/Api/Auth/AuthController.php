<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\MfaOtpMail;
use App\Http\Resources\Api\UserResource;

class AuthController extends Controller
{
    /**
     * Handle an incoming authentication request for API.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = Auth::user();
        if ($user instanceof User) {
            if ($user->hasMfaEnabled()) {
                // Generate and send OTP
                $otpCode = $user->generateEmailOtpCode();
                Mail::to($user->email)->send(new MfaOtpMail($otpCode, $user->name));

                $tempToken = encrypt([
                    'user_id' => $user->id,
                    'expires_at' => now()->addMinutes(5),
                    'purpose' => 'mfa_verification'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'MFA required',
                    'data' => [
                        'mfa_required' => true,
                        'temp_token' => $tempToken,
                        'user_id' => $user->id
                    ]
                ], 200);
            } else {
                $token = $user->createToken('api-token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'token' => $token,
                        'user' => new UserResource($user)
                    ]
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    /**
     * Verify the Multi-Factor Authentication (MFA) code for API.
     */
    public function verifyMfa(Request $request): JsonResponse
    {
        $request->validate([
            'temp_token' => ['required', 'string'],
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        try {
            $tokenData = decrypt($request->temp_token);
            
            if ($tokenData['purpose'] !== 'mfa_verification' || 
                $tokenData['expires_at'] < now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token.'
                ], 401);
            }

            $user = User::find($tokenData['user_id']);

            if (! $user || ! $user->hasMfaEnabled() || ! $user->verifyEmailOtpCode($request->otp)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid MFA code or MFA not enabled for this user.'
                ], 401);
            }

            // If MFA code is valid, issue the API token
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'MFA verification successful',
                'data' => [
                    'token' => $token,
                    'user' => new UserResource($user)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token format.'
            ], 401);
        }
    }

    /**
     * Destroy an authenticated API session (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
} 