<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

final class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'restaurant_id',
        'restaurant_branch_id',
        'role',
        'permissions',
        'status',
        'phone',
        'last_login_at',
        'is_email_verified',
        'profile_image_url',
        'email_otp_code',
        'email_otp_expires_at',
        'is_mfa_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'is_email_verified' => 'boolean',
            'email_otp_expires_at' => 'datetime',
            'is_mfa_enabled' => 'boolean',
        ];
    }

    /**
     * Get the restaurant that the user belongs to.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the restaurant branch that the user belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('SUPER_ADMIN');
    }

    /**
     * Check if the user is a restaurant owner.
     */
    public function isRestaurantOwner(): bool
    {
        return $this->hasRole('RESTAURANT_OWNER') || $this->isSuperAdmin();
    }

    /**
     * Generate and store an email OTP code.
     */
    public function generateEmailOtpCode(): string
    {
        $code = (string) random_int(100000, 999999); // Generate a 6-digit code
        $this->email_otp_code = Hash::make($code);
        $this->email_otp_expires_at = now()->addMinutes(config('auth.mfa_otp_expiration', 5)); // Configurable expiration
        $this->save();

        return $code;
    }

    /**
     * Verify an email OTP code.
     */
    public function verifyEmailOtpCode(string $code): bool
    {
        if (! $this->email_otp_code || ! $this->email_otp_expires_at) {
            return false;
        }

        if (now()->isAfter($this->email_otp_expires_at)) {
            $this->clearEmailOtpCode();
            return false;
        }

        if (! Hash::check($code, $this->email_otp_code)) {
            return false;
        }

        // Code is valid, clear it after successful verification
        $this->clearEmailOtpCode();

        return true;
    }

    /**
     * Clear the email OTP code and expiration time.
     */
    public function clearEmailOtpCode(): void
    {
        $this->email_otp_code = null;
        $this->email_otp_expires_at = null;
        $this->save();
    }

    /**
     * Enable MFA for the user.
     */
    public function enableMfa(): void
    {
        $this->is_mfa_enabled = true;
        $this->save();
    }

    /**
     * Disable MFA for the user.
     */
    public function disableMfa(): void
    {
        $this->is_mfa_enabled = false;
        $this->save();
    }

    /**
     * Check if MFA is enabled for the user.
     */
    public function hasMfaEnabled(): bool
    {
        return (bool) $this->is_mfa_enabled;
    }
}
