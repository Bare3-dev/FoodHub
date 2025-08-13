@extends('api.docs.layout')

@section('title', 'API Migration Guide - FoodHub API')

@section('sidebar')
    <div class="space-y-6">
        <div>
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Migration Overview</h3>
            <ul class="mt-2 space-y-1">
                <li><a href="#overview" class="text-gray-600 hover:text-gray-900 text-sm">Overview</a></li>
                <li><a href="#timeline" class="text-gray-600 hover:text-gray-900 text-sm">Timeline</a></li>
                <li><a href="#breaking-changes" class="text-gray-600 hover:text-gray-900 text-sm">Breaking Changes</a></li>
                <li><a href="#migration-steps" class="text-gray-600 hover:text-gray-900 text-sm">Migration Steps</a></li>
            </ul>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Version Migration</h3>
            <ul class="mt-2 space-y-1">
                <li><a href="#v1-to-v2" class="text-gray-600 hover:text-gray-900 text-sm">v1 → v2</a></li>
                <li><a href="#legacy-to-v1" class="text-gray-600 hover:text-gray-900 text-sm">Legacy → v1</a></li>
                <li><a href="#v2-to-future" class="text-gray-600 hover:text-gray-900 text-sm">v2 → Future</a></li>
            </ul>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Resources</h3>
            <ul class="mt-2 space-y-1">
                <li><a href="#code-examples" class="text-gray-600 hover:text-gray-900 text-sm">Code Examples</a></li>
                <li><a href="#testing" class="text-gray-600 hover:text-gray-900 text-sm">Testing Guide</a></li>
                <li><a href="#support" class="text-gray-600 hover:text-gray-900 text-sm">Support</a></li>
            </ul>
        </div>
    </div>
@endsection

