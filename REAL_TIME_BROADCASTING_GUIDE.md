# Real-Time Broadcasting System Guide

## Overview

This guide covers the implementation of Phase 1: Real-time Communication Infrastructure for the restaurant management system. The system provides real-time updates for orders, driver locations, delivery status, and kitchen display using Laravel's broadcasting system with Pusher protocol.

## Architecture

### Core Components

1. **Broadcasting Events**: Laravel events that implement `ShouldBroadcast`
2. **Channel Authorization**: Secure access control for private channels
3. **WebSocket Client**: JavaScript client for frontend real-time updates
4. **Service Integration**: Broadcasting integrated into existing services

### Technology Stack

- **Backend**: Laravel 12 with broadcasting system
- **WebSocket**: Pusher protocol (compatible with any WebSocket server)
- **Frontend**: JavaScript with Laravel Echo
- **Authentication**: Laravel Sanctum for channel authorization

## Events

### 1. OrderStatusUpdated

Broadcasts when order status changes.

**Channels:**
- `customer.{customerId}` - Customer receives updates about their orders
- `restaurant.{restaurantBranchId}` - Restaurant staff receive updates
- `driver.{driverId}` - Assigned driver receives updates

**Payload:**
```json
{
    "order_id": 123,
    "order_number": "ORD-001",
    "previous_status": "pending",
    "new_status": "preparing",
    "customer_id": 456,
    "restaurant_branch_id": 789,
    "driver_id": 101,
    "estimated_delivery_time": "2024-01-20T15:30:00Z",
    "timestamp": "2024-01-20T15:00:00Z",
    "version": "1.0"
}
```

### 2. DriverLocationUpdated

Broadcasts real-time driver location updates.

**Channels:**
- `driver.{driverId}` - Driver receives their own location updates
- `customer.{customerId}` - Customer receives driver location for their order
- `restaurant.{restaurantBranchId}` - Restaurant receives driver location

**Payload:**
```json
{
    "driver_id": 101,
    "driver_name": "John Doe",
    "location": {
        "latitude": 40.7128,
        "longitude": -74.0060,
        "accuracy": 10,
        "speed": 25,
        "heading": 90
    },
    "order_id": 123,
    "order_number": "ORD-001",
    "eta": "15 minutes",
    "timestamp": "2024-01-20T15:00:00Z",
    "version": "1.0"
}
```

### 3. NewOrderPlaced

Broadcasts when a new order is placed.

**Channels:**
- `restaurant.{restaurantBranchId}` - Restaurant receives new order notification
- `customer.{customerId}` - Customer receives order confirmation
- `kitchen.{restaurantBranchId}` - Kitchen display receives new order

**Payload:**
```json
{
    "order_id": 123,
    "order_number": "ORD-001",
    "customer_id": 456,
    "customer_name": "Jane Smith",
    "restaurant_branch_id": 789,
    "total_amount": 25.50,
    "order_type": "delivery",
    "estimated_preparation_time": 20,
    "items_count": 3,
    "special_instructions": "Extra cheese please",
    "timestamp": "2024-01-20T15:00:00Z",
    "version": "1.0"
}
```

### 4. DeliveryStatusChanged

Broadcasts delivery status changes.

**Channels:**
- `customer.{customerId}` - Customer receives delivery updates
- `restaurant.{restaurantBranchId}` - Restaurant receives delivery updates
- `driver.{driverId}` - Driver receives delivery updates

**Payload:**
```json
{
    "order_id": 123,
    "order_number": "ORD-001",
    "driver_id": 101,
    "driver_name": "John Doe",
    "previous_status": "assigned",
    "new_status": "picked_up",
    "customer_id": 456,
    "restaurant_branch_id": 789,
    "pickup_time": "2024-01-20T15:15:00Z",
    "timestamp": "2024-01-20T15:15:00Z",
    "version": "1.0"
}
```

### 5. KitchenOrderUpdated

Broadcasts kitchen display updates.

**Channels:**
- `kitchen.{restaurantBranchId}` - Kitchen staff receive updates
- `restaurant.{restaurantBranchId}` - Restaurant management receives updates

**Payload:**
```json
{
    "order_id": 123,
    "order_number": "ORD-001",
    "customer_name": "Jane Smith",
    "update_type": "status_changed",
    "priority": 4,
    "status": "preparing",
    "order_type": "delivery",
    "total_amount": 25.50,
    "estimated_preparation_time": 20,
    "items": [
        {
            "id": 1,
            "name": "Margherita Pizza",
            "quantity": 2,
            "special_instructions": "Extra cheese",
            "preparation_status": "in_progress"
        }
    ],
    "special_instructions": "Extra cheese please",
    "created_at": "2024-01-20T15:00:00Z",
    "timestamp": "2024-01-20T15:00:00Z",
    "version": "1.0"
}
```

