# Webhook Service Documentation

## Overview

The Webhook Service provides real-time integration with external payment gateways and POS systems for FoodHub. This service handles payment confirmations, order status updates, and comprehensive logging for audit trails.

## Features

### 🔗 Payment Gateway Integration
- **MADA** - Saudi Arabia's national payment network
- **STC Pay** - Saudi Telecom Company's digital wallet
- **Apple Pay** - International digital wallet
- **Google Pay** - International digital wallet

### 🔐 Security Features
- **Signature Verification** - HMAC-based verification for each gateway
- **Data Sanitization** - Sensitive information removal before logging
- **Audit Trail** - Comprehensive logging for compliance
- **Rate Limiting** - Protection against abuse

### 📊 Monitoring & Analytics
- **Real-time Statistics** - Success/failure rates per gateway
- **Performance Tracking** - Response times and throughput
- **Failure Alerts** - Immediate notifications for issues
- **Compliance Logging** - Full audit trail for financial regulations

## Database Schema

### WebhookLog
```sql
CREATE TABLE webhook_logs (
    id BIGINT PRIMARY KEY,
    service VARCHAR(255),           -- mada, stc_pay, apple_pay, google_pay
    event_type VARCHAR(255),        -- payment_update, pos_update
    payload JSON,                   -- Sanitized webhook payload
    success BOOLEAN,
    ip_address VARCHAR(45),
    user_agent TEXT,
    signature_verified BOOLEAN,
    response_time_ms INTEGER,
    error_message TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### WebhookRegistration
```sql
CREATE TABLE webhook_registrations (
    id BIGINT PRIMARY KEY,
    service VARCHAR(255),
    event_type VARCHAR(255),
    webhook_url VARCHAR(255),
    webhook_id VARCHAR(255),        -- External service webhook ID
    signature_key TEXT,             -- For signature verification
    is_active BOOLEAN DEFAULT true,
    configuration JSON,             -- Additional service-specific config
    last_verified_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### WebhookStatistics
```sql
CREATE TABLE webhook_statistics (
    id BIGINT PRIMARY KEY,
    service VARCHAR(255),
    event_type VARCHAR(255),
    total_received INTEGER DEFAULT 0,
    successful_processed INTEGER DEFAULT 0,
    failed_processed INTEGER DEFAULT 0,
    average_response_time_ms INTEGER,
    last_received_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Payment
```sql
CREATE TABLE payments (
    id BIGINT PRIMARY KEY,
    order_id BIGINT REFERENCES orders(id),
    transaction_id VARCHAR(255) UNIQUE,
    gateway VARCHAR(255),           -- mada, stc_pay, apple_pay, google_pay
    status VARCHAR(255),            -- pending, completed, failed, refunded
    amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'SAR',
    paid_amount DECIMAL(10,2),
    paid_at TIMESTAMP,
    gateway_response JSON,
    error_message TEXT,
    payment_method VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## API Endpoints

### Payment Webhook Endpoints

#### POST `/api/webhook/payment/{gateway}`
Handles payment webhooks from external gateways.

**Parameters:**
- `gateway` - Payment gateway (mada, stc_pay, apple_pay, google_pay)

**Headers:**
- `X-Webhook-Signature` - HMAC signature for verification

**Request Body:**
```json
{
    "transaction_id": "txn_123456789",
    "status": "success",
    "amount": 150.00,
    "currency": "SAR",
    "error_message": null
}
```

**Response:**
```json
{
    "status": "success"
}
```

### Webhook Management Endpoints

#### POST `/api/webhook/register`
Register webhook with external service.

**Request Body:**
```json
{
    "service": "mada",
    "event": "payment_success",
    "url": "https://foodhub.com/api/webhook/payment/mada"
}
```

#### GET `/api/webhook/statistics`
Get webhook statistics.

**Query Parameters:**
- `service` - Filter by service
- `event_type` - Filter by event type

#### GET `/api/webhook/logs`
Get webhook logs.

**Query Parameters:**
- `service` - Filter by service
- `event_type` - Filter by event type
- `success` - Filter by success status
- `limit` - Number of records (default: 50)

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# MADA Configuration
MADA_MERCHANT_ID=your_merchant_id
MADA_API_KEY=your_api_key
MADA_WEBHOOK_SECRET=your_webhook_secret
MADA_ENVIRONMENT=sandbox

# STC Pay Configuration
STC_PAY_MERCHANT_ID=your_merchant_id
STC_PAY_API_KEY=your_api_key
STC_PAY_MERCHANT_KEY=your_merchant_key
STC_PAY_ENVIRONMENT=sandbox

# Apple Pay Configuration
APPLE_PAY_MERCHANT_ID=your_merchant_id
APPLE_PAY_API_KEY=your_api_key
APPLE_PAY_WEBHOOK_SECRET=your_webhook_secret
APPLE_PAY_ENVIRONMENT=sandbox

# Google Pay Configuration
GOOGLE_PAY_MERCHANT_ID=your_merchant_id
GOOGLE_PAY_API_KEY=your_api_key
GOOGLE_PAY_WEBHOOK_SECRET=your_webhook_secret
GOOGLE_PAY_ENVIRONMENT=sandbox
```

## Usage Examples

### Processing a Payment Webhook

```php
use App\Services\WebhookService;

$webhookService = app(WebhookService::class);

$payload = [
    'transaction_id' => 'txn_123456789',
    'status' => 'success',
    'amount' => 150.00,
    'currency' => 'SAR'
];

$signature = 'hmac_signature_from_gateway';

$webhookService->handlePaymentWebhook('mada', $payload, $signature);
```

### Registering a Webhook

```php
$success = $webhookService->registerWebhook(
    'mada',
    'payment_success',
    'https://foodhub.com/api/webhook/payment/mada'
);
```

### Getting Statistics

```php
use App\Models\WebhookStatistics;

$stats = WebhookStatistics::getStatistics('mada', 'payment_update');
$successRate = $stats->success_rate; // 95.5
```

## Payment Flow

### Successful Payment Flow
1. **Payment Gateway** → Sends webhook to FoodHub
2. **Signature Verification** → Verify webhook authenticity
3. **Payment Update** → Update payment status to completed
4. **Order Update** → Update order status to confirmed
5. **Loyalty Points** → Award points to customer
6. **Notifications** → Send confirmation to customer
7. **Security Log** → Log successful payment

### Failed Payment Flow
1. **Payment Gateway** → Sends webhook to FoodHub
2. **Signature Verification** → Verify webhook authenticity
3. **Payment Update** → Update payment status to failed
4. **Order Update** → Update order status to cancelled
5. **Notifications** → Send failure notification to customer
6. **Security Log** → Log failed payment

### Refund Flow
1. **Payment Gateway** → Sends webhook to FoodHub
2. **Signature Verification** → Verify webhook authenticity
3. **Payment Update** → Update payment status to refunded
4. **Order Update** → Update order status to refunded
5. **Notifications** → Send refund notification to customer
6. **Security Log** → Log refund

## Security Features

### Signature Verification
Each payment gateway uses different signature algorithms:

- **MADA**: HMAC-SHA256 with hex encoding
- **STC Pay**: SHA256 with merchant key
- **Apple Pay**: HMAC-SHA256 with base64 encoding
- **Google Pay**: HMAC-SHA256 with hex encoding

### Data Sanitization
Sensitive information is automatically removed before logging:
- Credit card numbers
- CVV codes
- API keys
- Tokens
- PINs

### Rate Limiting
Webhook endpoints are protected with rate limiting:
- `webhook` rate limit profile
- Prevents abuse and DoS attacks

## Monitoring & Alerting

### Statistics Tracking
- Total webhooks received per service/event
- Success/failure rates
- Average response times
- Last received timestamp

### Failure Alerts
- Immediate notifications for failed webhooks
- Detailed error information
- Investigation requirements

### Logging
- Comprehensive audit trail
- IP addresses and user agents
- Response times
- Error messages

## Testing

### Unit Tests
```bash
php artisan test tests/Unit/Services/WebhookServiceTest.php
```

### Test Coverage
- Signature verification
- Payment processing
- Error handling
- Statistics tracking
- Logging functionality

## Integration with Other Services

### Dependencies
- **NotificationService** - Send customer notifications
- **LoyaltyService** - Process loyalty points
- **SecurityLoggingService** - Log security events

### Database Models
- **Order** - Update order status
- **Payment** - Store payment information
- **WebhookLog** - Audit trail
- **WebhookStatistics** - Monitoring data

## Error Handling

### Common Exceptions
- `InvalidWebhookSignatureException` - Invalid signature
- `UnsupportedGatewayException` - Unknown gateway
- `UnsupportedServiceException` - Unknown service

### Error Responses
```json
{
    "error": "Invalid signature",
    "status": 401
}
```

## Best Practices

### Security
1. Always verify webhook signatures
2. Use HTTPS for all webhook endpoints
3. Sanitize data before logging
4. Implement rate limiting
5. Monitor for suspicious activity

### Reliability
1. Implement idempotency
2. Handle duplicate webhooks
3. Log all webhook events
4. Set up monitoring and alerting
5. Test webhook endpoints regularly

### Performance
1. Process webhooks asynchronously when possible
2. Use database transactions for data consistency
3. Implement proper indexing
4. Monitor response times
5. Scale horizontally if needed

## Troubleshooting

### Common Issues

#### Invalid Signature
- Check webhook secret configuration
- Verify signature algorithm
- Ensure payload format is correct

#### Unknown Transaction
- Check if payment record exists
- Verify transaction ID format
- Check database connectivity

#### High Failure Rate
- Monitor webhook logs
- Check external service status
- Verify network connectivity
- Review rate limiting settings

### Debug Commands
```bash
# Check webhook statistics
php artisan tinker
>>> App\Models\WebhookStatistics::all();

# Check recent webhook logs
>>> App\Models\WebhookLog::recent(24)->get();

# Test signature verification
>>> app(App\Services\WebhookService::class)->verifyWebhookSignature('mada', 'payload', 'signature');
```

## Future Enhancements

### Planned Features
- **POS Integration** - Handle POS webhooks (Square, Toast)
- **Webhook Retry** - Automatic retry for failed webhooks
- **Webhook Dashboard** - Admin interface for monitoring
- **Advanced Analytics** - Detailed performance metrics
- **Multi-tenant Support** - Support for multiple restaurants

### Scalability Considerations
- **Queue Processing** - Move webhook processing to queues
- **Database Sharding** - Distribute webhook logs across databases
- **CDN Integration** - Use CDN for webhook delivery
- **Microservices** - Split into separate webhook service 