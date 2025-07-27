<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\SecurityLog;
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

                // Generate a temporary token for MFA verification
                $tempToken = encrypt([
                    'user_id' => $user->id,
                    'expires_at' => now()->addMinutes(10), // 10-minute expiry
                    'purpose' => 'mfa_verification'
                ]);

                // Return a standardized MFA challenge response
                return response()->json([
                    'success' => true,
                    'message' => 'MFA required. An OTP has been sent to your email.',
                    'data' => [
                        'mfa_required' => true,
                        'temp_token' => $tempToken,
                        'user_id' => $user->id,
                    ]
                ], 200); // 200 OK because the request was successful, but requires further action
            } else {
                $token = $user->createToken('api-token')->plainTextToken;

                // Log successful login
                SecurityLog::logEvent('login_success', $user->id);

                // Return a standardized success response with token and user data
                return response()->json([
                    'success' => true,
                    'message' => 'Login successful.',
                    'data' => [
                        'token' => $token,
                        'user' => new UserResource($user),
                    ]
                ], 200);
            }
        }

        // This line might not be reached if authenticate() throws an exception,
        // but included for completeness or if custom authentication logic is added.
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    /**
     * Verify the Multi-Factor Authentication (MFA) code for API.
     */
    public function verifyMfa(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'temp_token' => ['required', 'string'],
                'otp' => ['required', 'string', 'digits:6'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The provided data is invalid.',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $tokenData = decrypt($request->temp_token);
            
            // Verify token hasn't expired
            if (now()->isAfter($tokenData['expires_at']) || $tokenData['purpose'] !== 'mfa_verification') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired temporary token.',
                    'errors' => []
                ], 401);
            }

            $user = User::find($tokenData['user_id']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid temporary token.',
                'errors' => []
            ], 401);
        }

        if (! $user || ! $user->hasMfaEnabled() || ! $user->verifyEmailOtpCode($request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid MFA code or MFA not enabled for this user.',
                'errors' => []
            ], 401);
        }

        // If MFA code is valid, issue the API token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'MFA verified successfully. Login complete.',
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ]
        ], 200);
    }

    /**
     * Destroy an authenticated API session (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $request->user()->currentAccessToken()->delete();

        // Log logout event
        SecurityLog::logEvent('logout', $userId);

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ], 200);
    }
} 