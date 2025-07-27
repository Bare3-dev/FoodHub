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
}
