<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileUpload\UploadMenuItemImageRequest;
use App\Http\Requests\FileUpload\UploadRestaurantLogoRequest;
use App\Http\Requests\FileUpload\UploadUserAvatarRequest;
use App\Http\Resources\Api\FileUploadResource;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\FileUploadService;
use App\Traits\ApiErrorResponse;
use App\Traits\ApiSuccessResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Exceptions\BusinessLogicException;

/**
 * File Upload Controller
 * 
 * Handles file upload operations for menu items, restaurant logos, and user avatars
 * with comprehensive validation, security checks, and error handling.
 */
final class FileUploadController extends Controller
{
    use ApiSuccessResponse, ApiErrorResponse;

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Upload menu item image
     */
    public function uploadMenuItemImage(UploadMenuItemImageRequest $request, MenuItem $menuItem): JsonResponse
    {
        try {
            // Check if user has permission to upload images for this menu item
            if (!Auth::user()->can('upload', $menuItem)) {
                            return $this->errorResponse(
                'You do not have permission to upload images for this menu item',
                'INSUFFICIENT_PERMISSIONS',
                403
            );
            }

            $file = $request->file('image');
            $imageUrl = $this->fileUploadService->uploadMenuItemImage($menuItem, $file);

            return $this->successResponse(
                new FileUploadResource([
                    'url' => $imageUrl,
                    'type' => 'menu_item_image',
                    'entity_id' => $menuItem->id,
                    'filename' => $file->getClientOriginalName() ?? basename($imageUrl),
                    'uploaded_at' => now()->toISOString(),
                    'thumbnails' => [],
                    'metadata' => [
                        'original_name' => $file->getClientOriginalName(),
                        'extension' => $file->getClientOriginalExtension(),
                        'dimensions' => null,
                        'file_size' => $file->getSize(),
                    ],
                    'security' => [
                        'is_public' => true,
                        'access_control' => 'public_read',
                        'virus_scan_status' => 'pending',
                    ],
                    'links' => [
                        'self' => $imageUrl,
                        'delete' => route('api.files.delete', ['file_path' => str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH))]),
                        'status' => route('api.files.status', ['file_path' => str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH))]),
                    ],
                ]),
                'Menu item image uploaded successfully'
            );

        } catch (\Exception $e) {
            Log::error('Menu item image upload failed', [
                'menu_item_id' => $menuItem->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to upload menu item image: ' . $e->getMessage(),
                'UPLOAD_FAILED',
                500
            );
        }
    }

    /**
     * Upload restaurant logo
     */
    public function uploadRestaurantLogo(UploadRestaurantLogoRequest $request, Restaurant $restaurant): JsonResponse
    {
        try {
            // Check if user has permission to upload logo for this restaurant
            if (!Auth::user()->can('upload', $restaurant)) {
                            return $this->errorResponse(
                'You do not have permission to upload logo for this restaurant',
                'INSUFFICIENT_PERMISSIONS',
                403
            );
            }

            $file = $request->file('logo');
            $logoUrl = $this->fileUploadService->uploadRestaurantLogo($restaurant, $file);

            return $this->successResponse(
                new FileUploadResource([
                    'url' => $logoUrl,
                    'type' => 'restaurant_logo',
                    'entity_id' => $restaurant->id,
                    'filename' => $file->getClientOriginalName() ?? basename($logoUrl),
                    'uploaded_at' => now()->toISOString(),
                    'thumbnails' => [],
                    'metadata' => [
                        'original_name' => $file->getClientOriginalName(),
                        'extension' => $file->getClientOriginalExtension(),
                        'dimensions' => null,
                        'file_size' => $file->getSize(),
                    ],
                    'security' => [
                        'is_public' => true,
                        'access_control' => 'public_read',
                        'virus_scan_status' => 'pending',
                    ],
                    'links' => [
                        'self' => $logoUrl,
                        'delete' => route('api.files.delete', ['file_path' => str_replace('/storage/', '', parse_url($logoUrl, PHP_URL_PATH))]),
                        'status' => route('api.files.status', ['file_path' => str_replace('/storage/', '', parse_url($logoUrl, PHP_URL_PATH))]),
                    ],
                ]),
                'Restaurant logo uploaded successfully'
            );

        } catch (\Exception $e) {
            Log::error('Restaurant logo upload failed', [
                'restaurant_id' => $restaurant->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to upload restaurant logo: ' . $e->getMessage(),
                'UPLOAD_FAILED',
                500
            );
        }
    }

    /**
     * Upload user avatar
     */
    public function uploadUserAvatar(UploadUserAvatarRequest $request, User $user): JsonResponse
    {
        try {
            // Check if user has permission to upload avatar for this user
            if (!Auth::user()->can('upload', $user)) {
                            return $this->errorResponse(
                'You do not have permission to upload avatar for this user',
                'INSUFFICIENT_PERMISSIONS',
                403
            );
            }

            $file = $request->file('avatar');
            $avatarUrl = $this->fileUploadService->uploadUserAvatar($user, $file);

            return $this->successResponse(
                new FileUploadResource([
                    'url' => $avatarUrl,
                    'type' => 'user_avatar',
                    'entity_id' => $user->id,
                    'filename' => $file->getClientOriginalName() ?? basename($avatarUrl),
                    'uploaded_at' => now()->toISOString(),
                    'thumbnails' => [],
                    'metadata' => [
                        'original_name' => $file->getClientOriginalName(),
                        'extension' => $file->getClientOriginalExtension(),
                        'dimensions' => null,
                        'file_size' => $file->getSize(),
                    ],
                    'security' => [
                        'is_public' => true,
                        'access_control' => 'public_read',
                        'virus_scan_status' => 'pending',
                    ],
                    'links' => [
                        'self' => $avatarUrl,
                        'delete' => route('api.files.delete', ['file_path' => str_replace('/storage/', '', parse_url($avatarUrl, PHP_URL_PATH))]),
                        'status' => route('api.files.status', ['file_path' => str_replace('/storage/', '', parse_url($avatarUrl, PHP_URL_PATH))]),
                    ],
                ]),
                'User avatar uploaded successfully'
            );

        } catch (\Exception $e) {
            Log::error('User avatar upload failed', [
                'user_id' => $user->id,
                'uploader_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to upload user avatar: ' . $e->getMessage(),
                'UPLOAD_FAILED',
                500
            );
        }
    }

    /**
     * Delete file
     */
    public function deleteFile(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file_path' => 'required|string|max:500',
            ]);

            $filePath = $request->input('file_path') ?? $request->query('file_path');
            $deleted = $this->fileUploadService->deleteFile($filePath);

            if (!$deleted) {
                return $this->errorResponse(
                    'File not found or could not be deleted',
                    'FILE_NOT_FOUND',
                    404
                );
            }

            return $this->successResponse(
                ['file_path' => $filePath],
                'File deleted successfully'
            );

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (BusinessLogicException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'file_path' => $request->input('file_path') ?? $request->query('file_path'),
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete file: ' . $e->getMessage(),
                'DELETE_FAILED',
                500
            );
        }
    }

    /**
     * Get file upload status
     */
    public function getUploadStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file_path' => 'required|string|max:500',
            ]);

            $filePath = $request->input('file_path') ?? $request->query('file_path');
            
            // Validate file path for security before checking storage
            if (!$this->fileUploadService->isValidFilePath($filePath)) {
                throw new \App\Exceptions\SecurityException('Invalid file path provided');
            }
            
            $exists = Storage::disk('public')->exists($filePath);

            return $this->successResponse(
                [
                    'file_path' => $filePath,
                    'exists' => $exists,
                    'url' => $exists ? Storage::disk('public')->url($filePath) : null,
                ],
                'File status retrieved successfully'
            );

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\App\Exceptions\SecurityException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Security validation failed',
                'error_code' => 'SECURITY_VIOLATION',
            ], 422);
        } catch (BusinessLogicException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('File status check failed', [
                'file_path' => $request->input('file_path') ?? $request->query('file_path'),
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to check file status: ' . $e->getMessage(),
                'STATUS_CHECK_FAILED',
                500
            );
        }
    }
} 