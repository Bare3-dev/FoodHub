# API Versioning Implementation Guide

## Overview

This document outlines the complete API versioning system implemented for the FoodHub restaurant delivery platform. The system provides URL-based versioning with comprehensive backward compatibility, deprecation warnings, and migration guidance.

## Architecture

### Core Components

1. **ApiVersion Model** (`app/Models/ApiVersion.php`)
   - Manages version lifecycle (active, deprecated, sunset, beta)
   - Stores metadata including release dates, sunset dates, and migration guides
   - Provides helper methods for version status checks

2. **ApiVersionMiddleware** (`app/Http/Middleware/ApiVersionMiddleware.php`)
   - Extracts version from URL path (`/api/v1/*`, `/api/v2/*`)
   - Validates version existence and support
   - Handles sunset version responses
   - Logs version usage for analytics

3. **VersionDeprecationMiddleware** (`app/Http/Middleware/VersionDeprecationMiddleware.php`)
   - Adds deprecation warnings to responses
   - Includes migration guidance headers
   - Provides sunset notifications

4. **VersionAnalyticsMiddleware** (`app/Http/Middleware/VersionAnalyticsMiddleware.php`)
   - Tracks comprehensive version usage analytics
   - Monitors performance metrics and error rates
   - Collects migration progress data
   - Provides real-time monitoring capabilities

5. **ApiVersionNotificationService** (`app/Services/ApiVersionNotificationService.php`)
   - Manages multi-channel deprecation notifications
   - Sends email alerts with urgency-based templates
   - Provides dashboard notifications
   - Updates documentation automatically

6. **ApiVersionAnalyticsController** (`app/Http/Controllers/Api/ApiVersionAnalyticsController.php`)
   - Exposes analytics endpoints for monitoring
   - Provides migration progress insights
   - Offers real-time system health status
   - Generates risk assessments and recommendations

7. **Database Migration** (`database/migrations/2025_01_20_000000_create_api_versions_table.php`)
   - Stores version metadata and lifecycle information
   - Supports breaking changes documentation
   - Enables version-specific configurations

## Implementation Steps

### Step 1: Run Migrations and Seeders

```bash
# Run the migration
php artisan migrate

# Seed the initial API versions
php artisan db:seed --class=ApiVersionSeeder
```

### Step 2: Register Middleware

Add the middleware to your `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        // ... existing middleware
        \App\Http\Middleware\ApiVersionMiddleware::class,
        \App\Http\Middleware\VersionDeprecationMiddleware::class,
        \App\Http\Middleware\VersionAnalyticsMiddleware::class,
    ],
];
```

### Step 3: Update Routes

The routes have been restructured to support versioning:

- **Versioned routes**: `/api/v1/restaurants`, `/api/v2/restaurants`
- **Legacy routes**: `/api/restaurants` (maintains backward compatibility)
- **Migration helpers**: `/api/v1/migration/check`, `/api/v2/migration/check`

## Usage Examples

### Client Requests

#### Versioned Endpoints
```bash
# Explicit version request
GET /api/v1/restaurants
GET /api/v2/restaurants

# Legacy endpoint (defaults to v1)
GET /api/restaurants
```

#### Version Information
```bash
# Get current version info
GET /api/version

# Check migration requirements
GET /api/v1/migration/check
GET /api/v2/migration/check
```

#### Analytics and Monitoring
```bash
# Get comprehensive analytics
GET /api/v1/analytics
GET /api/v2/analytics

# Get specific version analytics
GET /api/v1/analytics/v1
GET /api/v2/analytics/v2

# Get migration progress
GET /api/v1/analytics/migration/progress

# Get real-time monitoring
GET /api/v1/analytics/monitoring/realtime
```

### Response Headers

#### Standard Headers
```
X-API-Version: v1
X-API-Version-Status: active
X-API-Migration-Guide: https://api.foodhub.com/api/docs/migration
```

#### Deprecation Headers (when applicable)
```
Deprecation: true
Sunset: Sat, 31 Dec 2025 23:59:59 GMT
Link: <v2>; rel="successor-version"
X-API-Deprecation-Notice: This API version will be sunset in 180 days...
```

## Version Lifecycle Management

### Version Statuses

1. **Active** (`active`)
   - Current stable version
   - Full support and features
   - Default version for new clients

2. **Beta** (`beta`)
   - Pre-release version
   - Limited support
   - Breaking changes expected

3. **Deprecated** (`deprecated`)
   - Version marked for removal
   - Deprecation warnings in responses
   - Migration guidance provided
   - Sunset date set

4. **Sunset** (`sunset`)
   - Version no longer supported
   - Returns 410 Gone status
   - Migration required

### Timeline Management

#### Critical Endpoints (12+ months support)
- Authentication/Authorization
- Core business operations (orders, payments)
- High-volume endpoints
- Public-facing integrations

#### Standard Endpoints (6 months support)
- Analytics and reporting
- Administrative functions
- Experimental features
- Low-usage endpoints

## Migration Strategy

### For Clients

1. **Immediate Actions**
   - Update API calls to use versioned endpoints (`/api/v1/*`)
   - Monitor deprecation warnings in response headers
   - Review migration guides for breaking changes

2. **Long-term Planning**
   - Plan migration to newer versions when available
   - Monitor sunset dates for deprecated versions
   - Implement version negotiation in client code

### For Developers

