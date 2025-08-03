<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use App\Services\SecurityLoggingService;
use Throwable;

/**
 * Comprehensive Exception Handler for Professional API Error Responses
 * 
 * Features:
 * - Consistent JSON error response structure
 * - Security-aware error message formatting
 * - Environment-based error detail exposure
 * - Integration with security logging system
 * - Professional error categorization
 * - Rate limiting and security error handling
 * - Proper HTTP status codes and headers
 */
class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
        MethodNotAllowedHttpException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'cvv',
        'security_code',
        'pin',
        'otp',
        'token',
        'secret',
        'api_key',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Handle invalid route model binding
        $this->renderable(function (\Illuminate\Database\QueryException $e, $request) {
            if (str_contains($e->getMessage(), 'invalid input syntax for type bigint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                    'errors' => []
                ], 404);
            }
        });

        // Handle invalid route model binding for API requests
        $this->renderable(function (\Illuminate\Database\QueryException $e, $request) {
            if ($request->is('api/*') && str_contains($e->getMessage(), 'invalid input syntax for type bigint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                    'errors' => []
                ], 404);
            }
        });

        // Handle model not found exceptions
        $this->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'The requested resource was not found.',
                'errors' => []
            ], 404);
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response|JsonResponse
    {
        // Always return JSON for API requests
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Render API exceptions with consistent JSON structure
     */
    protected function renderApiException(Request $request, Throwable $e): JsonResponse
    {
        // Log security-related exceptions
        $this->logSecurityException($e, $request);

        // Get standardized error response details (status, internal error data)
        $errorDetails = $this->getErrorResponse($e, $request);

        // Prepare the final API response structure
        $finalResponseData = [
            'success' => false, // All API errors should indicate failure
            'message' => $errorDetails['data']['message'] ?? $errorDetails['data']['error'] ?? 'An unexpected error occurred.',
            'errors' => [], // Default to an empty array for consistency
        ];

        // If it's a validation exception, populate 'errors' with validation messages
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            $finalResponseData['errors'] = $errorDetails['data']['errors'] ?? [];
        } else {
            // For other types of errors, put the detailed error info into the 'errors' array
            // We want to avoid polluting the root level with too many keys.
            // But for the `assertApiError` helper, it expects `errors` to be an empty array if not validation.
            // So, if we want to provide more specific error details, it should be nested.
            // Let's refine based on the `assertApiError` structure which simply checks for `errors => []`

            // For non-validation errors, we already have a top-level message.
            // If there are additional details (like error_code, context, etc.) from `getErrorResponse`,
            // we can place them into the `errors` array, or create a 'details' key for them.
            // For now, let's keep `errors` empty for non-validation as per the `assertApiError` test,
            // and trust the `message` and HTTP status code. If more info is needed, it would be a new key.
            // Example: 'details' => $errorDetails['data']
            
            // However, looking at the previous assertApiError, it did check for an 'errors' key.
            // To be compliant with `assertApiError` strictly expecting `errors => []` for non-validation:
            // The previous `AuthApiTest` was failing because the *top-level* response didn't have `success` and `message`.
            // Now that `AuthController` returns `success` and `message` for successful login/MFA,
            // we need `Handler.php` to return `success` and `message` for *failed* API requests.
            
            // The `getErrorResponse` method already returns a structured array with `error`, `message`, `error_code`, etc., under a `data` key.
            // Let's ensure that what `renderApiException` returns fits the common API error standard.
            
            // Merge all details from $errorDetails['data'] into the `errors` array, except for the message that is already extracted.
            // This ensures all custom error codes and contexts are still available.
            $additionalErrorData = $errorDetails['data'];
            unset($additionalErrorData['message']); // Message is already at the top level
            
            // Only add remaining error data if it's not empty, otherwise keep `errors` as empty array
            if (!empty($additionalErrorData)) {
                $finalResponseData['errors'] = $additionalErrorData;
            }
        }

        $response = response()->json($finalResponseData, $errorDetails['status']);
        
        // Add appropriate headers
        if (isset($errorDetails['headers'])) {
            foreach ($errorDetails['headers'] as $key => $value) {
                $response->header($key, $value);
            }
        }

        return $response;
    }

    /**
     * Get standardized error response structure
     */
    protected function getErrorResponse(Throwable $e, Request $request): array
    {
        $isProduction = app()->environment('production');

        switch (true) {
            case $e instanceof \App\Exceptions\BusinessLogicException:
                return $this->handleBusinessLogicException($e);

            case $e instanceof \App\Exceptions\SecurityException:
                return $this->handleSecurityException($e, $request);

            case $e instanceof ValidationException:
                return $this->handleValidationException($e);

            case $e instanceof AuthenticationException:
                return $this->handleAuthenticationException($e, $request);

            case $e instanceof AuthorizationException:
                return $this->handleAuthorizationException($e, $request);

            case $e instanceof ModelNotFoundException:
                return $this->handleModelNotFoundException($e);

            case $e instanceof NotFoundHttpException:
                return $this->handleNotFoundHttpException($e, $request);

            case $e instanceof MethodNotAllowedHttpException:
                return $this->handleMethodNotAllowedException($e);

            case $e instanceof TooManyRequestsHttpException:
                return $this->handleRateLimitException($e, $request);

            case $e instanceof QueryException:
                return $this->handleDatabaseException($e, $isProduction);

            case $e instanceof HttpException:
                return $this->handleHttpException($e, $isProduction);

            default:
                return $this->handleGenericException($e, $isProduction);
        }
    }

    /**
     * Handle business logic exceptions
     */
    protected function handleBusinessLogicException(\App\Exceptions\BusinessLogicException $e): array
    {
        return [
            'status' => $e->getStatusCode(),
            'data' => [
                'error' => 'Business Logic Error',
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
                'context' => $e->getContext(),
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Handle security exceptions with automatic logging
     */
    protected function handleSecurityException(\App\Exceptions\SecurityException $e, Request $request): array
    {
        // Security exceptions handle their own logging via the render method
        $response = $e->render($request);
        
        return [
            'status' => $e->getStatusCode(),
            'data' => json_decode($response->getContent(), true)
        ];
    }

    /**
     * Handle validation exceptions
     */
    protected function handleValidationException(ValidationException $e): array
    {
        return [
            'status' => 422,
            'data' => [
                'error' => 'Validation Failed',
                'message' => 'The provided data is invalid.',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR',
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Handle authentication exceptions
     */
    protected function handleAuthenticationException(AuthenticationException $e, Request $request): array
    {
        $message = 'Authentication required.';
        $errorCode = 'AUTHENTICATION_REQUIRED';
        $statusCode = 401; // Default to 401 Unauthorized

        // Check if it's an inactive account specific message
        if ($e->getMessage() === __('auth.inactive_account')) {
            $message = __('auth.inactive_account');
            $errorCode = 'ACCOUNT_INACTIVE';
            $statusCode = 403; // Change status to 403 Forbidden for inactive accounts
        } elseif (str_contains($e->getMessage(), 'token')) {
            $message = 'Invalid or expired authentication token.';
            $errorCode = 'INVALID_TOKEN';
        }

        return [
            'status' => $statusCode, // Use the determined status code
            'data' => [
                'error' => $this->getHttpErrorTitle($statusCode), // Get appropriate error title
                'message' => $message,
                'error_code' => $errorCode,
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Handle authorization exceptions
     */
    protected function handleAuthorizationException(AuthorizationException $e, Request $request): array
    {
        return [
            'status' => 403,
            'data' => [
                'error' => 'Forbidden',
                'message' => 'You do not have permission to perform this action.',
                'error_code' => 'INSUFFICIENT_PERMISSIONS',
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Handle model not found exceptions
     */
    protected function handleModelNotFoundException(ModelNotFoundException $e): array
    {
        $model = class_basename($e->getModel());
        
        return [
            'status' => 404,
            'data' => [
                'error' => 'Resource Not Found',
                'message' => "The requested {$model} could not be found.",
                'error_code' => 'RESOURCE_NOT_FOUND',
                'resource_type' => strtolower($model),
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Handle HTTP not found exceptions
     */
    protected function handleNotFoundHttpException(NotFoundHttpException $e, Request $request): array
    {
        return [
            'status' => 404,
            'data' => [
                'error' => 'Endpoint Not Found',
                'message' => 'The requested API endpoint does not exist.',
                'error_code' => 'ENDPOINT_NOT_FOUND',
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Handle method not allowed exceptions
     */
    protected function handleMethodNotAllowedException(MethodNotAllowedHttpException $e): array
    {
        $allowedMethods = $e->getHeaders()['Allow'] ?? 'Unknown';

        return [
            'status' => 405,
            'data' => [
                'error' => 'Method Not Allowed',
                'message' => 'The HTTP method used is not allowed for this endpoint.',
                'error_code' => 'METHOD_NOT_ALLOWED',
                'allowed_methods' => explode(', ', $allowedMethods),
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ],
            'headers' => [
                'Allow' => $allowedMethods,
            ]
        ];
    }

    /**
     * Handle rate limit exceptions
     */
    protected function handleRateLimitException(TooManyRequestsHttpException $e, Request $request): array
    {
        $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

        return [
            'status' => 429,
            'data' => [
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $retryAfter,
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ],
            'headers' => $retryAfter ? ['Retry-After' => $retryAfter] : []
        ];
    }

    /**
     * Handle database exceptions (security-aware)
     */
    protected function handleDatabaseException(QueryException $e, bool $isProduction): array
    {
        // Check if this is an invalid ID error (PostgreSQL error 22P02)
        if (str_contains($e->getMessage(), '22P02') && str_contains($e->getMessage(), 'invalid input syntax for type bigint')) {
            return [
                'status' => 404,
                'data' => [
                    'error' => 'Resource Not Found',
                    'message' => 'The requested resource could not be found.',
                    'error_code' => 'RESOURCE_NOT_FOUND',
                    'timestamp' => now()->toISOString(),
                    'request_id' => $this->generateRequestId(),
                ]
            ];
        }

        // In production, hide database details for security
        if ($isProduction) {
            return [
                'status' => 500,
                'data' => [
                    'error' => 'Database Error',
                    'message' => 'A database error occurred. Please try again later.',
                    'error_code' => 'DATABASE_ERROR',
                    'timestamp' => now()->toISOString(),
                    'request_id' => $this->generateRequestId(),
                ]
            ];
        }

        // In development, provide more details
        return [
            'status' => 500,
            'data' => [
                'error' => 'Database Error',
                'message' => 'Database query failed.',
                'error_code' => 'DATABASE_ERROR',
                'details' => $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Handle HTTP exceptions
     */
    protected function handleHttpException(HttpException $e, bool $isProduction): array
    {
        $statusCode = $e->getStatusCode();
        $message = $e->getMessage() ?: $this->getDefaultHttpMessage($statusCode);

        return [
            'status' => $statusCode,
            'data' => [
                'error' => $this->getHttpErrorTitle($statusCode),
                'message' => $message,
                'error_code' => 'HTTP_' . $statusCode,
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Handle generic exceptions
     */
    protected function handleGenericException(Throwable $e, bool $isProduction): array
    {
        // In production, hide internal error details
        if ($isProduction) {
            return [
                'status' => 500,
                'data' => [
                    'error' => 'Internal Server Error',
                    'message' => 'An unexpected error occurred. Please try again later.',
                    'error_code' => 'INTERNAL_ERROR',
                    'timestamp' => now()->toISOString(),
                    'request_id' => $this->generateRequestId(),
                ]
            ];
        }

        // In development, provide debugging information
        return [
            'status' => 500,
            'data' => [
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'error_code' => 'INTERNAL_ERROR',
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toISOString(),
                'request_id' => $this->generateRequestId(),
            ]
        ];
    }

    /**
     * Log security-related exceptions
     */
    protected function logSecurityException(Throwable $e, Request $request): void
    {
        try {
            $securityLogger = app(SecurityLoggingService::class);

            // Log authentication failures
            if ($e instanceof AuthenticationException) {
                $securityLogger->logAuthenticationFailure(
                    $request->input('email', 'unknown'),
                    $e->getMessage(),
                    $request
                );
            }

            // Log authorization failures
            if ($e instanceof AuthorizationException) {
                $securityLogger->logAuthorizationFailure(
                    $request->route()?->getName() ?? 'unknown',
                    $request->method(),
                    $request
                );
            }

            // Log suspicious database errors
            if ($e instanceof QueryException && str_contains($e->getMessage(), ['injection', 'select', 'union'])) {
                $securityLogger->logAttackAttempt(
                    'sql_injection_attempt',
                    ['query_error' => substr($e->getMessage(), 0, 200)],
                    $request
                );
            }
        } catch (Throwable $loggingError) {
            // If security logging fails, just log the error normally
            logger()->error('Security logging failed', ['error' => $loggingError->getMessage()]);
        }
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // For API requests, return JSON with our standardized format
        if ($request->expectsJson() || $request->is('api/*')) {
            $message = 'Authentication required.';
            $statusCode = 401; // Default to 401 Unauthorized

            // Check if it's an inactive account specific message
            if ($exception->getMessage() === __('auth.inactive_account')) {
                $message = __('auth.inactive_account');
                $statusCode = 403; // Change status to 403 Forbidden for inactive accounts
            } elseif (str_contains($exception->getMessage(), 'token')) {
                $message = 'Invalid or expired authentication token.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => [], // Empty array for non-validation errors
            ], $statusCode);
        }

        // For web requests, return a simple redirect without route dependency
        return response('Unauthorized', 401);
    }

    /**
     * Generate unique request ID for error tracking
     */
    protected function generateRequestId(): string
    {
        return 'req_' . now()->format('Ymd_His') . '_' . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    /**
     * Handle JSON validation exceptions with consistent API format
     * This method is called by Laravel specifically for JSON validation responses
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The provided data is invalid.',
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    /**
     * Get default HTTP error message
     */
    protected function getDefaultHttpMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'HTTP Error',
        };
    }

    /**
     * Get HTTP error title
     */
    protected function getHttpErrorTitle(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Validation Error',
            429 => 'Rate Limit Exceeded',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'Error',
        };
    }
} 