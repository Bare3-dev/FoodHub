<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * API Success Response Trait
 * 
 * Provides standardized success response formatting for consistent API responses
 */
trait ApiSuccessResponse
{
    /**
     * Generate standardized success response
     */
    protected function successResponse(
        $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        return response()->json($response, $statusCode);
    }

    /**
     * Success response with resource collection
     */
    protected function successResponseWithCollection(
        ResourceCollection $collection,
        string $message = 'Data retrieved successfully'
    ): JsonResponse {
        $responseData = $collection->response()->getData(true);
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $responseData['data'],
        ];

        // Include pagination metadata if it exists
        if (isset($responseData['meta'])) {
            $response['meta'] = $responseData['meta'];
        }

        return response()->json($response, 200);
    }

    /**
     * Success response with single resource
     */
    protected function successResponseWithResource(
        JsonResource $resource,
        string $message = 'Data retrieved successfully'
    ): JsonResponse {
        return $this->successResponse(
            $resource->response()->getData(true)['data'],
            $message
        );
    }

    /**
     * Created response
     */
    protected function createdResponse(
        $data = null,
        string $message = 'Resource created successfully'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Updated response
     */
    protected function updatedResponse(
        $data = null,
        string $message = 'Resource updated successfully'
    ): JsonResponse {
        return $this->successResponse($data, $message, 200);
    }

    /**
     * Deleted response
     */
    protected function deletedResponse(
        string $message = 'Resource deleted successfully'
    ): JsonResponse {
        return $this->successResponse(null, $message, 200);
    }
} 