@section('content')
    <div class="max-w-4xl">
        <!-- Overview -->
        <section id="overview" class="mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-6">API Migration Guide</h1>
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Important:</strong> This guide helps you migrate between API versions. 
                            Follow the timeline to ensure smooth transitions and avoid service disruptions.
                        </p>
                    </div>
                </div>
            </div>

            <p class="text-lg text-gray-600 mb-4">
                The FoodHub API is designed with backward compatibility in mind. This migration guide 
                provides step-by-step instructions for upgrading your integrations to newer API versions 
                while maintaining functionality.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Backward Compatible</h3>
                    <p class="text-gray-600 text-sm">v1 endpoints remain active for 12+ months after v2 release</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Gradual Migration</h3>
                    <p class="text-gray-600 text-sm">Migrate endpoints incrementally based on your timeline</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Comprehensive Support</h3>
                    <p class="text-gray-600 text-sm">Detailed examples and migration assistance available</p>
                </div>
            </div>
        </section>

        <!-- Timeline -->
        <section id="timeline" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Migration Timeline</h2>
            
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">Phase 1: v2 Beta Release</h3>
                        <span class="version-badge v2">Q2 2024</span>
                    </div>
                    <p class="text-gray-600 mb-4">
                        v2 endpoints become available for testing and early adoption. v1 remains the 
                        stable production version.
                    </p>
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                        <li>v2 endpoints available for testing</li>
                        <li>Migration guides and examples published</li>
                        <li>Early adopter support program</li>
                    </ul>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">Phase 2: v2 Stable Release</h3>
                        <span class="version-badge v2">Q3 2024</span>
                    </div>
                    <p class="text-gray-600 mb-4">
                        v2 becomes the new stable version. v1 enters deprecation phase with 6-month notice.
                    </p>
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                        <li>v2 becomes production-ready</li>
                        <li>v1 deprecation warnings begin</li>
                        <li>6-month migration period starts</li>
                    </ul>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">Phase 3: v1 Sunset</h3>
                        <span class="version-badge deprecated">Q1 2025</span>
                    </div>
                    <p class="text-gray-600 mb-4">
                        v1 endpoints are sunset after 6 months of deprecation warnings. v2 is the 
                        primary production version.
                    </p>
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                        <li>v1 endpoints return 410 Gone</li>
                        <li>v2 is the only active version</li>
                        <li>Legacy support for critical endpoints</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Breaking Changes -->
        <section id="breaking-changes" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Breaking Changes</h2>
            
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-red-900 mb-2">What Are Breaking Changes?</h3>
                <p class="text-red-800 text-sm">
                    Breaking changes are modifications that require updates to your code to maintain 
                    functionality. These include changes to request/response formats, endpoint URLs, 
                    or authentication methods.
                </p>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">v1 → v2 Breaking Changes</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Authentication Changes</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1 ml-4">
                                <li>MFA requirement for admin endpoints</li>
                                <li>Enhanced role-based permissions</li>
                                <li>Session timeout changes</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Response Format Changes</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1 ml-4">
                                <li>Standardized error response format</li>
                                <li>Additional metadata fields</li>
                                <li>Pagination structure updates</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Endpoint Changes</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1 ml-4">
                                <li>New required parameters</li>
                                <li>Deprecated query parameters</li>
                                <li>Rate limiting adjustments</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Migration Steps -->
        <section id="migration-steps" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Migration Steps</h2>
            
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Step-by-Step Migration Process</h3>
                    
                    <ol class="list-decimal list-inside space-y-4 text-sm text-gray-600">
                        <li class="flex">
                            <span class="font-medium text-gray-900 mr-2">1.</span>
                            <div>
                                <strong>Assess Current Usage:</strong> Review your current API usage and identify 
                                all endpoints that need migration.
                            </div>
                        </li>
                        <li class="flex">
                            <span class="font-medium text-gray-900 mr-2">2.</span>
                            <div>
                                <strong>Set Up v2 Environment:</strong> Create a separate environment for testing 
                                v2 endpoints without affecting production.
                            </div>
                        </li>
                        <li class="flex">
                            <span class="font-medium text-gray-900 mr-2">3.</span>
                            <div>
                                <strong>Update Authentication:</strong> Implement new authentication requirements 
                                including MFA for admin endpoints.
                            </div>
                        </li>
                        <li class="flex">
                            <span class="font-medium text-gray-900 mr-2">4.</span>
                            <div>
                                <strong>Test Endpoints:</strong> Test each endpoint with v2 to ensure compatibility 
                                and identify any issues.
                            </div>
                        </li>
                        <li class="flex">
                            <span class="font-medium text-gray-900 mr-2">5.</span>
                            <div>
                                <strong>Update Code:</strong> Modify your application code to handle new response 
                                formats and required parameters.
                            </div>
                        </li>
                        <li class="flex">
                            <span class="font-medium text-gray-900 mr-2">6.</span>
                            <div>
                                <strong>Deploy Gradually:</strong> Deploy changes incrementally, starting with 
                                non-critical endpoints.
                            </div>
                        </li>
                        <li class="flex">
                            <span class="font-medium text-gray-900 mr-2">7.</span>
                            <div>
                                <strong>Monitor & Validate:</strong> Monitor performance and validate that all 
                                functionality works as expected.
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <!-- v1 to v2 Migration -->
        <section id="v1-to-v2" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">v1 → v2 Migration Guide</h2>
            
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Authentication Updates</h3>
                    
                    <div class="bg-gray-900 rounded-lg p-4 mb-4">
                        <h4 class="text-white font-semibold mb-2">v1 Authentication</h4>
                        <pre class="text-green-400 text-sm"><code>POST /api/v1/auth/login
{
    "email": "user@example.com",
    "password": "password"
}</code></pre>
                    </div>

                    <div class="bg-gray-900 rounded-lg p-4">
                        <h4 class="text-white font-semibold mb-2">v2 Authentication (with MFA)</h4>
                        <pre class="text-green-400 text-sm"><code>POST /api/v2/auth/login
{
    "email": "user@example.com",
    "password": "password",
    "mfa_code": "123456"
}</code></pre>
                    </div>

                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                        <p class="text-sm text-blue-800">
                            <strong>Note:</strong> v2 requires MFA for all admin and sensitive operations. 
                            Ensure your users have MFA enabled before migration.
                        </p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Response Format Updates</h3>
                    
                    <div class="bg-gray-900 rounded-lg p-4 mb-4">
                        <h4 class="text-white font-semibold mb-2">v1 Response</h4>
                        <pre class="text-green-400 text-sm"><code>{
    "id": 1,
    "name": "Restaurant Name",
    "address": "123 Main St"
}</code></pre>
                    </div>

                    <div class="bg-gray-900 rounded-lg p-4">
                        <h4 class="text-white font-semibold mb-2">v2 Response (with metadata)</h4>
                        <pre class="text-green-400 text-sm"><code>{
    "data": {
        "id": 1,
        "name": "Restaurant Name",
        "address": "123 Main St"
    },
    "meta": {
        "version": "v2",
        "timestamp": "2024-01-20T10:00:00Z",
        "request_id": "req_123456"
    }
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Code Examples -->
        <section id="code-examples" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Code Examples</h2>
            
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">JavaScript/Node.js Migration</h3>
                    
                    <div class="bg-gray-900 rounded-lg p-4 mb-4">
                        <h4 class="text-white font-semibold mb-2">v1 Implementation</h4>
                        <pre class="text-green-400 text-sm"><code>// v1 API call
const response = await fetch('/api/v1/restaurants', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
    }
});

