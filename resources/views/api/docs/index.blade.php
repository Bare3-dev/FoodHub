@extends('api.docs.layout')

@section('title', 'FoodHub API Documentation')

@section('sidebar')
    <div class="space-y-6">
        <div>
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Getting Started</h3>
            <ul class="mt-2 space-y-1">
                <li><a href="#introduction" class="text-gray-600 hover:text-gray-900 text-sm">Introduction</a></li>
                <li><a href="#authentication" class="text-gray-600 hover:text-gray-900 text-sm">Authentication</a></li>
                <li><a href="#rate-limiting" class="text-gray-600 hover:text-gray-900 text-sm">Rate Limiting</a></li>
                <li><a href="#errors" class="text-gray-600 hover:text-gray-900 text-sm">Error Handling</a></li>
            </ul>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">API Versions</h3>
            <ul class="mt-2 space-y-1">
                <li><a href="#v1" class="text-gray-600 hover:text-gray-900 text-sm flex items-center">
                    <span class="version-badge v1 mr-2">v1</span>
                    Current Stable
                </a></li>
                <li><a href="#v2" class="text-gray-600 hover:text-gray-900 text-sm flex items-center">
                    <span class="version-badge v2 mr-2">v2</span>
                    Beta
                </a></li>
            </ul>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Core Resources</h3>
            <ul class="mt-2 space-y-1">
                <li><a href="#restaurants" class="text-gray-600 hover:text-gray-900 text-sm">Restaurants</a></li>
                <li><a href="#orders" class="text-gray-600 hover:text-gray-900 text-sm">Orders</a></li>
                <li><a href="#customers" class="text-gray-600 hover:text-gray-900 text-sm">Customers</a></li>
                <li><a href="#staff" class="text-gray-600 hover:text-gray-900 text-sm">Staff</a></li>
                <li><a href="#loyalty" class="text-gray-600 hover:text-gray-900 text-sm">Loyalty Programs</a></li>
            </ul>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Integrations</h3>
            <ul class="mt-2 space-y-1">
                <li><a href="#pos" class="text-gray-600 hover:text-gray-900 text-sm">POS Systems</a></li>
                <li><a href="#delivery" class="text-gray-600 hover:text-gray-900 text-sm">Delivery</a></li>
                <li><a href="#webhooks" class="text-gray-600 hover:text-gray-900 text-sm">Webhooks</a></li>
            </ul>
        </div>
    </div>
@endsection

@section('content')
    <div class="max-w-4xl">
        <!-- Introduction -->
        <section id="introduction" class="mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-6">FoodHub API Documentation</h1>
            
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Current Stable Version:</strong> v1 | 
                            <strong>Latest Beta:</strong> v2 | 
                            <strong>Base URL:</strong> {{ config('app.url') }}/api
                        </p>
                    </div>
                </div>
            </div>

            <p class="text-lg text-gray-600 mb-4">
                The FoodHub API provides comprehensive access to restaurant management, order processing, 
                customer loyalty, and delivery coordination features. Our API follows REST principles 
                and supports multiple versions for backward compatibility.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">RESTful Design</h3>
                    <p class="text-gray-600 text-sm">Standard HTTP methods, status codes, and resource-based URLs</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Version Control</h3>
                    <p class="text-gray-600 text-sm">URL-based versioning with backward compatibility</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Comprehensive Auth</h3>
                    <p class="text-gray-600 text-sm">Role-based access control with MFA support</p>
                </div>
            </div>
        </section>

        <!-- API Versions -->
        <section id="versions" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">API Versions</h2>
            
            <div class="space-y-6">
                <!-- v1 -->
                <div id="v1" class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">Version 1 (v1)</h3>
                        <span class="version-badge v1">Current Stable</span>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Version 1 is our current stable API with full feature support. All endpoints are 
                        production-ready and actively maintained.
                    </p>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-900">Status:</span> 
                            <span class="text-green-600">Active</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-900">Release Date:</span> 
                            <span class="text-gray-600">January 2024</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-900">Sunset Date:</span> 
                            <span class="text-gray-600">None</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-900">Breaking Changes:</span> 
                            <span class="text-gray-600">None</span>
                        </div>
                    </div>
                </div>

                <!-- v2 -->
                <div id="v2" class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold text-gray-900">Version 2 (v2)</h3>
                        <span class="version-badge v2">Beta</span>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Version 2 introduces new features and improvements while maintaining compatibility 
                        with v1 where possible. Currently in beta testing.
                    </p>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-900">Status:</span> 
                            <span class="text-blue-600">Beta</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-900">Release Date:</span> 
                            <span class="text-gray-600">Q2 2024</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-900">Sunset Date:</span> 
                            <span class="text-gray-600">TBD</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-900">Breaking Changes:</span> 
                            <span class="text-yellow-600">Some</span>
                        </div>
                    </div>
                    <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                        <p class="text-sm text-yellow-800">
                            <strong>Note:</strong> v2 is in beta and may have breaking changes. 
                            <a href="{{ route('api.docs.migration') }}" class="underline">View migration guide</a> for details.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Authentication -->
        <section id="authentication" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Authentication</h2>
            
            <p class="text-gray-600 mb-4">
                The FoodHub API uses Laravel Sanctum for token-based authentication. All private endpoints 
                require a valid authentication token.
            </p>

            <div class="bg-gray-900 rounded-lg p-6 mb-6">
                <h4 class="text-white font-semibold mb-2">Example Authentication Request</h4>
                <pre class="text-green-400 text-sm"><code>POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}</code></pre>
            </div>

            <div class="bg-gray-900 rounded-lg p-6">
                <h4 class="text-white font-semibold mb-2">Example Authenticated Request</h4>
                <pre class="text-green-400 text-sm"><code>GET /api/v1/user
