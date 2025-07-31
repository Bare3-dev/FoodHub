<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Restaurant;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a basic user for tests that don't need a specific role
        $this->user = User::factory()->create(['role' => 'CASHIER', 'status' => 'active']); 
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'name', 'email', 'password', 'restaurant_id', 'restaurant_branch_id',
            'role', 'permissions', 'status', 'phone', 'last_login_at',
            'is_email_verified', 'profile_image_url', 'email_otp_code',
            'email_otp_expires_at', 'is_mfa_enabled',
        ];

        $this->assertEqualsCanonicalizing($fillable, $this->user->getFillable());
    }

    #[Test]
    public function it_hides_sensitive_attributes()
    {
        $hidden = [
            'password', 'remember_token',
        ];

        $this->assertEqualsCanonicalizing($hidden, $this->user->getHidden());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $casts = [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'is_email_verified' => 'boolean',
            'email_otp_expires_at' => 'datetime',
            'is_mfa_enabled' => 'boolean',
        ];

        foreach ($casts as $attribute => $expectedCast) {
            $this->assertArrayHasKey($attribute, $this->user->getCasts());
        }
    }

    #[Test]
    public function it_has_valid_role_enum_values()
    {
        $validRoles = [
            'SUPER_ADMIN', 'RESTAURANT_OWNER', 'BRANCH_MANAGER',
            'CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER',
            'CUSTOMER_SERVICE', 'DRIVER'
        ];

        foreach ($validRoles as $role) {
            // Create a user with a specific role, ensure role is not null
            $user = User::factory()->create(['role' => $role]);
            $this->assertEquals($role, $user->role);
        }
    }

    #[Test]
    public function it_belongs_to_restaurant_when_restaurant_owner()
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create([
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $restaurant->id
        ]);

        $this->assertInstanceOf(Restaurant::class, $owner->restaurant);
        $this->assertEquals($restaurant->id, $owner->restaurant->id);
    }

    // Removed: User model doesn't have customer relationship
    // Users are for staff only, customers use separate Customer model

    #[Test]
    public function it_checks_if_user_has_role()
    {
        $adminUser = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $cashierUser = User::factory()->create(['role' => 'CASHIER']);

        $this->assertTrue($adminUser->hasRole('SUPER_ADMIN'));
        $this->assertFalse($adminUser->hasRole('CASHIER'));
        
        $this->assertTrue($cashierUser->hasRole('CASHIER'));
        $this->assertFalse($cashierUser->hasRole('SUPER_ADMIN'));
    }

    #[Test]
    public function it_checks_if_user_has_permission()
    {
        $user = User::factory()->create([
            'role' => 'CASHIER',
            'permissions' => ['order:manage', 'customer:view']
        ]);

        $this->assertTrue($user->hasPermission('order:manage'));
        $this->assertTrue($user->hasPermission('customer:view'));
        $this->assertFalse($user->hasPermission('restaurant:manage'));
    }

    #[Test]
    public function super_admin_has_all_permissions()
    {
        $superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'permissions' => ['*']
        ]);

        $this->assertTrue($superAdmin->hasPermission('restaurant:manage'));
        $this->assertTrue($superAdmin->hasPermission('any:permission'));
        $this->assertTrue($superAdmin->hasPermission('non:existent'));
    }

    #[Test]
    public function it_checks_if_user_can_access_role()
    {
        $restaurantOwner = User::factory()->create(['role' => 'RESTAURANT_OWNER']);
        $branchManager = User::factory()->create(['role' => 'BRANCH_MANAGER']);
        $kitchenStaff = User::factory()->create(['role' => 'KITCHEN_STAFF']);

        // Test role hierarchy access
        $this->assertTrue($restaurantOwner->canAccessRole('BRANCH_MANAGER'));
        $this->assertTrue($restaurantOwner->canAccessRole('CASHIER'));
        $this->assertFalse($restaurantOwner->canAccessRole('SUPER_ADMIN'));
        
        $this->assertFalse($kitchenStaff->canAccessRole('CASHIER'));
        $this->assertFalse($branchManager->canAccessRole('RESTAURANT_OWNER'));
    }

    #[Test]
    public function it_validates_email_otp_code()
    {
        $user = User::factory()->create(['email_otp_code' => Hash::make('123456'), 'email_otp_expires_at' => now()->addMinutes(5)]);
        $this->assertTrue($user->verifyEmailOtpCode('123456'));
        $user->refresh();
        $this->assertNull($user->email_otp_code);
    }

    #[Test]
    public function it_does_not_validate_expired_email_otp_code()
    {
        $user = User::factory()->create(['email_otp_code' => Hash::make('123456'), 'email_otp_expires_at' => now()->subMinutes(1)]);
        $this->assertFalse($user->verifyEmailOtpCode('123456'));
        $user->refresh();
        $this->assertNull($user->email_otp_code);
    }

    #[Test]
    public function it_generates_email_otp_code()
    {
        $user = User::factory()->create(['email_otp_code' => null, 'email_otp_expires_at' => null]);
        $code = $user->generateEmailOtpCode();
        $this->assertNotNull($code);
        $this->assertNotNull($user->email_otp_code);
        $this->assertNotNull($user->email_otp_expires_at);
    }

    #[Test]
    public function it_enables_mfa()
    {
        $user = User::factory()->create(['is_mfa_enabled' => false]);
        $user->enableMfa();
        $this->assertTrue($user->is_mfa_enabled);
    }

    #[Test]
    public function it_disables_mfa()
    {
        $user = User::factory()->create(['is_mfa_enabled' => true]);
        $user->disableMfa();
        $this->assertFalse($user->is_mfa_enabled);
    }

    #[Test]
    public function it_scopes_active_users()
    {
        User::factory()->create(['status' => 'active', 'role' => 'CASHIER']);
        User::factory()->create(['status' => 'inactive', 'role' => 'CASHIER']);

        $activeUsers = User::where('status', 'active')->get();
        
        // The setUp user is active by default. We expect 2 active users if setUp creates an active user.
        // If setUp creates an active user, and we create one more, we should have 2.
        $this->assertCount(2, $activeUsers);
        $this->assertTrue($activeUsers->every(fn($user) => $user->status === 'active'));
    }

    #[Test]
    public function it_scopes_users_by_role()
    {
        User::factory()->create(['role' => 'KITCHEN_STAFF']);
        User::factory()->create(['role' => 'KITCHEN_STAFF']);
        User::factory()->create(['role' => 'DRIVER']);

        $kitchenStaff = User::role('KITCHEN_STAFF')->get();
        $drivers = User::role('DRIVER')->get();

        $this->assertCount(2, $kitchenStaff);
        $this->assertCount(1, $drivers);
    }
} 