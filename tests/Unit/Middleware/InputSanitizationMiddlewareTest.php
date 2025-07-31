<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\InputSanitizationMiddleware;
use App\Services\SecurityLoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InputSanitizationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private InputSanitizationMiddleware $middleware;
    private $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = $this->createMock(SecurityLoggingService::class);
        $this->loggerMock->method('logAttackAttempt')->willReturnCallback(function() {});
        $this->middleware = new InputSanitizationMiddleware($this->loggerMock);
    }

    #[Test]
    public function allows_clean_input()
    {
        $request = Request::create('/api/resource', 'POST', ['name' => 'John Doe', 'email' => 'john@example.com']);
        $response = $this->middleware->handle($request, fn($req) => new Response('OK'));
        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function blocks_sql_injection()
    {
        $request = Request::create('/api/resource', 'POST', ['username' => "admin' OR 1=1 --"]);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('security', strtolower($response->getContent()));
    }

    #[Test]
    public function blocks_xss()
    {
        $request = Request::create('/api/resource', 'POST', ['comment' => '<script>alert(1)</script>']);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('security', strtolower($response->getContent()));
    }

    #[Test]
    public function blocks_path_traversal()
    {
        $request = Request::create('/api/resource', 'POST', ['file' => '../../etc/passwd']);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('security', strtolower($response->getContent()));
    }

    #[Test]
    public function blocks_command_injection()
    {
        $request = Request::create('/api/resource', 'POST', ['cmd' => 'ls; rm -rf /']);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('security', strtolower($response->getContent()));
    }

    #[Test]
    public function blocks_nosql_injection()
    {
        $request = Request::create('/api/resource', 'POST', ['query' => '{ "$where": "this.value == 1" }']);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('security', strtolower($response->getContent()));
    }

    #[Test]
    public function blocks_suspicious_file_extension()
    {
        $request = Request::create('/api/resource', 'POST', ['filename' => 'malware.php']);
        $response = $this->middleware->handle($request, fn($req) => new Response('Should not reach here'));
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('security', strtolower($response->getContent()));
    }

    #[Test]
    public function allows_safe_routes()
    {
        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, fn($req) => new Response('OK'));
        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function sanitizes_html_input()
    {
        $request = Request::create('/api/resource', 'POST', ['bio' => '<b>bold</b>']);
        $this->middleware->handle($request, function ($req) {
            $this->assertEquals('&lt;b&gt;bold&lt;/b&gt;', $req->input('bio'));
            return new Response('OK');
        });
    }
} 