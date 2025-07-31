<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CacheHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CacheHeadersMiddlewareTest extends TestCase
{
    private CacheHeadersMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CacheHeadersMiddleware();
    }

    #[Test]
    public function sets_cache_headers_for_get_requests()
    {
        $request = Request::create('/api/restaurants', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($response->headers->get('Cache-Control'));
        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=600', $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function does_not_set_cache_headers_for_post_requests()
    {
        $request = Request::create('/api/restaurants', 'POST');
        $response = $this->middleware->handle($request, fn($req) => new Response('Test content', 200));
        
        $this->assertEquals(200, $response->getStatusCode());
        // Laravel may add default cache headers, so we check that our specific headers are not set
        $this->assertNull($response->headers->get('ETag'));
    }

    #[Test]
    public function does_not_set_cache_headers_for_error_responses()
    {
        $request = Request::create('/api/restaurants', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Error content', 404));
        
        $this->assertEquals(404, $response->getStatusCode());
        // Laravel may add default cache headers, so we check that our specific headers are not set
        $this->assertNull($response->headers->get('ETag'));
    }

    #[Test]
    public function sets_restaurants_cache_headers()
    {
        $request = Request::create('/api/restaurants', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Restaurants content', 200), 'restaurants');
        
        $this->assertEquals(200, $response->getStatusCode());
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertStringContainsString('s-maxage=300', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
    }

    #[Test]
    public function sets_menu_items_cache_headers()
    {
        $request = Request::create('/api/menu-items', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Menu items content', 200), 'menu_items');
        
        $this->assertEquals(200, $response->getStatusCode());
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertStringContainsString('s-maxage=300', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
    }

    #[Test]
    public function sets_menu_categories_cache_headers()
    {
        $request = Request::create('/api/menu-categories', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Categories content', 200), 'menu_categories');
        
        $this->assertEquals(200, $response->getStatusCode());
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=1800', $cacheControl);
        $this->assertStringContainsString('s-maxage=1800', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
    }

    #[Test]
    public function sets_restaurant_branches_cache_headers()
    {
        $request = Request::create('/api/restaurant-branches', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Branches content', 200), 'restaurant_branches');
        
        $this->assertEquals(200, $response->getStatusCode());
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=1800', $cacheControl);
        $this->assertStringContainsString('s-maxage=1800', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
    }

    #[Test]
    public function uses_default_cache_headers_for_unknown_type()
    {
        $request = Request::create('/api/unknown', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('Unknown content', 200), 'unknown_type');
        
        $this->assertEquals(200, $response->getStatusCode());
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=600', $cacheControl);
        $this->assertStringContainsString('s-maxage=600', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
    }

    #[Test]
    public function generates_correct_etag_based_on_content()
    {
        $content = 'Test content for ETag';
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response($content, 200));
        
        $expectedETag = '"' . md5($content) . '"';
        $this->assertEquals($expectedETag, $response->headers->get('ETag'));
    }

    #[Test]
    public function removes_existing_cache_headers_before_setting_new_ones()
    {
        $request = Request::create('/api/test', 'GET');
        $response = $this->middleware->handle($request, function($req) {
            $response = new Response('Test content', 200);
            $response->headers->set('Cache-Control', 'no-cache');
            $response->headers->set('ETag', 'old-etag');
            $response->headers->set('Last-Modified', 'old-date');
            $response->headers->set('Expires', 'old-expires');
            $response->headers->set('Pragma', 'old-pragma');
            return $response;
        });
        
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=600', $cacheControl);
        $this->assertNotEquals('old-etag', $response->headers->get('ETag'));
        $this->assertNotNull($response->headers->get('Last-Modified'));
        $this->assertNotEquals('old-date', $response->headers->get('Last-Modified'));
        $this->assertNotNull($response->headers->get('Expires'));
        $this->assertNotEquals('old-expires', $response->headers->get('Expires'));
        $this->assertNull($response->headers->get('Pragma'));
    }

    #[Test]
    public function handles_empty_response_content()
    {
        $request = Request::create('/api/empty', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('', 200));
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($response->headers->get('Cache-Control'));
        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertEquals('"' . md5('') . '"', $response->headers->get('ETag'));
    }
} 