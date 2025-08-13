<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiVersion;
use Illuminate\Support\Facades\Log;

class VersionDeprecationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Get the API version from the request
        $version = $request->get('version');
        
        if ($version && $this->isDeprecated($version)) {
            $this->addDeprecationHeaders($response, $version);
        }
        
        return $response;
    }
    
    /**
     * Check if a version is deprecated
     */
    private function isDeprecated(string $version): bool
    {
        $apiVersion = ApiVersion::where('version', $version)->first();
        
        if (!$apiVersion) {
            return false;
        }
        
        return $apiVersion->isDeprecated() || $apiVersion->isSunset();
    }
    
    /**
     * Get sunset date for a version
     */
    private function getSunsetDate(string $version): ?string
    {
        $apiVersion = ApiVersion::where('version', $version)->first();
        
        if (!$apiVersion || !$apiVersion->sunset_date) {
            return null;
        }
        
        return $apiVersion->sunset_date->toRFC7231();
    }
    
    /**
     * Get successor version link
     */
    private function getSuccessorVersionLink(string $version): ?string
    {
        $apiVersion = ApiVersion::where('version', $version)->first();
        
        if (!$apiVersion) {
            return null;
        }
        
        $successor = $apiVersion->getSuccessorVersion();
        
        if (!$successor) {
            return null;
        }
        
        return config('app.url') . '/api/' . $successor->version;
    }
    
    /**
     * Add deprecation headers to response
     */
    private function addDeprecationHeaders($response, string $version): void
    {
        $sunsetDate = $this->getSunsetDate($version);
        $successorLink = $this->getSuccessorVersionLink($version);
        
        if ($sunsetDate) {
            $response->headers->set('Sunset', $sunsetDate);
        }
        
        $response->headers->set('Deprecation', 'true');
        
        if ($successorLink) {
            $response->headers->set('Link', "<{$successorLink}>; rel=\"successor-version\"");
        }
        
        // Add custom deprecation warning header
        $response->headers->set('X-API-Deprecation-Warning', 
            "API version {$version} is deprecated. Please migrate to the latest version.");
        
        Log::info('Deprecation warning added to response', [
            'version' => $version,
            'sunset_date' => $sunsetDate,
            'successor_link' => $successorLink
        ]);
    }
}
