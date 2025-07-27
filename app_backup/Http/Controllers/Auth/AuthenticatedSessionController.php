<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\MfaOtpMail;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response
    {
        $request->authenticate();

        $user = Auth::user();

        if ($user && $user->hasMfaEnabled()) {
            // Generate and send OTP
            $otpCode = $user->generateEmailOtpCode();
            Mail::to($user->email)->send(new MfaOtpMail($otpCode, $user->name));

            // Log out the user from the session temporarily until MFA is verified
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response(['message' => 'MFA required', 'mfa_enabled' => true], 202); // 202 Accepted for MFA pending
        }

        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Verify the Multi-Factor Authentication (MFA) code.
     */
    public function verifyMfa(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (! $user || ! $user->hasMfaEnabled() || ! $user->verifyEmailOtpCode($request->otp)) {
            return response(['message' => 'Invalid MFA code or MFA not enabled for this user.'], 401);
        }

        // If MFA code is valid, log the user in
        Auth::login($user);
        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
