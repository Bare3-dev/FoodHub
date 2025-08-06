# FileUploadService Implementation

## Overview

The `FileUploadService` is a comprehensive file upload and media management system for the FoodHub Laravel API. It handles image uploads for menu items, restaurant logos, and user avatars with advanced features including image optimization, thumbnail generation, cloud storage integration, and security validation.

## Features

### Core Functionality
- **Multi-format support**: JPEG, JPG, PNG, WebP, GIF
- **Image optimization**: Automatic resizing and compression
- **Thumbnail generation**: Multiple sizes for different use cases
- **Cloud storage integration**: Google Cloud Storage ready
- **Security validation**: File type, size, and content validation
- **Comprehensive logging**: Upload success/failure tracking
- **Caching system**: Performance optimization for file variants
- **Cleanup utilities**: Automatic thumbnail cleanup on deletion

### Security Features
- **File type validation**: Whitelist of allowed MIME types
- **Size limits**: Context-specific file size restrictions
- **Content validation**: Malicious file detection
- **Path sanitization**: Directory traversal attack prevention
- **Access control**: Role-based upload permissions

## API Endpoints

### Menu Item Image Upload
```
POST /api/menu-items/{menuItem}/upload-image
Content-Type: multipart/form-data

Parameters:
- image: UploadedFile (required)
  - Max size: 5MB
  - Allowed types: jpeg, jpg, png, webp, gif
  - Min dimensions: 100x100
  - Max dimensions: 4000x4000
```

### Restaurant Logo Upload
```
POST /api/restaurants/{restaurant}/upload-logo
Content-Type: multipart/form-data

Parameters:
- logo: UploadedFile (required)
  - Max size: 2MB
  - Allowed types: jpeg, jpg, png, webp
  - Min dimensions: 100x100
  - Max dimensions: 2000x2000
```

### User Avatar Upload
```
POST /api/users/{user}/upload-avatar
Content-Type: multipart/form-data

Parameters:
- avatar: UploadedFile (required)
  - Max size: 1MB
  - Allowed types: jpeg, jpg, png, webp
  - Min dimensions: 100x100
  - Max dimensions: 1500x1500
```

### File Deletion
```
DELETE /api/files/delete

Parameters:
- file_path: string (required)
```

### File Status Check
```
GET /api/files/status

Parameters:
- file_path: string (required)
```

## Service Methods

### `uploadMenuItemImage(MenuItem $item, UploadedFile $file): string`

Uploads and processes menu item images with the following workflow:

1. **Validation**: File type, size, and content validation
2. **Storage**: Original file stored in cloud storage
3. **Thumbnail Generation**: Creates multiple sizes (small, medium, large)
4. **Database Update**: Updates menu item's images array
5. **Logging**: Records successful upload
6. **Return**: Public URL of uploaded image

**Features:**
- Generates thumbnails: 150x150, 300x300, 600x600
- Stores metadata: original URL, thumbnails, upload timestamp
- Organizes files by restaurant ID: `menu_items/{restaurant_id}/{filename}`

### `uploadRestaurantLogo(Restaurant $restaurant, UploadedFile $file): string`

Uploads restaurant logos with specialized processing:

1. **Validation**: Stricter size limits (2MB max)
2. **Storage**: Original file stored
3. **Logo Variants**: Creates header, favicon, and thumbnail versions
4. **Database Update**: Updates restaurant logo_url
5. **Caching**: Stores logo variants in cache
6. **Return**: Public URL of uploaded logo

**Features:**
- Generates logo variants: 200x200 (header), 32x32 (favicon), 100x100 (thumbnail)
- Caches variants for performance
- Updates restaurant model directly

### `uploadUserAvatar(User $user, UploadedFile $file): string`

Uploads user avatars with square cropping:

1. **Validation**: Size limit of 1MB
2. **Storage**: Original file stored
3. **Square Cropping**: Creates square variants (50x50, 150x150, 300x300)
4. **Database Update**: Updates user profile_image_url
5. **Caching**: Stores avatar variants in cache
6. **Return**: Public URL of uploaded avatar

**Features:**
- Automatic square cropping for consistent avatars
- Multiple square sizes for different UI contexts
- Secure storage with user-specific paths

### `deleteFile(string $filePath): bool`

Deletes files with comprehensive cleanup:

1. **Path Validation**: Security check for file path
2. **Existence Check**: Verifies file exists
3. **Deletion**: Removes original file
4. **Thumbnail Cleanup**: Removes all related thumbnails
5. **Cache Clearing**: Clears related caches
6. **Logging**: Records successful deletion

