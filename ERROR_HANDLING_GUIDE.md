# Error Handling & API Experience Guide

## 📋 Overview

This Laravel application implements comprehensive, professional error handling with consistent JSON responses, security-aware messaging, and integrated logging systems.

## 🛡️ Error Handling Architecture

### Exception Handler (`app/Exceptions/Handler.php`)
- **Consistent JSON Structure**: All API errors return standardized JSON responses
- **Environment-Aware**: Detailed errors in development, secure messages in production
- **Security Integration**: Automatic security incident logging for relevant errors
- **Request Tracking**: Unique request IDs for error tracking and debugging

### Custom Exception Classes

#### 1. BusinessLogicException (`app/Exceptions/BusinessLogicException.php`)
For domain-specific business rule violations:
```php
// Usage examples
throw BusinessLogicException::restaurantClosed('Pizza Palace', '09:00 AM');
throw BusinessLogicException::insufficientLoyaltyPoints(100, 50);
throw BusinessLogicException::menuItemUnavailable('Margherita Pizza', 'Out of cheese');
throw BusinessLogicException::deliveryZoneNotCovered('123 Remote Street');
```

#### 2. SecurityException (`app/Exceptions/SecurityException.php`)
For security violations with automatic logging:
```php
// Usage examples
throw SecurityException::suspiciousActivity('Multiple failed login attempts');
throw SecurityException::accountLocked($userId, 'Brute force protection');
throw SecurityException::dataAccessViolation('customer', $customerId);
throw SecurityException::mfaRequired();
```

### API Error Response Trait (`app/Traits/ApiErrorResponse.php`)
Standardized error response methods for controllers:
```php
// In controllers
use App\Traits\ApiErrorResponse;

return $this->validationErrorResponse($validator->errors());
return $this->notFoundResponse('Restaurant');
return $this->forbiddenResponse('Cannot access this order');
return $this->businessLogicErrorResponse('Restaurant is closed', 'RESTAURANT_CLOSED');
```

## 🔒 Security-Aware Error Messages

### Production vs Development
- **Production**: Minimal error details to prevent information disclosure
- **Development**: Detailed debugging information including stack traces
- **Database Errors**: Sanitized in production to prevent SQL structure exposure
- **Security Violations**: Always minimal exposure regardless of environment

### Sensitive Data Protection
- **Passwords**: Never included in error responses or logs
- **Tokens**: Automatically redacted from logs
- **Personal Information**: Filtered from error contexts
- **Payment Data**: Excluded from all error reporting

## 📊 Standardized Error Response Format

### Base Error Structure
```json
{
  "error": "Error Category",
  "message": "Human-readable error message",
  "error_code": "MACHINE_READABLE_CODE",
  "timestamp": "2023-12-07T10:30:00.000000Z",
  "request_id": "req_20231207_103000_A1B2C3D4"
}
```

### Validation Errors
```json
{
  "error": "Validation Failed",
  "message": "The provided data is invalid.",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  },
  "timestamp": "2023-12-07T10:30:00.000000Z",
  "request_id": "req_20231207_103000_A1B2C3D4"
}
```

### Business Logic Errors
```json
{
  "error": "Business Logic Error",
  "message": "Restaurant 'Pizza Palace' is currently closed.",
  "error_code": "RESTAURANT_CLOSED",
  "context": {
    "restaurant_name": "Pizza Palace",
    "reopen_time": "09:00 AM"
  },
  "timestamp": "2023-12-07T10:30:00.000000Z",
  "request_id": "req_20231207_103000_A1B2C3D4"
}
```

### Security Violations
```json
{
  "error": "Security Violation",
  "message": "Access denied due to security policy.",
  "error_code": "SUSPICIOUS_ACTIVITY",
  "timestamp": "2023-12-07T10:30:00.000000Z",
  "request_id": "req_20231207_103000_A1B2C3D4"
}
```

## 🚨 Error Categories & HTTP Status Codes

| Category | Status Code | Description | Example |
|----------|-------------|-------------|---------|
| **Validation Error** | 422 | Invalid input data | Missing required fields |
| **Authentication** | 401 | Authentication required/failed | Invalid token |
| **Authorization** | 403 | Insufficient permissions | Role-based access denied |
| **Not Found** | 404 | Resource doesn't exist | Restaurant ID not found |
| **Method Not Allowed** | 405 | HTTP method not supported | POST to GET-only endpoint |
| **Conflict** | 409 | Resource state conflict | Email already exists |
| **Business Logic** | 422 | Domain rule violation | Restaurant closed |
| **Rate Limiting** | 429 | Too many requests | Rate limit exceeded |
| **Security Violation** | 403/423 | Security policy violation | Suspicious activity |
| **Server Error** | 500 | Internal server error | Database connection failed |

## 🔄 Error Handling Flow

### 1. Exception Occurs
```php
// In controller or service
if ($restaurant->is_closed) {
    throw BusinessLogicException::restaurantClosed($restaurant->name);
}
```

