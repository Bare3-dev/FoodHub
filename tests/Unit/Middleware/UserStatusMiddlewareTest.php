<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\UserStatusMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserStatusMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private UserStatusMiddleware $middleware;
    private User $activeUser;
    private User $inactiveUser;
    private User $suspendedUser;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new UserStatusMiddleware();
        $this->activeUser = User::factory()->create(['status' => 'active']);
        $this->inactiveUser = User::factory()->create(['status' => 'inactive']);
        $this->suspendedUser = User::factory()->create(['status' => 'suspended']);
        $this->superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'active']);
    }

    #[Test]
    public function allows_active_user()
    {
        $request = Request::create('/test', 'GET');
        $this->actingAs($this->activeUser);
        $response = $this->middleware->handle($request, fn($req) => new Response('OK'));
        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function blocks_inactive_user()
    {
        $request = Request::create('/test', 'GET');
        $this->actingAs($this->inactiveUser);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('inactive', $response->getContent());
    }

    #[Test]
    public function blocks_suspended_user()
    {
        $request = Request::create('/test', 'GET');
        $this->actingAs($this->suspendedUser);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('inactive', $response->getContent());
    }

    #[Test]
    public function blocks_guest_user()
    {
        $request = Request::create('/test', 'GET');
        $this->withoutMiddleware();
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unauthenticated', $response->getContent());
    }

    #[Test]
    public function blocks_super_admin_if_not_active()
    {
        $superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'suspended']);
        $request = Request::create('/test', 'GET');
        $this->actingAs($superAdmin);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('inactive', $response->getContent());
    }
} 