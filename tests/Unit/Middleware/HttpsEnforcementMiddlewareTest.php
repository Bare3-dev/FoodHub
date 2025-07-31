<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\HttpsEnforcementMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HttpsEnforcementMiddlewareTest extends TestCase
{
    private HttpsEnforcementMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new HttpsEnforcementMiddleware();
    }

    #[Test]
    public function adds_security_headers_to_response()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($response->headers->get('Strict-Transport-Security'));
        $this->assertNotNull($response->headers->get('X-Frame-Options'));
        $this->assertNotNull($response->headers->get('X-XSS-Protection'));
        $this->assertNotNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function sets_correct_strict_transport_security_header()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $hstsHeader = $response->headers->get('Strict-Transport-Security');
        $this->assertStringContainsString('max-age=31536000', $hstsHeader);
        $this->assertStringContainsString('includeSubDomains', $hstsHeader);
        $this->assertStringContainsString('preload', $hstsHeader);
    }

    #[Test]
    public function sets_correct_x_frame_options_header()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
    }

    #[Test]
    public function sets_correct_xss_protection_header()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
    }

    #[Test]
    public function sets_correct_content_type_options_header()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function sets_correct_referrer_policy_header()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    #[Test]
    public function sets_correct_permissions_policy_header()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=()', $permissionsPolicy);
        $this->assertStringContainsString('microphone=()', $permissionsPolicy);
        $this->assertStringContainsString('geolocation=()', $permissionsPolicy);
        $this->assertStringContainsString('payment=()', $permissionsPolicy);
    }

    #[Test]
    public function sets_comprehensive_content_security_policy()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'none'", $csp);
        $this->assertStringContainsString("style-src 'none'", $csp);
        $this->assertStringContainsString("img-src 'none'", $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
    }

    #[Test]
    public function sets_custom_server_headers()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals('FoodHub-API', $response->headers->get('X-Powered-By'));
        $this->assertEquals('FoodHub-Secure', $response->headers->get('Server'));
    }

    #[Test]
    public function sets_cache_control_headers_when_not_already_set()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
        $this->assertEquals('0', $response->headers->get('Expires'));
    }

    #[Test]
    public function does_not_override_existing_cache_headers()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, function($req) {
            $response = new Response('Test content', 200);
            $response->headers->set('Cache-Control', 'public, max-age=300');
            $response->headers->set('Pragma', 'cache');
            $response->headers->set('Expires', '3600');
            return $response;
        });
        
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertEquals('cache', $response->headers->get('Pragma'));
        $this->assertEquals('3600', $response->headers->get('Expires'));
    }

    #[Test]
    public function does_not_enforce_https_when_disabled_in_config()
    {
        $this->app['config']->set('app.env', 'production');
        $this->app['config']->set('app.force_https', false);
        
        $request = Request::create('http://localhost/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Test content', $response->getContent());
    }

    #[Test]
    public function enforces_https_in_testing_when_forced()
    {
        $this->app['config']->set('app.env', 'testing');
        $this->app['config']->set('app.force_https_in_testing', true);
        $this->app['config']->set('app.force_https', true);
        
        $request = Request::create('http://localhost/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertStringContainsString('https://localhost/api/test', $response->headers->get('Location'));
    }
} 