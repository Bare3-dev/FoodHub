<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * API Error Response Trait
 * 
 * Provides standardized error response formatting for consistent API responses
 */
trait ApiErrorResponse
{
    /**
     * Generate standardized error response
     */
    protected function errorResponse(
        string $message,
        string $errorCode = 'API_ERROR',
        int $statusCode = 400,
        array $errors = [],
        array $context = [],
        ?string $requestId = null
    ): JsonResponse {
        $response = [
            'error' => $this->getErrorTitle($statusCode),
            'message' => $message,
            'error_code' => $errorCode,
            'timestamp' => now()->toISOString(),
            'request_id' => $requestId ?: $this->generateRequestId(),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if (!empty($context)) {
            $response['context'] = $context;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse(
            $message,
            'VALIDATION_ERROR',
            422,
            $errors
        );
    }

    /**
     * Not found error response
     */
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse(
            "{$resource} not found",
            'RESOURCE_NOT_FOUND',
            404,
            [],
            ['resource_type' => strtolower($resource)]
        );
    }

    /**
     * Unauthorized error response
     */
    protected function unauthorizedResponse(string $message = 'Authentication required'): JsonResponse
    {
        return $this->errorResponse(
            $message,
            'AUTHENTICATION_REQUIRED',
            401
        );
    }

    /**
     * Forbidden error response
     */
    protected function forbiddenResponse(string $message = 'Insufficient permissions'): JsonResponse
    {
        return $this->errorResponse(
            $message,
            'INSUFFICIENT_PERMISSIONS',
            403
        );
    }

    /**
     * Conflict error response
     */
    protected function conflictResponse(string $message, array $context = []): JsonResponse
    {
        return $this->errorResponse(
            $message,
            'CONFLICT',
            409,
            [],
            $context
        );
    }

    /**
     * Server error response
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse(
            $message,
            'INTERNAL_ERROR',
            500
        );
    }

    /**
     * Business logic error response
     */
    protected function businessLogicErrorResponse(
        string $message,
        string $errorCode,
        array $context = []
    ): JsonResponse {
        return $this->errorResponse(
            $message,
            $errorCode,
            422,
            [],
            $context
        );
    }

    /**
     * Rate limit error response
     */
    protected function rateLimitResponse(int $retryAfter = null): JsonResponse
    {
        $response = $this->errorResponse(
            'Too many requests. Please try again later.',
            'RATE_LIMIT_EXCEEDED',
            429
        );

        if ($retryAfter) {
            $response->header('Retry-After', $retryAfter);
        }

        return $response;
    }

    /**
     * Get error title based on status code
     */
    private function getErrorTitle(int $statusCode): string
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
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return 'req_' . now()->format('Ymd_His') . '_' . strtoupper(substr(md5(uniqid()), 0, 8));
    }
} 