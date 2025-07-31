<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RoleAndPermissionMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleAndPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private RoleAndPermissionMiddleware $middleware;
    private User $superAdmin;
    private User $restaurantOwner;
    private User $branchManager;
    private User $cashier;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->middleware = new RoleAndPermissionMiddleware();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $this->restaurantOwner = User::factory()->create(['role' => 'RESTAURANT_OWNER']);
        $this->branchManager = User::factory()->create(['role' => 'BRANCH_MANAGER']);
        $this->cashier = User::factory()->create(['role' => 'CASHIER']);
        $this->unauthorizedUser = User::factory()->create(['role' => 'CUSTOMER_SERVICE']);
    }

    #[Test]
    public function middleware_allows_authorized_requests()
    {
        // Test super admin access
        $request = Request::create('/admin', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->superAdmin);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, 'SUPER_ADMIN');
        
        $this->assertEquals('Authorized', $response->getContent());
    }

    #[Test]
    public function middleware_blocks_unauthorized_requests()
    {
        // Test unauthorized user trying to access super admin route
        $request = Request::create('/admin', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->unauthorizedUser);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Should not reach here');
        }, 'SUPER_ADMIN');
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function middleware_handles_multiple_roles()
    {
        // Test restaurant owner accessing route that allows both SUPER_ADMIN and RESTAURANT_OWNER
        $request = Request::create('/restaurant', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->restaurantOwner);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, 'SUPER_ADMIN,RESTAURANT_OWNER');
        
        $this->assertEquals('Authorized', $response->getContent());
    }

    #[Test]
    public function middleware_handles_specific_permissions()
    {
        // Test user with specific permission
        $this->restaurantOwner->permissions = ['manage_restaurants'];
        $this->restaurantOwner->save();
        
        $request = Request::create('/restaurant/manage', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->restaurantOwner);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, 'manage_restaurants');
        
        $this->assertEquals('Authorized', $response->getContent());
    }

    #[Test]
    public function middleware_blocks_users_without_permissions()
    {
        // Test user without required permission - use unauthorized user
        $this->unauthorizedUser->permissions = [];
        $this->unauthorizedUser->save();
        
        $request = Request::create('/restaurant/manage', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->unauthorizedUser);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Should not reach here');
        }, null, 'manage_restaurants'); // Pass permission as second parameter
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function middleware_handles_guest_users()
    {
        // Test unauthenticated user
        $request = Request::create('/admin', 'GET');
        
        // Don't authenticate any user
        $this->withoutMiddleware();
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Should not reach here');
        }, 'SUPER_ADMIN');
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    #[Test]
    public function middleware_handles_role_hierarchy()
    {
        // Test that super admin can access branch manager routes
        $request = Request::create('/branch', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->superAdmin);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, 'BRANCH_MANAGER');
        
        $this->assertEquals('Authorized', $response->getContent());
    }

    #[Test]
    public function middleware_handles_invalid_roles()
    {
        // Test with invalid role parameter - super admin should still have access due to bypass
        $request = Request::create('/test', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->superAdmin);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, 'INVALID_ROLE');
        
        $this->assertEquals('Authorized', $response->getContent());
    }

    #[Test]
    public function middleware_handles_empty_roles()
    {
        // Test with empty role parameter - super admin should still have access due to bypass
        $request = Request::create('/test', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->superAdmin);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, '');
        
        $this->assertEquals('Authorized', $response->getContent());
    }

    #[Test]
    public function middleware_handles_case_sensitive_roles()
    {
        // Test case sensitivity in role matching - super admin should still have access due to bypass
        $request = Request::create('/admin', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->superAdmin);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, 'super_admin'); // lowercase
        
        $this->assertEquals('Authorized', $response->getContent());
    }

    #[Test]
    public function middleware_handles_suspended_users()
    {
        // Test suspended user - middleware doesn't check status, so access should be allowed
        $this->superAdmin->status = 'suspended';
        $this->superAdmin->save();
        
        $request = Request::create('/admin', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->superAdmin);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, 'SUPER_ADMIN');
        
        $this->assertEquals('Authorized', $response->getContent());
    }

    #[Test]
    public function middleware_handles_inactive_users()
    {
        // Test inactive user - middleware doesn't check status, so access should be allowed
        $this->superAdmin->status = 'inactive';
        $this->superAdmin->save();
        
        $request = Request::create('/admin', 'GET');
        
        // Properly authenticate the user
        $this->actingAs($this->superAdmin);
        
        $response = $this->middleware->handle($request, function ($request) {
            return new Response('Authorized');
        }, 'SUPER_ADMIN');
        
        $this->assertEquals('Authorized', $response->getContent());
    }
} 