const restaurants = await response.json();</code></pre>
                    </div>

                    <div class="bg-gray-900 rounded-lg p-4">
                        <h4 class="text-white font-semibold mb-2">v2 Implementation</h4>
                        <pre class="text-green-400 text-sm"><code>// v2 API call with updated response handling
const response = await fetch('/api/v2/restaurants', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/vnd.foodhub.v2+json'
    }
});

const result = await response.json();
const restaurants = result.data; // Note: data is now nested
const metadata = result.meta;</code></pre>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Python Migration</h3>
                    
                    <div class="bg-gray-900 rounded-lg p-4 mb-4">
                        <h4 class="text-white font-semibold mb-2">v1 Implementation</h4>
                        <pre class="text-green-400 text-sm"><code># v1 API call
import requests

response = requests.get(
    '/api/v1/restaurants',
    headers={
        'Authorization': f'Bearer {token}',
        'Accept': 'application/json'
    }
)

restaurants = response.json()</code></pre>
                    </div>

                    <div class="bg-gray-900 rounded-lg p-4">
                        <h4 class="text-white font-semibold mb-2">v2 Implementation</h4>
                        <pre class="text-green-400 text-sm"><code># v2 API call with updated response handling
import requests

response = requests.get(
    '/api/v2/restaurants',
    headers={
        'Authorization': f'Bearer {token}',
        'Accept': 'application/vnd.foodhub.v2+json'
    }
)

result = response.json()
restaurants = result['data']  # Note: data is now nested
metadata = result['meta']</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testing Guide -->
        <section id="testing" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Testing Guide</h2>
            
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Testing Strategy</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Unit Testing</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                                <li>Test individual endpoint responses</li>
                                <li>Validate new response formats</li>
                                <li>Check error handling</li>
                                <li>Verify authentication flows</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Integration Testing</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                                <li>Test complete user workflows</li>
                                <li>Validate data consistency</li>
                                <li>Check performance impact</li>
                                <li>Test backward compatibility</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Testing Checklist</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <input type="checkbox" class="mt-1 mr-3" id="test-auth">
                            <label for="test-auth" class="text-sm text-gray-700">Authentication and authorization</label>
                        </div>
                        <div class="flex items-start">
                            <input type="checkbox" class="mt-1 mr-3" id="test-endpoints">
                            <label for="test-endpoints" class="text-sm text-gray-700">All critical endpoints</label>
                        </div>
                        <div class="flex items-start">
                            <input type="checkbox" class="mt-1 mr-3" id="test-errors">
                            <label for="test-errors" class="text-sm text-gray-700">Error handling and validation</label>
                        </div>
                        <div class="flex items-start">
                            <input type="checkbox" class="mt-1 mr-3" id="test-performance">
                            <label for="test-performance" class="text-sm text-gray-700">Performance and response times</label>
                        </div>
                        <div class="flex items-start">
                            <input type="checkbox" class="mt-1 mr-3" id="test-compatibility">
                            <label for="test-compatibility" class="text-sm text-gray-700">Backward compatibility</label>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Support -->
        <section id="support" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Support & Resources</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Migration Support</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><strong>Email:</strong> <a href="mailto:migration@foodhub.com" class="text-blue-600 hover:text-blue-800">migration@foodhub.com</a></li>
                        <li><strong>Phone:</strong> +1 (555) 123-4567</li>
                        <li><strong>Hours:</strong> Mon-Fri 9AM-6PM EST</li>
                        <li><strong>Priority:</strong> Available for enterprise customers</li>
                    </ul>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Additional Resources</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><a href="#" class="text-blue-600 hover:text-blue-800">Migration Webinar Recording</a></li>
                        <li><a href="#" class="text-blue-600 hover:text-blue-800">FAQ & Troubleshooting</a></li>
                        <li><a href="#" class="text-blue-600 hover:text-blue-800">Community Forum</a></li>
                        <li><a href="#" class="text-blue-600 hover:text-blue-800">Code Samples Repository</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-900 mb-3">Need Immediate Help?</h3>
                <p class="text-blue-800 text-sm mb-4">
                    For urgent migration issues or production problems, contact our 24/7 support team.
                </p>
                <div class="flex space-x-4">
                    <a href="mailto:urgent@foodhub.com" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                        Urgent Support
                    </a>
                    <a href="#" class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50">
                        Schedule Call
                    </a>
                </div>
            </div>
        </section>
    </div>
@endsection