**Security Features:**
- Prevents directory traversal attacks
- Only allows deletion from authorized directories
- Comprehensive error handling

### `generateImageThumbnails(string $imagePath, array $sizes): array`

Generates thumbnails with custom sizes:

**Parameters:**
- `$imagePath`: Original image URL
- `$sizes`: Array of size configurations

**Returns:**
- Array of thumbnail URLs indexed by size name

**Features:**
- Maintains aspect ratios
- Optimizes for web delivery
- Graceful error handling
- Returns original if thumbnail generation fails

## File Organization

### Storage Structure
```
storage/app/public/
├── menu_items/
│   └── {restaurant_id}/
│       ├── menu_items_20250101_120000_abc123.jpg
│       ├── menu_items_20250101_120000_abc123_150x150.jpg
│       ├── menu_items_20250101_120000_abc123_300x300.jpg
│       └── menu_items_20250101_120000_abc123_600x600.jpg
├── restaurants/
│   └── {restaurant_id}/
│       └── logos/
│           ├── restaurant_logos_20250101_120000_def456.png
│           ├── restaurant_logos_20250101_120000_def456_200x200.png
│           ├── restaurant_logos_20250101_120000_def456_32x32.png
│           └── restaurant_logos_20250101_120000_def456_100x100.png
└── users/
    └── {user_id}/
        └── avatars/
            ├── user_avatars_20250101_120000_ghi789.jpg
            ├── user_avatars_20250101_120000_ghi789_square_50.jpg
            ├── user_avatars_20250101_120000_ghi789_square_150.jpg
            └── user_avatars_20250101_120000_ghi789_square_300.jpg
```

### Naming Convention
- **Format**: `{prefix}_{timestamp}_{random}.{extension}`
- **Example**: `menu_items_20250101_120000_abc123.jpg`
- **Components**:
  - `prefix`: Context-specific prefix (menu_items, restaurant_logos, user_avatars)
  - `timestamp`: YYYYMMDD_HHMMSS format
  - `random`: 8-character random string
  - `extension`: Original file extension

## Configuration

### File Size Limits
```php
private const MAX_FILE_SIZES = [
    'menu_item' => 5 * 1024 * 1024,    // 5MB
    'restaurant_logo' => 2 * 1024 * 1024, // 2MB
    'user_avatar' => 1 * 1024 * 1024,     // 1MB
];
```

### Allowed File Types
```php
private const ALLOWED_IMAGE_TYPES = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/webp',
    'image/gif',
];
```

### Thumbnail Sizes
```php
private const THUMBNAIL_SIZES = [
    'small' => [150, 150],
    'medium' => [300, 300],
    'large' => [600, 600],
    'original' => null,
];
```

## Security Features

### File Validation
- **MIME Type Check**: Whitelist of allowed image types
- **Size Validation**: Context-specific size limits
- **Content Validation**: Malicious file detection using Intervention Image
- **Dimension Validation**: Min/max width/height requirements

### Path Security
- **Directory Traversal Prevention**: Blocks `../` and `//` patterns
- **Authorized Directories**: Only allows operations in specific paths
- **Path Sanitization**: Validates file paths before operations

### Access Control
- **Role-based Permissions**: Different upload permissions per user role
- **Entity Ownership**: Users can only upload for entities they own/manage
- **Self-upload**: Users can upload their own avatars

## Error Handling

### Business Logic Exceptions
- `INVALID_FILE_TYPE`: Unsupported file format
- `FILE_SIZE_EXCEEDED`: File exceeds size limit
- `UPLOAD_FAILED`: General upload failure

### Security Exceptions
- `INVALID_FILE_PATH`: Malicious file path detected
- `CORRUPTED_FILE`: Invalid or corrupted image file

### Graceful Degradation
- Returns original image if thumbnail generation fails
- Logs errors without breaking application flow
- Provides meaningful error messages to users

## Performance Optimizations

### Caching Strategy
- **Logo Variants**: Cached for 1 hour
- **Avatar Variants**: Cached for 1 hour
- **Cache Keys**: `restaurant_logo_variants_{id}`, `user_avatar_variants_{id}`

### Storage Optimization
- **Image Compression**: Automatic optimization during resize
- **Thumbnail Generation**: On-demand creation
- **Cleanup**: Automatic thumbnail cleanup on deletion

### Database Efficiency
- **JSON Storage**: Menu item images stored as JSON array
- **Direct Updates**: Restaurant and user models updated directly
- **Minimal Queries**: Single update per upload

## Testing