Authorization: Bearer {your-token}
Accept: application/json</code></pre>
            </div>
        </section>

        <!-- Rate Limiting -->
        <section id="rate-limiting" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Rate Limiting</h2>
            
            <p class="text-gray-600 mb-4">
                API requests are rate-limited to ensure fair usage and system stability. Different 
                endpoints have different rate limits based on their resource intensity.
            </p>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endpoint Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate Limit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Window</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Public endpoints</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">100 requests</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">per minute</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Authenticated endpoints</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">1000 requests</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">per minute</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Login attempts</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">5 attempts</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">per 15 minutes</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Error Handling -->
        <section id="errors" class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Error Handling</h2>
            
            <p class="text-gray-600 mb-4">
                The API uses standard HTTP status codes and returns detailed error messages in JSON format 
                to help with debugging and integration.
            </p>

            <div class="bg-gray-900 rounded-lg p-6">
                <h4 class="text-white font-semibold mb-2">Example Error Response</h4>
                <pre class="text-red-400 text-sm"><code>{
    "error": "Validation failed",
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password field is required."]
    },
    "status_code": 422
}</code></pre>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Common Status Codes</h4>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><strong>200:</strong> Success</li>
                        <li><strong>201:</strong> Created</li>
                        <li><strong>400:</strong> Bad Request</li>
                        <li><strong>401:</strong> Unauthorized</li>
                        <li><strong>403:</strong> Forbidden</li>
                        <li><strong>404:</strong> Not Found</li>
                        <li><strong>422:</strong> Validation Error</li>
                        <li><strong>429:</strong> Too Many Requests</li>
                        <li><strong>500:</strong> Server Error</li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Error Headers</h4>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><strong>X-API-Version:</strong> Current API version</li>
                        <li><strong>X-API-Version-Status:</strong> Version status</li>
                        <li><strong>Deprecation:</strong> Deprecation warning</li>
                        <li><strong>Sunset:</strong> Sunset date</li>
                        <li><strong>Link:</strong> Successor version link</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Quick Start -->
        <section class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Quick Start</h2>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-900 mb-3">Get Started in 3 Steps</h3>
                <ol class="list-decimal list-inside space-y-2 text-blue-800">
                    <li><strong>Get your API key:</strong> Contact our team to get your restaurant's API credentials</li>
                    <li><strong>Authenticate:</strong> Use the login endpoint to get your access token</li>
                    <li><strong>Make requests:</strong> Include your token in the Authorization header for all requests</li>
                </ol>
                <div class="mt-4">
                    <a href="{{ route('api.docs.examples') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        View Examples
                    </a>
                </div>
            </div>
        </section>
    </div>
@endsection
