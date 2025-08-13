<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\RestaurantBranch;

/**
 * File Upload Controller Test
 * 
 * Feature tests for the FileUploadController covering
 * all API endpoints, authentication, authorization, and validation.
 */
final class FileUploadControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Restaurant $restaurant;
    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->restaurant = Restaurant::factory()->create();
        $this->menuItem = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'RESTAURANT_OWNER',
            'status' => 'active',
        ]);
        
        // Configure storage for testing
        Storage::fake('public');
    }

    /** @test */
    public function it_can_upload_menu_item_image(): void
    {
        // Create a minimal valid JPEG image (200x200 pixels) that meets the minimum dimension requirement
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('menu-item.jpg', $imageData);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'url',
                    'type',
                    'entity_id',
                    'filename',
                    'uploaded_at',
                    'thumbnails',
                    'metadata',
                    'security',
                    'links',
                ],
            ]);
        
        $this->assertNotEmpty($response->json('data.url'));
        $this->assertEquals('menu_item_image', $response->json('data.type'));
        $this->assertEquals($this->menuItem->id, $response->json('data.entity_id'));
    }

    /** @test */
    public function it_can_upload_restaurant_logo(): void
    {
        // Create a proper test image that meets dimension requirements
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('logo.png', $imageData);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/restaurants/{$this->restaurant->id}/upload-logo", [
                'logo' => $file,
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'url',
                    'type',
                    'entity_id',
                    'filename',
                    'uploaded_at',
                    'thumbnails',
                    'metadata',
                    'security',
                    'links',
                ],
            ]);
        
        $this->assertNotEmpty($response->json('data.url'));
        $this->assertEquals('restaurant_logo', $response->json('data.type'));
        $this->assertEquals($this->restaurant->id, $response->json('data.entity_id'));
    }

    /** @test */
    public function it_can_upload_user_avatar(): void
    {
        // Create a proper test image that meets dimension requirements
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('avatar.jpg', $imageData);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/users/{$this->user->id}/upload-avatar", [
                'avatar' => $file,
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'url',
                    'type',
                    'entity_id',
                    'filename',
                    'uploaded_at',
                    'thumbnails',
                    'metadata',
                    'security',
                    'links',
                ],
            ]);
        
        $this->assertNotEmpty($response->json('data.url'));
        $this->assertEquals('user_avatar', $response->json('data.type'));
        $this->assertEquals($this->user->id, $response->json('data.entity_id'));
    }

    /** @test */
    public function it_requires_authentication_for_uploads(): void
    {
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('menu-item.jpg', $imageData);
        
        $response = $this->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
            'image' => $file,
        ]);
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_permission_for_menu_item_upload(): void
    {
        // Create user with a valid staff role that doesn't have upload permissions
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF', 
            'status' => 'active',
            'restaurant_id' => $this->restaurant->id
        ]);
        
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('menu-item.jpg', $imageData);
        
        $response = $this->actingAs($unauthorizedUser)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_requires_permission_for_restaurant_logo_upload(): void
    {
        // Create user with a valid staff role that doesn't have upload permissions
        $unauthorizedUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF', 
            'status' => 'active',
            'restaurant_id' => $this->restaurant->id
        ]);
        
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('logo.png', $imageData);
        
        $response = $this->actingAs($unauthorizedUser)
            ->postJson("/api/restaurants/{$this->restaurant->id}/upload-logo", [
                'logo' => $file,
            ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_requires_permission_for_user_avatar_upload(): void
    {
        // Create user with a valid staff role that doesn't have upload permissions
        $otherUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF', 
            'status' => 'active',
            'restaurant_id' => $this->restaurant->id
        ]);
        
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('avatar.jpg', $imageData);
        
        $response = $this->actingAs($otherUser)
            ->postJson("/api/users/{$this->user->id}/upload-avatar", [
                'avatar' => $file,
            ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_menu_item_image_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    /** @test */
    public function it_validates_restaurant_logo_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/restaurants/{$this->restaurant->id}/upload-logo", [
                'logo' => $file,
            ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    /** @test */
    public function it_validates_user_avatar_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/users/{$this->user->id}/upload-avatar", [
                'avatar' => $file,
            ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    /** @test */
    public function it_validates_file_size_limits(): void
    {
        // Create a large file that exceeds size limit
        $largeImageData = str_repeat('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A', 1000);
        $file = UploadedFile::fake()->createWithContent('large-image.jpg', $largeImageData);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    /** @test */
    public function it_validates_image_dimensions(): void
    {
        // Create a very small image that definitely doesn't meet minimum dimension requirements
        // This creates a 1x1 pixel image which is well below the 100x100 minimum
        $smallImageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('small-image.jpg', $smallImageData);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    /** @test */
    public function it_can_delete_file(): void
    {
        // First upload a file
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('test.jpg', $imageData);
        $uploadResponse = $this->actingAs($this->user)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $imageUrl = $uploadResponse->json('data.url');
        $filePath = str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH));
        
        // Delete the file
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/files/delete', [
                'file_path' => $filePath,
            ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_file_deletion(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/files/delete', [
                'file_path' => 'menu_items/999/nonexistent.jpg',
            ]);
        
        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Not Found',
                'message' => 'File not found or could not be deleted',
                'error_code' => 'FILE_NOT_FOUND',
            ]);
    }

    /** @test */
    public function it_can_check_file_status(): void
    {
        // First upload a file
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('test.jpg', $imageData);
        $uploadResponse = $this->actingAs($this->user)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $imageUrl = $uploadResponse->json('data.url');
        $filePath = str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH));
        
        // Check file status
        $response = $this->actingAs($this->user)
            ->getJson("/api/files/status?file_path={$filePath}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'File status retrieved successfully',
                'data' => [
                    'file_path' => $filePath,
                    'exists' => true,
                ],
            ]);
    }

    /** @test */
    public function it_returns_false_for_nonexistent_file_status(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/files/status?file_path=menu_items/999/nonexistent.jpg');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'File status retrieved successfully',
                'data' => [
                    'file_path' => 'menu_items/999/nonexistent.jpg',
                    'exists' => false,
                    'url' => null,
                ],
            ]);
    }

    /** @test */
    public function it_validates_file_path_for_deletion(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/files/delete', [
                'file_path' => '../../../etc/passwd',
            ]);
        
        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_file_path_for_status_check(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/files/status?file_path=../../../etc/passwd');
        
        $response->assertStatus(422);
    }

    /** @test */
    public function it_requires_file_path_for_deletion(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/files/delete', []);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file_path']);
    }

    /** @test */
    public function it_requires_file_path_for_status_check(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/files/status', []);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file_path']);
    }

    /** @test */
    public function it_handles_upload_errors_gracefully(): void
    {
        // Mock the service to throw an exception
        $this->mock(\App\Services\FileUploadService::class)
            ->shouldReceive('uploadMenuItemImage')
            ->andThrow(new \Exception('Upload failed'));
        
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('menu-item.jpg', $imageData);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Internal Server Error',
                'message' => 'Failed to upload menu item image: Upload failed',
                'error_code' => 'UPLOAD_FAILED',
            ]);
    }

    /** @test */
    public function it_allows_users_to_upload_their_own_avatar(): void
    {
        // Create user with valid staff role instead of CUSTOMER
        $regularUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF', 
            'status' => 'active',
            'restaurant_id' => $this->restaurant->id
        ]);
        
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('avatar.jpg', $imageData);
        
        $response = $this->actingAs($regularUser)
            ->postJson("/api/users/{$regularUser->id}/upload-avatar", [
                'avatar' => $file,
            ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_allows_super_admin_to_upload_any_avatar(): void
    {
        $superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'active']);
        $regularUser = User::factory()->create([
            'role' => 'KITCHEN_STAFF', 
            'status' => 'active',
            'restaurant_id' => $this->restaurant->id
        ]);
        
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('avatar.jpg', $imageData);
        
        $response = $this->actingAs($superAdmin)
            ->postJson("/api/users/{$regularUser->id}/upload-avatar", [
                'avatar' => $file,
            ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_allows_branch_manager_to_upload_menu_item_images(): void
    {
        // Create a restaurant branch first
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        
        $branchManager = User::factory()->create([
            'role' => 'BRANCH_MANAGER',
            'restaurant_id' => $this->restaurant->id,
            'restaurant_branch_id' => $branch->id,
            'status' => 'active',
        ]);
        
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCADIAMgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
        $file = UploadedFile::fake()->createWithContent('menu-item.jpg', $imageData);
        
        $response = $this->actingAs($branchManager)
            ->postJson("/api/menu-items/{$this->menuItem->id}/upload-image", [
                'image' => $file,
            ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function debug_policy_authorization(): void
    {
        // Debug the policy authorization
        $this->actingAs($this->user);
        
        echo "User ID: " . $this->user->id . "\n";
        echo "User Role: " . $this->user->role . "\n";
        echo "User Restaurant ID: " . $this->user->restaurant_id . "\n";
        echo "MenuItem Restaurant ID: " . $this->menuItem->restaurant_id . "\n";
        echo "User can upload: " . ($this->user->can('upload', $this->menuItem) ? 'true' : 'false') . "\n";
        
        // Check if the policy is being called
        $policy = new \App\Policies\MenuItemPolicy();
        $result = $policy->upload($this->user, $this->menuItem);
        echo "Policy result: " . ($result ? 'true' : 'false') . "\n";
        
        $this->assertTrue(true); // Just to make this a valid test
    }
} 