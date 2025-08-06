<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * File Upload Resource
 * 
 * API resource for file upload responses with comprehensive
 * metadata and security information.
 */
final class FileUploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'url' => $this->resource['url'],
            'type' => $this->resource['type'],
            'entity_id' => $this->resource['entity_id'],
            'filename' => $this->resource['filename'] ?? basename($this->resource['url']),
            'size' => $this->resource['size'] ?? null,
            'mime_type' => $this->resource['mime_type'] ?? null,
            'uploaded_at' => $this->resource['uploaded_at'] ?? now()->toISOString(),
            'thumbnails' => $this->resource['thumbnails'] ?? [],
            'metadata' => [
                'original_name' => $this->resource['original_name'] ?? null,
                'extension' => $this->resource['extension'] ?? pathinfo($this->resource['url'], PATHINFO_EXTENSION),
                'dimensions' => $this->resource['dimensions'] ?? null,
                'file_size' => $this->resource['file_size'] ?? null,
            ],
            'security' => [
                'is_public' => true,
                'access_control' => 'public_read',
                'virus_scan_status' => $this->resource['virus_scan_status'] ?? 'pending',
            ],
            'links' => [
                'self' => $this->resource['url'],
                'delete' => route('api.files.delete', ['file_path' => $this->getFilePathFromUrl($this->resource['url'])]),
                'status' => route('api.files.status', ['file_path' => $this->getFilePathFromUrl($this->resource['url'])]),
            ],
        ];
    }

    /**
     * Extract file path from URL for API operations
     */
    private function getFilePathFromUrl(string $url): string
    {
        // Remove the storage URL prefix to get the relative path
        $storageUrl = config('app.url') . '/storage/';
        if (str_starts_with($url, $storageUrl)) {
            return substr($url, strlen($storageUrl));
        }
        
        // If it's a full URL, extract the path
        $parsedUrl = parse_url($url);
        return $parsedUrl['path'] ?? '';
    }
} 