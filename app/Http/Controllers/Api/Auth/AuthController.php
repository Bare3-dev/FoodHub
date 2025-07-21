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

                return response()->json(['message' => 'MFA required', 'mfa_enabled' => true, 'email' => $user->email], 202); // 202 Accepted for MFA pending
            } else {
                $token = $user->createToken('api-token')->plainTextToken;

                return response()->json(['token' => $token, 'user' => new UserResource($user)]);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    /**
     * Verify the Multi-Factor Authentication (MFA) code for API.
     */
    public function verifyMfa(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->hasMfaEnabled() || ! $user->verifyEmailOtpCode($request->otp)) {
            return response()->json(['message' => 'Invalid MFA code or MFA not enabled for this user.'], 401);
        }

        // If MFA code is valid, issue the API token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => new UserResource($user)]);
    }

    /**
     * Destroy an authenticated API session (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
} 