### Unit Tests
- **Service Tests**: `tests/Unit/Services/FileUploadServiceTest.php`
- **Coverage**: All service methods and error scenarios
- **Mocking**: Storage and image processing mocks

### Feature Tests
- **Controller Tests**: `tests/Feature/Api/FileUploadControllerTest.php`
- **Coverage**: All API endpoints and validation
- **Authentication**: Permission and authorization tests

### Test Scenarios
- ✅ Successful uploads for all file types
- ✅ File validation (type, size, dimensions)
- ✅ Error handling and graceful degradation
- ✅ Security validation and path sanitization
- ✅ Permission checks and access control
- ✅ File deletion and cleanup
- ✅ Cache management and invalidation

## Dependencies

### Required Packages
```json
{
    "intervention/image": "^3.0"
}
```

### Laravel Features Used
- **Storage Facade**: File storage abstraction
- **Cache Facade**: Performance optimization
- **Log Facade**: Comprehensive logging
- **UploadedFile**: File upload handling
- **Form Requests**: Validation and authorization

## Usage Examples

### Basic Upload
```php
$fileUploadService = new FileUploadService();
$file = $request->file('image');

try {
    $imageUrl = $fileUploadService->uploadMenuItemImage($menuItem, $file);
    return response()->json(['url' => $imageUrl]);
} catch (BusinessLogicException $e) {
    return response()->json(['error' => $e->getMessage()], 422);
}
```

### Thumbnail Generation
```php
$sizes = [
    'small' => [150, 150],
    'medium' => [300, 300],
];
$thumbnails = $fileUploadService->generateImageThumbnails($imageUrl, $sizes);
```

### File Deletion
```php
$deleted = $fileUploadService->deleteFile('menu_items/1/image.jpg');
if ($deleted) {
    // File and thumbnails deleted successfully
}
```

## Future Enhancements

### Planned Features
- **Video Upload Support**: MP4, WebM formats
- **Document Upload**: PDF, DOC, DOCX for menus
- **Batch Upload**: Multiple file upload support
- **CDN Integration**: CloudFront, CloudFlare
- **Image Recognition**: Automatic tagging and categorization
- **Compression Optimization**: Advanced image compression algorithms

### Performance Improvements
- **Async Processing**: Queue-based thumbnail generation
- **Progressive Loading**: WebP and AVIF support
- **Lazy Loading**: On-demand thumbnail generation
- **Edge Caching**: Global CDN distribution

## Troubleshooting

### Common Issues

**File Upload Fails**
- Check file size limits
- Verify file type is allowed
- Ensure proper permissions
- Check storage disk configuration

**Thumbnails Not Generated**
- Verify Intervention Image is installed
- Check GD or Imagick extension
- Review error logs for processing failures

**Permission Denied**
- Verify user role and permissions
- Check entity ownership
- Review policy configurations

### Debug Information
- **Logs**: Check `storage/logs/laravel.log`
- **Storage**: Verify `storage/app/public` permissions
- **Cache**: Clear cache with `php artisan cache:clear`
- **Config**: Review `config/filesystems.php`

## API Response Format

### Success Response
```json
{
    "success": true,
    "message": "Menu item image uploaded successfully",
    "data": {
        "url": "https://example.com/storage/menu_items/1/image.jpg",
        "type": "menu_item_image",
        "entity_id": 1,
        "filename": "menu_items_20250101_120000_abc123.jpg",
        "uploaded_at": "2025-01-01T12:00:00.000000Z",
        "thumbnails": {
            "small": "https://example.com/storage/menu_items/1/image_150x150.jpg",
            "medium": "https://example.com/storage/menu_items/1/image_300x300.jpg",
            "large": "https://example.com/storage/menu_items/1/image_600x600.jpg"
        },
        "metadata": {
            "original_name": "menu-item.jpg",
            "extension": "jpg",
            "dimensions": {"width": 300, "height": 200},
            "file_size": 102400
        },
        "security": {
            "is_public": true,
            "access_control": "public_read",
            "virus_scan_status": "pending"
        },
        "links": {
            "self": "https://example.com/storage/menu_items/1/image.jpg",
            "delete": "https://example.com/api/files/delete?file_path=menu_items/1/image.jpg",
            "status": "https://example.com/api/files/status?file_path=menu_items/1/image.jpg"
        }
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "File size exceeds maximum limit of 5MB",
    "error_code": "FILE_SIZE_EXCEEDED",
    "context": {
        "file_size": 6291456,
        "max_size": 5242880,
        "context": "menu_item"
    }
}
``` 