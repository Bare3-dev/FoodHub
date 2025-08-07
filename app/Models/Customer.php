<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class Customer extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'date_of_birth',
        'gender',
        'profile_image_url',
        'preferences',
        'status',
        'marketing_emails_enabled',
        'sms_notifications_enabled',
        'push_notifications_enabled',
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
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'preferences' => 'array',
            'last_login_at' => 'datetime',
            'total_spent' => 'decimal:2',
            'marketing_emails_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the customer addresses for the customer.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Get the default address for the customer.
     */
    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    /**
     * Get the orders for the customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the customer's loyalty points.
     */
    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyPoint::class);
    }

    /**
     * Get the customer's spin wheel data.
     */
    public function customerSpins(): HasMany
    {
        return $this->hasMany(CustomerSpin::class);
    }

    /**
     * Get the customer's challenges.
     */
    public function customerChallenges(): HasMany
    {
        return $this->hasMany(CustomerChallenge::class);
    }

    /**
     * Get the customer's spin results.
     */
    public function spinResults(): HasMany
    {
        return $this->hasMany(SpinResult::class);
    }

    /**
     * Get the customer's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include customers with verified emails.
     */
    public function scopeEmailVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope a query to only include customers with verified phones.
     */
    public function scopePhoneVerified($query)
    {
        return $query->whereNotNull('phone_verified_at');
    }

    /**
     * Check if the customer has a specific role.
     * Customers always have the 'CUSTOMER' role.
     */
    public function hasRole(string $role): bool
    {
        return $role === 'CUSTOMER';
    }

    /**
     * Check if the customer has a specific permission.
     * Customers have basic permissions for their own orders.
     */
    public function hasPermission(string $permission): bool
    {
        $customerPermissions = [
            'order:cancel-own',
            'order:view-own',
            'order:create'
        ];
        
        return in_array($permission, $customerPermissions, true);
    }

    /**
     * Check if the customer is a super admin (always false for customers).
     */
    public function isSuperAdmin(): bool
    {
        return false;
    }

    /**
     * Check if the customer can access a specific role (customers have limited access).
     */
    public function canAccessRole(string $role): bool
    {
        // Customers can only access their own role (CUSTOMER)
        return $role === 'CUSTOMER';
    }

    /**
     * Get the customer's role (always CUSTOMER).
     */
    public function getRoleAttribute(): string
    {
        return 'CUSTOMER';
    }

    /**
     * Get the customer's permissions.
     */
    public function getPermissionsAttribute(): array
    {
        return [
            'order:cancel-own',
            'order:view-own',
            'order:create'
        ];
    }
}