1. **Adding New Versions**
   ```php
   // Create new version record
   ApiVersion::create([
       'version' => 'v3',
       'status' => ApiVersion::STATUS_BETA,
       'release_date' => now(),
       'migration_guide_url' => '/api/docs/migration/v2-to-v3'
   ]);
   ```

2. **Deprecating Versions**
   ```php
   // Mark version as deprecated
   $version = ApiVersion::where('version', 'v1')->first();
   $version->update([
       'status' => ApiVersion::STATUS_DEPRECATED,
       'sunset_date' => now()->addMonths(6)
   ]);
   ```

## Testing

### Run Versioning Tests

```bash
# Run all API versioning tests
php artisan test tests/Feature/Api/ApiVersioningTest.php

# Run specific test
php artisan test --filter=it_accepts_v1_versioned_endpoints
```

### Test Scenarios

1. **Version Validation**
   - Valid versions (v1, v2)
   - Invalid versions (v999)
   - Missing versions (defaults to current)

2. **Deprecation Warnings**
   - Deprecated version responses
   - Sunset version handling
   - Migration guidance headers

3. **Backward Compatibility**
   - Legacy endpoint support
   - Version fallback behavior
   - Error handling

## Monitoring and Analytics

### Version Usage Tracking

The system automatically tracks:
- API version usage by restaurant
- Endpoint popularity per version
- Error rates by version
- Migration progress
- Response times and performance metrics
- Client distribution and usage patterns

### Key Metrics

1. **Adoption Rates**
   - New version adoption
   - Legacy version usage decline
   - Migration success rates
   - Version-specific client counts

2. **Performance Impact**
   - Response times by version
   - Error rates by version
   - Resource usage patterns
   - Endpoint performance trends

3. **Client Impact**
   - Breaking change incidents
   - Support ticket volume
   - Client satisfaction scores
   - Migration risk assessment

### Real-time Monitoring

The system provides:
- Live request monitoring
- Error rate trends
- System health status
- Performance alerts
- Migration progress tracking

### Automated Notifications

- **Email Alerts**: Urgency-based deprecation warnings
- **Dashboard Notifications**: Real-time status updates
- **Documentation Updates**: Automatic deprecation notices
- **Scheduled Reminders**: Periodic migration prompts

## Best Practices

### For API Consumers

1. **Always specify version explicitly**
   ```bash
   # Good
   GET /api/v1/restaurants
   
   # Avoid (may change behavior)
   GET /api/restaurants
   ```

2. **Monitor deprecation warnings**
   - Check response headers for warnings
   - Plan migrations before sunset dates
   - Test with newer versions early

3. **Implement version negotiation**
   ```javascript
   const response = await fetch('/api/v1/restaurants');
   const version = response.headers.get('X-API-Version');
   const isDeprecated = response.headers.get('Deprecation') === 'true';
   ```

### For API Developers

1. **Plan breaking changes carefully**
   - Document all changes in migration guides
   - Provide clear upgrade paths
   - Maintain backward compatibility when possible

2. **Communicate changes proactively**
   - Email notifications for major changes
   - Dashboard notifications for developers
   - Clear documentation updates

3. **Monitor version health**
   - Track usage patterns
   - Monitor error rates
   - Gather client feedback

## Troubleshooting

### Common Issues

1. **Version not found errors**
   - Check if version exists in database
   - Verify middleware registration
   - Clear application cache

2. **Deprecation warnings not showing**
   - Ensure VersionDeprecationMiddleware is registered
   - Check version status in database
   - Verify response header setting

3. **Legacy routes not working**
   - Check route registration order
   - Verify middleware stack
   - Test with explicit version

### Debug Commands

```bash
# Check current API versions
php artisan tinker
>>> App\Models\ApiVersion::all()->pluck('version', 'status');

# Clear version cache
php artisan cache:clear

# Test version detection
curl -H "Accept: application/json" http://localhost:8000/api/v1/version

# Send deprecation reminders (dry run)
php artisan api:send-deprecation-reminders --dry-run

# Send deprecation reminders
php artisan api:send-deprecation-reminders

# Check analytics data
php artisan tinker
>>> App\Http\Middleware\VersionAnalyticsMiddleware::getVersionAnalytics('v1');
>>> App\Http\Middleware\VersionAnalyticsMiddleware::getMigrationProgress();
```

## Future Enhancements

### Planned Features

1. **Automatic Version Detection**
   - Client capability negotiation
   - Smart version fallback
   - Version compatibility matrix

2. **Enhanced Analytics**
   - Real-time version usage dashboards
   - Predictive sunset recommendations
   - Client migration tracking

3. **Advanced Deprecation**
   - Gradual feature removal
   - A/B testing for new versions
   - Automated migration tools

### Version Roadmap

- **v1**: Current stable (2024-2025)
- **v2**: Enhanced features (2025+)
- **v3**: Future breaking changes (2026+)

## Support and Resources

### Documentation
- Migration guides: `/api/docs/migration`
- API reference: `/api/docs`
- Version comparison: `/api/docs/versions`

### Contact Information
- API Support: api-support@foodhub.com
- Developer Relations: dev@foodhub.com
- Emergency Issues: ops@foodhub.com

### Community Resources
- Developer forum: https://community.foodhub.com
- GitHub repository: https://github.com/foodhub/api
- Slack channel: #api-support

---

**Last Updated**: January 2025  
**Version**: 1.0  
**Maintainer**: API Team
