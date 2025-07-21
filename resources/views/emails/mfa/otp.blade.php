<x-mail::message>
# Your Multi-Factor Authentication (MFA) Code

Hello {{ $userName ?? 'User' }},

Your Multi-Factor Authentication (MFA) code is:

<x-mail::panel>
# {{ $otpCode }}
</x-mail::panel>

This code is valid for {{ config('auth.mfa_otp_expiration', 5) }} minutes. Please do not share this code with anyone.

If you did not request this code, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