## Channel Authorization

### Security Rules

1. **Customer Channels**: Users can only access their own customer channel
2. **Restaurant Channels**: Staff can only access their restaurant's channels
3. **Driver Channels**: Drivers can only access their own channels
4. **Kitchen Channels**: Kitchen staff can access their restaurant's kitchen channel
5. **Order Channels**: Access based on user role and order ownership

### Authorization Logic

```php
// Customer channels
Broadcast::channel('customer.{customerId}', function ($user, $customerId) {
    return $user->id == $customerId || $user->hasRole('admin');
});

// Restaurant channels
Broadcast::channel('restaurant.{restaurantBranchId}', function ($user, $restaurantBranchId) {
    if ($user->hasRole('admin')) {
        return true;
    }
    
    return $user->restaurant_branch_id == $restaurantBranchId;
});
```

## Frontend Integration

### JavaScript Client Setup

```javascript
import WebSocketManager from './websocket-client.js';

const wsManager = new WebSocketManager();

// Connect to customer channel
wsManager.connectToCustomerChannel(123);

// Connect to restaurant channel
wsManager.connectToRestaurantChannel(456);

// Listen for events
window.addEventListener('orderStatusUpdated', (event) => {
    console.log('Order status updated:', event.detail);
    updateOrderStatus(event.detail);
});

window.addEventListener('driverLocationUpdated', (event) => {
    console.log('Driver location updated:', event.detail);
    updateDriverLocation(event.detail);
});
```

### Event Handling

The WebSocket manager automatically handles:
- Channel connections and disconnections
- Event listening and dispatching
- Error handling and reconnection
- UI updates based on events

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Broadcasting Driver
BROADCAST_CONNECTION=pusher

# Pusher Configuration
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1

# Redis Configuration (if using Redis)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue Configuration
QUEUE_CONNECTION=redis
BROADCAST_QUEUE=broadcasting
```

### Service Provider Registration

Register the `BroadcastingServiceProvider` in `config/app.php`:

```php
'providers' => [
    // ... other providers
    App\Providers\BroadcastingServiceProvider::class,
],
```

## Testing

### Running Tests

```bash
# Run all broadcasting tests
php artisan test --testsuite=Feature --filter=Broadcasting

# Run specific test
php artisan test tests/Feature/Broadcasting/OrderBroadcastingTest.php
```

### Test Coverage

Tests cover:
- Event broadcasting
- Channel authorization
- Data payload validation
- Error handling
- Channel targeting

## Performance Considerations

### Optimization Strategies

1. **Rate Limiting**: Implement rate limiting for location updates
2. **Batch Updates**: Group multiple updates when possible
3. **Connection Pooling**: Reuse WebSocket connections
4. **Event Filtering**: Only broadcast essential updates

### Monitoring

Monitor these metrics:
- WebSocket connection count
- Event broadcast latency
- Channel subscription count
- Error rates

## Security Best Practices

1. **Authentication**: Always authenticate channel subscriptions
2. **Authorization**: Validate user permissions for each channel
3. **Data Sanitization**: Sanitize all broadcast data
4. **Rate Limiting**: Prevent abuse of broadcasting endpoints
5. **Logging**: Log all broadcasting activities for audit

## Troubleshooting

### Common Issues

1. **Connection Failures**
   - Check Pusher credentials
   - Verify network connectivity
   - Check firewall settings

2. **Authentication Errors**
   - Verify CSRF token
   - Check user authentication
   - Validate channel authorization

3. **Event Not Broadcasting**
   - Check event implementation
   - Verify channel names
   - Check broadcasting configuration

### Debug Commands

```bash
# Test broadcasting configuration
php artisan tinker
>>> broadcast(new App\Events\TestEvent());

# Check channel authorization
php artisan route:list | grep broadcasting
```

## Deployment

### Production Setup

1. **SSL Certificates**: Ensure HTTPS for secure WebSocket connections
2. **Load Balancing**: Configure load balancer for WebSocket traffic
3. **Monitoring**: Set up monitoring for broadcasting system health
4. **Backup**: Implement backup notification delivery methods

### Scaling Considerations

1. **Horizontal Scaling**: Use Redis for broadcasting across multiple servers
2. **Connection Limits**: Monitor and adjust WebSocket connection limits
3. **Queue Workers**: Scale queue workers for broadcasting jobs

## Support

For technical support:
1. Check the Laravel broadcasting documentation
2. Review the test suite for examples
3. Check application logs for error details
4. Verify environment configuration

## Future Enhancements

1. **Presence Channels**: Add user presence tracking
2. **Event Replay**: Implement event replay for offline users
3. **Advanced Analytics**: Add detailed broadcasting analytics
4. **Mobile Push**: Integrate with mobile push notifications
5. **Webhook Integration**: Add webhook support for external systems
