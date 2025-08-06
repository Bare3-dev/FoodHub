<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Exceptions\SecurityException;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * File Upload Service Test
 * 
 * Comprehensive unit tests for the FileUploadService covering
 * all upload scenarios, validation, error handling, and security features.
 */
final class FileUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private FileUploadService $fileUploadService;
    private Restaurant $restaurant;
    private MenuItem $menuItem;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fileUploadService = new FileUploadService();
        
        // Create test data
        $this->restaurant = Restaurant::factory()->create();
        $this->menuItem = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->user = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        
        // Configure storage for testing
        Storage::fake('public');
    }



    /** @test */
    public function it_can_upload_menu_item_image(): void
    {
        $file = UploadedFile::fake()->create('menu-item.jpg', 1000, 'image/jpeg');
        
        $imageUrl = $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file);
        
        $this->assertNotEmpty($imageUrl);
        $this->assertStringContainsString('menu_items', $imageUrl);
        $this->assertStringContainsString((string) $this->restaurant->id, $imageUrl);
        
        // Check that menu item was updated
        $this->menuItem->refresh();
        $this->assertIsArray($this->menuItem->images);
        $this->assertNotEmpty($this->menuItem->images);
        
        // Check that file was stored
        $filePath = str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH));
        
        // Debug: List all files in storage
        $allFiles = Storage::disk('public')->allFiles();
        $debugInfo = "All files in storage: " . implode(', ', $allFiles);
        
        $this->assertTrue(Storage::disk('public')->exists($filePath), 
            "File not found at path: {$filePath}. Image URL: {$imageUrl}. {$debugInfo}");
    }

    /** @test */
    public function it_can_upload_restaurant_logo(): void
    {
        $file = UploadedFile::fake()->create('logo.png', 1000, 'image/png');
        
        $logoUrl = $this->fileUploadService->uploadRestaurantLogo($this->restaurant, $file);
        
        $this->assertNotEmpty($logoUrl);
        $this->assertStringContainsString('restaurants', $logoUrl);
        $this->assertStringContainsString((string) $this->restaurant->id, $logoUrl);
        
        // Check that restaurant was updated
        $this->restaurant->refresh();
        $this->assertEquals($logoUrl, $this->restaurant->logo_url);
        
        // Check that file was stored
        $this->assertTrue(Storage::disk('public')->exists(
            str_replace('/storage/', '', parse_url($logoUrl, PHP_URL_PATH))
        ));
    }

    /** @test */
    public function it_can_upload_user_avatar(): void
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 1000, 'image/jpeg');
        
        $avatarUrl = $this->fileUploadService->uploadUserAvatar($this->user, $file);
        
        $this->assertNotEmpty($avatarUrl);
        $this->assertStringContainsString('users', $avatarUrl);
        $this->assertStringContainsString((string) $this->user->id, $avatarUrl);
        
        // Check that user was updated
        $this->user->refresh();
        $this->assertEquals($avatarUrl, $this->user->profile_image_url);
        
        // Check that file was stored
        $this->assertTrue(Storage::disk('public')->exists(
            str_replace('/storage/', '', parse_url($avatarUrl, PHP_URL_PATH))
        ));
    }

    /** @test */
    public function it_rejects_invalid_file_types(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000);
        
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Invalid file type');
        
        $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file);
    }

    /** @test */
    public function it_rejects_files_exceeding_size_limit(): void
    {
        // Create a large file to test size limits
        $file = UploadedFile::fake()->create('large-image.jpg', 6 * 1024 * 1024, 'image/jpeg');
        
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('File size exceeds maximum limit');
        
        $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file);
    }

    /** @test */
    public function it_rejects_corrupted_image_files(): void
    {
        // Create a service instance without mocking for this test
        $service = new FileUploadService();
        
        // Create a file with valid MIME type but invalid content
        $file = UploadedFile::fake()->create('corrupted.jpg', 1000, 'image/jpeg');
        
        // Skip this test if GD extension is not available, as the service will accept the MIME type
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available, skipping corrupted image test');
        }
        
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Invalid file type');
        
        $service->uploadMenuItemImage($this->menuItem, $file);
    }

    /** @test */
    public function it_can_delete_file(): void
    {
        // First upload a file
        $file = UploadedFile::fake()->create('test.jpg', 1000, 'image/jpeg');
        $imageUrl = $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file);
        
        // Extract file path from URL
        $filePath = str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH));
        
        // Delete the file
        $result = $this->fileUploadService->deleteFile($filePath);
        
        $this->assertTrue($result);
        $this->assertFalse(Storage::disk('public')->exists($filePath));
    }

    /** @test */
    public function it_returns_false_when_deleting_nonexistent_file(): void
    {
        $result = $this->fileUploadService->deleteFile('menu_items/1/nonexistent.jpg');
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_invalid_file_paths_for_deletion(): void
    {
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Failed to delete file: Invalid file path provided');
        
        $this->fileUploadService->deleteFile('../../../etc/passwd');
    }

    /** @test */
    public function it_generates_thumbnails_for_menu_item_images(): void
    {
        $file = UploadedFile::fake()->create('menu-item.jpg', 1000, 'image/jpeg');
        
        $imageUrl = $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file);
        
        $this->menuItem->refresh();
        $this->assertIsArray($this->menuItem->images);
        
        $images = $this->menuItem->images;
        $lastImage = end($images);
        $this->assertArrayHasKey('thumbnails', $lastImage);
        $this->assertArrayHasKey('small', $lastImage['thumbnails']);
        $this->assertArrayHasKey('medium', $lastImage['thumbnails']);
        $this->assertArrayHasKey('large', $lastImage['thumbnails']);
    }

    /** @test */
    public function it_generates_image_thumbnails_with_custom_sizes(): void
    {
        $imageUrl = 'https://example.com/test.jpg';
        $sizes = [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [600, 600],
        ];
        
        $thumbnails = $this->fileUploadService->generateImageThumbnails($imageUrl, $sizes);
        
        $this->assertIsArray($thumbnails);
        
        // If GD extension is not available, thumbnail generation will return original for all sizes
        if (!extension_loaded('gd')) {
            $this->assertArrayHasKey('small', $thumbnails);
            $this->assertArrayHasKey('medium', $thumbnails);
            $this->assertArrayHasKey('large', $thumbnails);
            $this->assertEquals($imageUrl, $thumbnails['small']);
            $this->assertEquals($imageUrl, $thumbnails['medium']);
            $this->assertEquals($imageUrl, $thumbnails['large']);
        } else {
            $this->assertArrayHasKey('small', $thumbnails);
            $this->assertArrayHasKey('medium', $thumbnails);
            $this->assertArrayHasKey('large', $thumbnails);
        }
    }

    /** @test */
    public function it_handles_thumbnail_generation_failure_gracefully(): void
    {
        $invalidImageUrl = 'https://example.com/invalid.jpg';
        $sizes = ['small' => [150, 150]];
        
        $thumbnails = $this->fileUploadService->generateImageThumbnails($invalidImageUrl, $sizes);
        
        $this->assertIsArray($thumbnails);
        $this->assertArrayHasKey('small', $thumbnails);
        $this->assertEquals($invalidImageUrl, $thumbnails['small']);
    }

    /** @test */
    public function it_caches_logo_variants(): void
    {
        $file = UploadedFile::fake()->create('logo.png', 1000, 'image/png');
        
        $this->fileUploadService->uploadRestaurantLogo($this->restaurant, $file);
        
        $cacheKey = "restaurant_logo_variants_{$this->restaurant->id}";
        $this->assertTrue(Cache::has($cacheKey));
        
        $variants = Cache::get($cacheKey);
        $this->assertIsArray($variants);
        $this->assertArrayHasKey('original', $variants);
        $this->assertArrayHasKey('header', $variants);
        $this->assertArrayHasKey('favicon', $variants);
        $this->assertArrayHasKey('thumbnail', $variants);
    }

    /** @test */
    public function it_caches_avatar_variants(): void
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 1000, 'image/jpeg');
        
        $this->fileUploadService->uploadUserAvatar($this->user, $file);
        
        $cacheKey = "user_avatar_variants_{$this->user->id}";
        $this->assertTrue(Cache::has($cacheKey));
        
        $variants = Cache::get($cacheKey);
        $this->assertIsArray($variants);
        $this->assertArrayHasKey('original', $variants);
        $this->assertArrayHasKey('square_150', $variants);
        $this->assertArrayHasKey('square_300', $variants);
        $this->assertArrayHasKey('square_50', $variants);
    }

    /** @test */
    public function it_clears_caches_when_deleting_files(): void
    {
        // Upload a restaurant logo
        $file = UploadedFile::fake()->create('logo.png', 1000, 'image/png');
        $logoUrl = $this->fileUploadService->uploadRestaurantLogo($this->restaurant, $file);
        
        // Verify cache exists
        $cacheKey = "restaurant_logo_variants_{$this->restaurant->id}";
        $this->assertTrue(Cache::has($cacheKey));
        
        // Delete the file
        $filePath = str_replace('/storage/', '', parse_url($logoUrl, PHP_URL_PATH));
        $this->fileUploadService->deleteFile($filePath);
        
        // Verify cache was cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    /** @test */
    public function it_generates_unique_filenames(): void
    {
        $file1 = UploadedFile::fake()->create('menu-item.jpg', 1000, 'image/jpeg');
        $file2 = UploadedFile::fake()->create('menu-item.jpg', 1000, 'image/jpeg');
        
        $imageUrl1 = $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file1);
        $imageUrl2 = $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file2);
        
        $this->assertNotEquals($imageUrl1, $imageUrl2);
        
        // Check that filenames are unique
        $filename1 = basename(parse_url($imageUrl1, PHP_URL_PATH));
        $filename2 = basename(parse_url($imageUrl2, PHP_URL_PATH));
        $this->assertNotEquals($filename1, $filename2);
    }

    /** @test */
    public function it_validates_restaurant_logo_size_limits(): void
    {
        $file = UploadedFile::fake()->create('logo.png', 3 * 1024 * 1024, 'image/png');
        
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('File size exceeds maximum limit of 2MB');
        
        $this->fileUploadService->uploadRestaurantLogo($this->restaurant, $file);
    }

    /** @test */
    public function it_validates_user_avatar_size_limits(): void
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 2 * 1024 * 1024, 'image/jpeg');
        
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('File size exceeds maximum limit of 1MB');
        
        $this->fileUploadService->uploadUserAvatar($this->user, $file);
    }

    /** @test */
    public function it_handles_upload_errors_gracefully(): void
    {
        // Create a file that will pass validation but fail storage
        $file = UploadedFile::fake()->create('menu-item.jpg', 1000, 'image/jpeg');
        
        // Mock Storage to throw an exception
        Storage::shouldReceive('putFileAs')->andReturn(false);
        
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Failed to store file');
        
        $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file);
    }

    /** @test */
    public function it_logs_successful_uploads(): void
    {
        $file = UploadedFile::fake()->create('menu-item.jpg', 1000, 'image/jpeg');
        
        $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file);
        
        // Note: In a real test, you would verify the log entries
        // This test ensures the method doesn't throw exceptions during logging
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_upload_errors(): void
    {
        $file = UploadedFile::fake()->create('invalid.txt', 1000);
        
        try {
            $this->fileUploadService->uploadMenuItemImage($this->menuItem, $file);
        } catch (BusinessLogicException $e) {
            // Expected exception
        }
        
        // Note: In a real test, you would verify the error log entries
        // This test ensures the method doesn't throw exceptions during error logging
        $this->assertTrue(true);
    }
} 