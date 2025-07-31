<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $cacheType = 'default'): Response
    {
        \Log::info("CacheHeadersMiddleware executing with type: " . $cacheType);

        $response = $next($request);

        // Only add cache headers for successful GET requests
        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            $this->addCacheHeaders($response, $cacheType);
            \Log::info("Cache headers set: " . $response->headers->get('Cache-Control'));
        }

        return $response;
    }

    /**
     * Add appropriate cache headers based on the cache type
     */
    private function addCacheHeaders(Response $response, string $cacheType): void
    {
        $cacheConfig = $this->getCacheConfig($cacheType);
        
        // Force override any existing cache headers
        $response->headers->remove('Cache-Control');
        $response->headers->remove('ETag');
        $response->headers->remove('Last-Modified');
        $response->headers->remove('Expires');
        $response->headers->remove('Pragma');
        
        $response->headers->set('Cache-Control', $cacheConfig['cache_control']);
        $response->headers->set('ETag', $this->generateETag($response));
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
        
        if (isset($cacheConfig['expires'])) {
            $response->headers->set('Expires', $cacheConfig['expires']);
        }
    }

    /**
     * Get cache configuration based on type
     */
    private function getCacheConfig(string $cacheType): array
    {
        $configs = [
            'restaurants' => [
                'cache_control' => 'public, max-age=300, s-maxage=300', // 5 minutes
                'expires' => gmdate('D, d M Y H:i:s', time() + 300) . ' GMT',
            ],
            'menu_items' => [
                'cache_control' => 'public, max-age=300, s-maxage=300', // 5 minutes
                'expires' => gmdate('D, d M Y H:i:s', time() + 300) . ' GMT',
            ],
            'menu_categories' => [
                'cache_control' => 'public, max-age=1800, s-maxage=1800', // 30 minutes
                'expires' => gmdate('D, d M Y H:i:s', time() + 1800) . ' GMT',
            ],
            'restaurant_branches' => [
                'cache_control' => 'public, max-age=1800, s-maxage=1800', // 30 minutes
                'expires' => gmdate('D, d M Y H:i:s', time() + 1800) . ' GMT',
            ],
            'default' => [
                'cache_control' => 'public, max-age=600, s-maxage=600', // 10 minutes
                'expires' => gmdate('D, d M Y H:i:s', time() + 600) . ' GMT',
            ],
        ];

        return $configs[$cacheType] ?? $configs['default'];
    }

    /**
     * Generate ETag for the response
     */
    private function generateETag(Response $response): string
    {
        $content = $response->getContent();
        return '"' . md5($content) . '"';
    }
} 