### 2. Exception Handler Processing
- Determines exception type
- Generates appropriate response structure
- Logs security incidents if applicable
- Returns consistent JSON response

### 3. Security Logging Integration
- Authentication failures → Security incident log
- Authorization violations → Access attempt log
- Suspicious patterns → Threat detection log
- Database errors → Potential injection attempt log

### 4. Response Headers
- **Content-Type**: `application/json`
- **Retry-After**: For rate limiting errors
- **Request-ID**: For error tracking

## 🛠️ Implementation Examples

### Controller Error Handling
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiErrorResponse;
use App\Exceptions\BusinessLogicException;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiErrorResponse;

    public function store(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'restaurant_id' => 'required|exists:restaurants,id',
                'items' => 'required|array|min:1',
            ]);

            // Check business rules
            $restaurant = Restaurant::findOrFail($validated['restaurant_id']);
            if ($restaurant->is_closed) {
                throw BusinessLogicException::restaurantClosed($restaurant->name);
            }

            // Process order...
            $order = $this->orderService->createOrder($validated);

            return response()->json($order, 201);

        } catch (BusinessLogicException $e) {
            // Will be handled by exception handler automatically
            throw $e;
        } catch (\Exception $e) {
            // Generic error handling
            return $this->serverErrorResponse('Failed to create order');
        }
    }
}
```

### Service Layer Error Handling
```php
<?php

namespace App\Services;

use App\Exceptions\BusinessLogicException;
use App\Exceptions\SecurityException;

class OrderService
{
    public function createOrder(array $data): Order
    {
        // Check delivery zone
        if (!$this->isDeliveryAvailable($data['address'])) {
            throw BusinessLogicException::deliveryZoneNotCovered($data['address']);
        }

        // Check suspicious order patterns
        if ($this->detectSuspiciousOrderPattern($data)) {
            throw SecurityException::suspiciousActivity(
                'Unusual order pattern detected',
                ['order_data' => $data]
            );
        }

        // Create order...
    }
}
```

## 📝 Error Response Examples

### Successful Request
```http
POST /api/orders
Status: 201 Created

{
  "id": 123,
  "restaurant_id": 1,
  "status": "pending",
  "total": 25.50
}
```

### Validation Error
```http
POST /api/orders
Status: 422 Unprocessable Entity

{
  "error": "Validation Failed",
  "message": "The provided data is invalid.",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "restaurant_id": ["The restaurant id field is required."]
  },
  "timestamp": "2023-12-07T10:30:00.000000Z",
  "request_id": "req_20231207_103000_A1B2C3D4"
}
```

### Business Logic Error
```http
POST /api/orders
Status: 422 Unprocessable Entity

{
  "error": "Business Logic Error",
  "message": "Restaurant 'Pizza Palace' is currently closed.",
  "error_code": "RESTAURANT_CLOSED",
  "context": {
    "restaurant_name": "Pizza Palace",
    "reopen_time": "09:00 AM"
  },
  "timestamp": "2023-12-07T10:30:00.000000Z",
  "request_id": "req_20231207_103000_A1B2C3D4"
}
```

### Security Violation
```http
POST /api/orders
Status: 403 Forbidden

{
  "error": "Security Violation",
  "message": "Access denied due to security policy.",
  "error_code": "SUSPICIOUS_ACTIVITY",
  "timestamp": "2023-12-07T10:30:00.000000Z",
  "request_id": "req_20231207_103000_A1B2C3D4"
}
```

## 🔍 Error Tracking & Debugging

### Request IDs
Every error response includes a unique `request_id` for tracking:
- Format: `req_YYYYMMDD_HHMMSS_HASH`
- Used for log correlation and debugging
- Safe to share with frontend teams for troubleshooting

### Log Integration
- **Application Logs**: Standard Laravel logging with request IDs
- **Security Logs**: Automatic security incident logging via SecurityLoggingService
- **Error Correlation**: Request IDs link frontend errors to backend logs

### Production Debugging
1. **Frontend receives error response** with request ID
2. **Backend logs contain detailed information** linked by request ID
3. **Security incidents automatically logged** with appropriate severity
4. **Monitoring systems can alert** on error patterns

## 🚀 Best Practices

### For Developers
1. **Use custom exceptions** for business logic violations
2. **Include context** in error responses where helpful
3. **Never expose sensitive data** in error messages
4. **Use appropriate HTTP status codes** for different error types
5. **Log security-relevant errors** using SecurityException

### For Frontend Integration
1. **Always check `error_code`** for programmatic error handling
2. **Display user-friendly messages** based on error codes
3. **Include request ID** in support requests
4. **Handle rate limiting** with retry logic using `retry_after`
5. **Implement proper error states** in UI components

### For Production
1. **Monitor error rates** and patterns
2. **Set up alerts** for high-severity security incidents
3. **Regularly review** security logs for threats
4. **Implement log retention** policies for compliance
5. **Test error scenarios** in staging environments 