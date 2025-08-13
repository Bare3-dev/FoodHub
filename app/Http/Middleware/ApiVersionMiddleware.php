<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\ApiVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ApiVersionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Extract version from URL path
        $version = $this->extractVersionFromPath($request->path());
        
        // Debug logging
        Log::info('ApiVersionMiddleware: Processing request', [
            'path' => $request->path(),
            'extracted_version' => $version
        ]);
        
        // If no version in path, try to get from Accept header
        if (!$version) {
            $version = $this->extractVersionFromHeader($request);
            Log::info('ApiVersionMiddleware: Version from header', ['version' => $version]);
        }
        
        // If still no version, default to current stable version
        if (!$version) {
            $version = $this->getDefaultVersion();
            Log::info('ApiVersionMiddleware: Using default version', ['version' => $version]);
        }
        
        // Check if the path contains a version pattern but it's invalid
        if (preg_match('/^api\/(v\d+)/', $request->path(), $matches)) {
            $extractedVersion = $matches[1];
            // Validate version exists and is supported
            $apiVersion = $this->validateVersion($extractedVersion);
            if (!$apiVersion) {
                Log::warning('ApiVersionMiddleware: Invalid version pattern', ['version' => $extractedVersion]);
                return $this->createVersionNotFoundResponse($extractedVersion);
            }
            $version = $extractedVersion;
        }
        
        // Validate version exists and is supported
        $apiVersion = $this->validateVersion($version);
        Log::info('ApiVersionMiddleware: Validated version', [
            'requested_version' => $version,
            'found_version' => $apiVersion ? $apiVersion->version : null,
            'status' => $apiVersion ? $apiVersion->status : null
        ]);
        
        if (!$apiVersion) {
            Log::warning('ApiVersionMiddleware: Version not found', ['version' => $version]);
            return $this->createVersionNotFoundResponse($version);
        }
        
        // Check if version is sunset
        if ($apiVersion->isSunset()) {
            Log::warning('ApiVersionMiddleware: Version is sunset', ['version' => $version]);
            return $this->createSunsetResponse($apiVersion);
        }
        
        // Store version info in request for other middleware/controllers
        $request->merge(['api_version' => $apiVersion]);
        $request->merge(['version' => $version]);
        
        // Log version usage for analytics
        $this->logVersionUsage($request, $apiVersion);
        
        // Continue with the request
        $response = $next($request);
        
        // Debug: Check response type and content
        Log::info('ApiVersionMiddleware: Response details', [
            'response_class' => get_class($response),
            'response_status' => $response->getStatusCode(),
            'response_headers_before' => $response->headers->all()
        ]);
        
        // Add version info to response headers
        $this->addVersionHeaders($response, $apiVersion);
        
        // Debug: Check headers after setting
        Log::info('ApiVersionMiddleware: Headers after setting', [
            'version' => $apiVersion->version,
            'status' => $apiVersion->status,
            'response_headers_after' => $response->headers->all()
        ]);
        
        return $response;
    }
    
    /**
     * Extract version from URL path
     */
    private function extractVersionFromPath(string $path): ?string
    {
        // Match patterns like /api/v1/restaurants, /api/v2/auth/login
        // Updated regex to be more flexible and handle edge cases
        if (preg_match('/^api\/(v\d+)(\/|$)/', $path, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Extract version from Accept header
     */
    private function extractVersionFromHeader(Request $request): ?string
    {
        $accept = $request->header('Accept');
        if (!$accept) {
            return null;
        }
        
        // Match patterns like application/vnd.foodhub.v1+json
        if (preg_match('/application\/vnd\.foodhub\.(v\d+)\+json/', $accept, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get default version from cache or database
     */
    private function getDefaultVersion(): string
    {
        return Cache::remember('api.default_version', 3600, function () {
            $default = ApiVersion::where('is_default', true)
                ->where('status', ApiVersion::STATUS_ACTIVE)
                ->first();
            
            return $default ? $default->version : 'v1';
        });
    }
    
    /**
     * Validate version exists and is supported
     */
    private function validateVersion(string $version): ?ApiVersion
    {
        return Cache::remember("api.version.{$version}", 3600, function () use ($version) {
            return ApiVersion::where('version', $version)->first();
        });
    }
    
    /**
     * Create response for version not found
     */
    private function createVersionNotFoundResponse(string $version)
    {
        $availableVersions = $this->getAvailableVersions();
        
        return response()->json([
            'error' => 'API version not found',
            'message' => "API version '{$version}' is not supported",
            'available_versions' => $availableVersions,
            'current_version' => $this->getDefaultVersion(),
            'migration_guide' => config('app.url') . '/api/docs/migration'
        ], 400)->header('Content-Type', 'application/json');
    }
    
    /**
     * Create response for sunset version
     */
    private function createSunsetResponse(ApiVersion $apiVersion)
    {
        $successor = $apiVersion->getSuccessorVersion();
        
        return response()->json([
            'error' => 'API version sunset',
            'message' => $apiVersion->getDeprecationWarning(),
            'sunset_date' => $apiVersion->sunset_date?->toISOString(),
            'successor_version' => $successor?->version,
            'migration_guide' => $apiVersion->getMigrationGuideUrl(),
            'support_contact' => 'api-support@foodhub.com'
        ], 410)->header('Content-Type', 'application/json');
    }
    
    /**
     * Get available API versions
     */
    private function getAvailableVersions(): array
    {
        return Cache::remember('api.available_versions', 3600, function () {
            return ApiVersion::where('status', ApiVersion::STATUS_ACTIVE)
                ->select('version', 'release_date', 'is_default')
                ->orderBy('release_date')
                ->get()
                ->toArray();
        });
    }
    
    /**
     * Log version usage for analytics
     */
    private function logVersionUsage(Request $request, ApiVersion $apiVersion): void
    {
        $analyticsData = [
            'version' => $apiVersion->version,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'restaurant_id' => $this->extractRestaurantId($request),
            'timestamp' => now()->toISOString()
        ];
        
        // Store in cache for batch processing
        $key = 'api.analytics.' . date('Y-m-d-H');
        $analytics = Cache::get($key, []);
        $analytics[] = $analyticsData;
        Cache::put($key, $analytics, 3600);
        
        // Log for debugging
        Log::info('API version usage', $analyticsData);
    }
    
    /**
     * Extract restaurant ID from request if available
     */
    private function extractRestaurantId(Request $request): ?int
    {
        // Try to get from route parameters
        $restaurant = $request->route('restaurant');
        if ($restaurant) {
            return is_numeric($restaurant) ? (int) $restaurant : $restaurant->id ?? null;
        }
        
        // Try to get from query parameters
        return $request->query('restaurant_id');
    }
    
    /**
     * Add version information to response headers
     */
    private function addVersionHeaders($response, ApiVersion $apiVersion): void
    {
        $response->headers->set('X-API-Version', $apiVersion->version);
        $response->headers->set('X-API-Version-Status', $apiVersion->status);
        
        // Add migration guide header - handle database failures gracefully
        try {
            $migrationGuide = $apiVersion->getMigrationGuideUrl() ?: config('app.url') . '/api/docs/migration';
            $response->headers->set('X-API-Migration-Guide', $migrationGuide);
        } catch (\Exception $e) {
            Log::warning('Failed to get migration guide URL', ['error' => $e->getMessage()]);
            $response->headers->set('X-API-Migration-Guide', config('app.url') . '/api/docs/migration');
        }
        
        if ($apiVersion->release_date) {
            $response->headers->set('X-API-Version-Release-Date', $apiVersion->release_date->toISOString());
        }
        
        if ($apiVersion->sunset_date) {
            $response->headers->set('X-API-Version-Sunset-Date', $apiVersion->sunset_date->toISOString());
        }
        
        // Add deprecation warnings if applicable
        if ($apiVersion->isDeprecated()) {
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', $apiVersion->sunset_date?->toRFC7231() ?? 'Unknown');
            
            try {
                $successor = $apiVersion->getSuccessorVersion();
                if ($successor) {
                    $response->headers->set('Link', "<{$successor->version}>; rel=\"successor-version\"");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get successor version', ['error' => $e->getMessage()]);
            }
        }
    }
}
