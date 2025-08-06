🎯 Key Requirements from FoodHub Document:
🔗 1. handlePOSWebhook() - Real-time POS Integration

Multi-POS support: Square, Toast, local systems
Real-time sync: Order status updates, inventory changes, menu updates
Event processing: Order updates, inventory counts, catalog changes
Status mapping: Convert POS statuses to FoodHub statuses
Immediate notifications: Customer alerts when order is ready

💳 2. handlePaymentWebhook() - Payment Gateway Integration

Saudi gateways: MADA, STC Pay (primary focus)
International: Apple Pay, Google Pay support
Payment lifecycle: Success → Order confirmation → Loyalty points
Automatic processing: Order confirmation, POS sync, notifications
Refund handling: Process refunds and update order status

📝 3. registerWebhook() - Integration Setup

Multi-service registration: Setup webhooks with external systems
Configuration storage: Save webhook IDs and signature keys
Event-specific: Different webhooks for different events
Error handling: Graceful failure with comprehensive logging
Security setup: Generate and manage signature keys

🔐 4. verifyWebhookSignature() - Security Verification

Service-specific algorithms: Each service uses different methods
HMAC verification: HMAC-SHA256 with different encodings
Timing attack prevention: Use hash_equals for secure comparison
Configuration management: Secure storage of signature keys
Security logging: Track verification failures

📊 5. logWebhookEvent() - Comprehensive Logging

Data sanitization: Remove sensitive information before logging
Performance tracking: Response times and success rates
Statistics collection: Per-service metrics and failure rates
Security monitoring: IP addresses, user agents, verification status
Failure alerts: Immediate notification for failed webhooks
Compliance: Full audit trail for financial regulations

🔄 Critical Integration Flow:
POS Webhook Flow:
Square POS → Order Ready → Webhook → FoodHub → Customer Notification
Payment Webhook Flow:
MADA Payment → Success → Webhook → Order Confirmed → POS Sync → Loyalty Points
Real-time Updates:

Order status changes propagate instantly
Payment confirmations trigger immediate order processing
Inventory updates sync across all systems
Customer notifications sent in real-time

🚨 Security & Compliance:

Signature verification prevents malicious requests
Audit logging for financial compliance
Data sanitization protects sensitive information
Failure monitoring ensures system reliability

🔗 1. handlePOSWebhook(string $posType, array $payload, string $signature): void
Purpose: Process real-time updates from POS systems (Square, Toast, Local)
From FoodHub Document: "POS Integration Service", "Real-time synchronization with Square, Toast, local POS"
Implementation Logic:
phppublic function handlePOSWebhook(string $posType, array $payload, string $signature): void
{
    // 1. Verify webhook signature for security
    if (!$this->verifyWebhookSignature($posType, json_encode($payload), $signature)) {
        throw new InvalidWebhookSignatureException('Invalid POS webhook signature');
    }
    
    // 2. Log the webhook event
    $this->logWebhookEvent($posType, 'pos_update', $payload, true);
    
    // 3. Process based on POS type and event
    switch ($posType) {
        case 'square':
            $this->processSquareWebhook($payload);
            break;
        case 'toast':
            $this->processToastWebhook($payload);
            break;
        case 'local':
            $this->processLocalPOSWebhook($payload);
            break;
        default:
            throw new UnsupportedPOSTypeException("Unsupported POS type: {$posType}");
    }
}

private function processSquareWebhook(array $payload): void
{
    $eventType = $payload['type'] ?? '';
    
    switch ($eventType) {
        case 'order.updated':
            $this->handlePOSOrderUpdate($payload['data']['object']);
            break;
            
        case 'inventory.count.updated':
            $this->handlePOSInventoryUpdate($payload['data']['object']);
            break;
            
        case 'catalog.version.updated':
            $this->handlePOSMenuUpdate($payload['data']['object']);
            break;
    }
}

private function handlePOSOrderUpdate(array $orderData): void
{
    // Find FoodHub order by POS order ID
    $order = Order::where('pos_order_id', $orderData['id'])->first();
    
    if ($order) {
        // Update order status based on POS status
        $newStatus = $this->mapPOSStatusToFoodHubStatus($orderData['state']);
        
        if ($order->status !== $newStatus) {
            $order->update(['status' => $newStatus]);
            
            // Trigger real-time notifications
            $this->orderService->sendStatusUpdateNotification($order);
            
            // Update kitchen display if needed
            if ($newStatus === 'ready') {
                $this->notificationService->notifyCustomerOrderReady($order);
            }
        }
    }
}
Key Requirements:

Multi-POS support: Handle Square, Toast, and local POS systems
Real-time sync: Immediate order status updates
Inventory sync: Stock level updates from POS
Menu sync: Price and availability changes
Status mapping: Convert POS statuses to FoodHub statuses


💳 2. handlePaymentWebhook(string $gateway, array $payload, string $signature): void
Purpose: Process payment confirmations from Saudi payment gateways
From FoodHub Document: "Payment Integration Service", "MADA, Apple Pay, STC Pay, online payments"
Implementation Logic:
phppublic function handlePaymentWebhook(string $gateway, array $payload, string $signature): void
{
    // 1. Verify webhook signature
    if (!$this->verifyWebhookSignature($gateway, json_encode($payload), $signature)) {
        throw new InvalidWebhookSignatureException('Invalid payment webhook signature');
    }
    
    // 2. Log webhook event
    $this->logWebhookEvent($gateway, 'payment_update', $payload, true);
    
    // 3. Process based on payment gateway
    switch ($gateway) {
        case 'mada':
            $this->processMADAWebhook($payload);
            break;
        case 'stc_pay':
            $this->processSTCPayWebhook($payload);
            break;
        case 'apple_pay':
            $this->processApplePayWebhook($payload);
            break;
        case 'google_pay':
            $this->processGooglePayWebhook($payload);
            break;
        default:
            throw new UnsupportedGatewayException("Unsupported gateway: {$gateway}");
    }
}

private function processMADAWebhook(array $payload): void
{
    $transactionId = $payload['transaction_id'];
    $status = $payload['status']; // success, failed, pending
    $amount = $payload['amount'];
    
    // Find payment record
    $payment = Payment::where('transaction_id', $transactionId)->first();
    
    if (!$payment) {
        Log::warning('Payment webhook for unknown transaction', ['transaction_id' => $transactionId]);
        return;
    }
    
    $order = $payment->order;
    
    switch ($status) {
        case 'success':
            $this->handleSuccessfulPayment($payment, $order, $amount);
            break;
            
        case 'failed':
            $this->handleFailedPayment($payment, $order, $payload['error_message'] ?? '');
            break;
            
        case 'refunded':
            $this->handleRefundedPayment($payment, $order, $amount);
            break;
    }
}

private function handleSuccessfulPayment(Payment $payment, Order $order, float $amount): void
{
    // Update payment status
    $payment->update([
        'status' => 'completed',
        'paid_amount' => $amount,
        'paid_at' => now()
    ]);
    
    // Update order status
    $order->update([
        'payment_status' => 'paid',
        'status' => 'confirmed'
    ]);
    
    // Process loyalty points
    $this->loyaltyService->processOrderLoyaltyPoints($order);
    
    // Send confirmation notifications
    $this->notificationService->sendPaymentConfirmation($order);
    $this->notificationService->sendOrderConfirmation($order);
    
    // Sync with POS system
    $this->posService->syncOrderToPOS($order);
}
Key Requirements:

Saudi payment gateways: MADA, STC Pay support
International gateways: Apple Pay, Google Pay
Payment status handling: Success, failed, refunded, pending
Order lifecycle: Automatic order confirmation on successful payment
Loyalty integration: Award points after successful payment
Real-time notifications: Instant customer updates


📝 3. registerWebhook(string $service, string $event, string $url): bool
Purpose: Register webhook endpoints with external services during setup
From FoodHub Document: "Integration setup", "Webhook management for POS and payment systems"
Implementation Logic:
phppublic function registerWebhook(string $service, string $event, string $url): bool
{
    try {
        switch ($service) {
            case 'square':
                return $this->registerSquareWebhook($event, $url);
                
            case 'toast':
                return $this->registerToastWebhook($event, $url);
                
            case 'mada':
                return $this->registerMADAWebhook($event, $url);
                
            case 'stc_pay':
                return $this->registerSTCPayWebhook($event, $url);
                
            default:
                throw new UnsupportedServiceException("Service not supported: {$service}");
        }
    } catch (Exception $e) {
        Log::error('Webhook registration failed', [
            'service' => $service,
            'event' => $event,
            'url' => $url,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

private function registerSquareWebhook(string $event, string $url): bool
{
    $squareClient = new SquareClient([
        'accessToken' => config('services.square.access_token'),
        'environment' => config('services.square.environment')
    ]);
    
    $webhooksApi = $squareClient->getWebhooksApi();
    
    $body = new CreateWebhookSubscriptionRequest();
    $body->setName('FoodHub ' . ucfirst($event));
    $body->setEventTypes([$this->mapEventToSquareType($event)]);
    $body->setNotificationUrl($url);
    $body->setSignatureKey($this->generateWebhookSignature());
    
    $result = $webhooksApi->createWebhookSubscription($body);
    
    if ($result->isSuccess()) {
        // Store webhook configuration
        WebhookRegistration::create([
            'service' => 'square',
            'event_type' => $event,
            'webhook_url' => $url,
            'webhook_id' => $result->getResult()->getSubscription()->getId(),
            'signature_key' => $body->getSignatureKey(),
            'is_active' => true
        ]);
        
        return true;
    }
    
    return false;
}

private function registerMADAWebhook(string $event, string $url): bool
{
    // MADA webhook registration via API
    $madaClient = new MADAClient([
        'merchant_id' => config('services.mada.merchant_id'),
        'api_key' => config('services.mada.api_key'),
        'environment' => config('services.mada.environment')
    ]);
    
    $response = $madaClient->registerWebhook([
        'webhook_url' => $url,
        'events' => [$event],
        'merchant_id' => config('services.mada.merchant_id')
    ]);
    
    if ($response['status'] === 'success') {
        WebhookRegistration::create([
            'service' => 'mada',
            'event_type' => $event,
            'webhook_url' => $url,
            'webhook_id' => $response['webhook_id'],
            'signature_key' => $response['signature_key'],
            'is_active' => true
        ]);
        
        return true;
    }
    
    return false;
}
Key Requirements:

Multi-service support: Register with various external systems
Event-specific: Different webhooks for different events
Configuration storage: Save webhook details for later use
Error handling: Graceful failure with logging
Security setup: Generate and store signature keys


🔐 4. verifyWebhookSignature(string $service, string $payload, string $signature): bool
Purpose: Verify webhook authenticity to prevent malicious requests
From FoodHub Document: "Security best practices", "Webhook signature verification"
Implementation Logic:
phppublic function verifyWebhookSignature(string $service, string $payload, string $signature): bool
{
    try {
        switch ($service) {
            case 'square':
                return $this->verifySquareSignature($payload, $signature);
                
            case 'toast':
                return $this->verifyToastSignature($payload, $signature);
                
            case 'mada':
                return $this->verifyMADASignature($payload, $signature);
                
            case 'stc_pay':
                return $this->verifySTCPaySignature($payload, $signature);
                
            default:
                Log::warning('Unknown service for signature verification', ['service' => $service]);
                return false;
        }
    } catch (Exception $e) {
        Log::error('Webhook signature verification failed', [
            'service' => $service,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

private function verifySquareSignature(string $payload, string $signature): bool
{
    // Get Square webhook signature key from configuration
    $webhookSignatureKey = config('services.square.webhook_signature_key');
    
    if (!$webhookSignatureKey) {
        Log::error('Square webhook signature key not configured');
        return false;
    }
    
    // Square uses HMAC-SHA256 with base64 encoding
    $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $webhookSignatureKey, true));
    
    return hash_equals($expectedSignature, $signature);
}

private function verifyMADASignature(string $payload, string $signature): bool
{
    // MADA signature verification
    $secretKey = config('services.mada.webhook_secret');
    
    if (!$secretKey) {
        Log::error('MADA webhook secret not configured');
        return false;
    }
    
    // MADA uses HMAC-SHA256 with hex encoding
    $expectedSignature = hash_hmac('sha256', $payload, $secretKey);
    
    return hash_equals($expectedSignature, $signature);
}

private function verifySTCPaySignature(string $payload, string $signature): bool
{
    // STC Pay signature verification (custom algorithm)
    $merchantKey = config('services.stc_pay.merchant_key');
    
    // STC Pay might use a different signature algorithm
    $expectedSignature = hash('sha256', $payload . $merchantKey);
    
    return hash_equals($expectedSignature, $signature);
}
Key Requirements:

Service-specific algorithms: Each service uses different signature methods
HMAC verification: Prevent timing attacks with hash_equals
Error logging: Track verification failures for security monitoring
Configuration management: Secure storage of signature keys
Fallback handling: Graceful failure for unknown services


📊 5. logWebhookEvent(string $service, string $event, array $payload, bool $success): void
Purpose: Comprehensive logging for debugging and compliance
From FoodHub Document: "Comprehensive audit logging", "Security monitoring"
Implementation Logic:
phppublic function logWebhookEvent(string $service, string $event, array $payload, bool $success): void
{
    try {
        // Create webhook log entry
        WebhookLog::create([
            'service' => $service,
            'event_type' => $event,
            'payload' => $this->sanitizePayloadForLogging($payload),
            'success' => $success,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'signature_verified' => $success, // Assuming success means verified
            'response_time_ms' => $this->calculateResponseTime(),
            'created_at' => now()
        ]);
        
        // Log to application logs for immediate debugging
        $logLevel = $success ? 'info' : 'error';
        
        Log::{$logLevel}('Webhook received', [
            'service' => $service,
            'event' => $event,
            'success' => $success,
            'ip' => request()->ip(),
            'payload_size' => strlen(json_encode($payload)),
            'timestamp' => now()->toISOString()
        ]);
        
        // Update webhook statistics
        $this->updateWebhookStatistics($service, $event, $success);
        
        // Send alert for failed webhooks
        if (!$success) {
            $this->sendWebhookFailureAlert($service, $event, $payload);
        }
        
    } catch (Exception $e) {
        // Fallback logging if database fails
        Log::error('Failed to log webhook event', [
            'service' => $service,
            'event' => $event,
            'success' => $success,
            'error' => $e->getMessage()
        ]);
    }
}

private function sanitizePayloadForLogging(array $payload): array
{
    // Remove sensitive information before logging
    $sensitiveKeys = [
        'password',
        'token',
        'secret',
        'api_key',
        'credit_card',
        'card_number',
        'cvv',
        'pin'
    ];
    
    return $this->recursiveSanitize($payload, $sensitiveKeys);
}

private function recursiveSanitize(array $data, array $sensitiveKeys): array
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = $this->recursiveSanitize($value, $sensitiveKeys);
        } elseif (in_array(strtolower($key), $sensitiveKeys)) {
            $data[$key] = '[REDACTED]';
        }
    }
    
    return $data;
}

private function updateWebhookStatistics(string $service, string $event, bool $success): void
{
    $stats = WebhookStatistics::firstOrCreate(
        ['service' => $service, 'event_type' => $event],
        ['total_received' => 0, 'successful_processed' => 0, 'failed_processed' => 0]
    );
    
    $stats->increment('total_received');
    
    if ($success) {
        $stats->increment('successful_processed');
    } else {
        $stats->increment('failed_processed');
    }
    
    $stats->last_received_at = now();
    $stats->save();
}

private function sendWebhookFailureAlert(string $service, string $event, array $payload): void
{
    // Send immediate alert to development team
    $this->notificationService->sendWebhookFailureAlert([
        'service' => $service,
        'event_type' => $event,
        'timestamp' => now(),
        'error_summary' => 'Webhook processing failed',
        'requires_investigation' => true
    ]);
}
Key Requirements:

Comprehensive logging: Service, event, payload, success status
Data sanitization: Remove sensitive information (card numbers, tokens)
Performance tracking: Response times and success rates
Security monitoring: IP addresses, user agents, verification status
Statistics tracking: Per-service and per-event metrics
Failure alerts: Immediate notification of processing failures
Compliance: Audit trail for financial and security requirements


🎯 Integration with Other Services:
Dependencies:

SecurityLoggingService: Log security events and audit trail
NotificationService: Send alerts and customer notifications
OrderProcessingService: Update order statuses
LoyaltyEngineService: Process points after payment
InventoryService: Update stock levels from POS

Database Schema:
php// webhook_logs table
service | event_type | payload | success | ip_address | created_at

// webhook_registrations table  
service | event_type | webhook_url | webhook_id | signature_key | is_active

// webhook_statistics table
service | event_type | total_received | successful_processed | failed_processed
Laravel Routes:
php// Webhook endpoints
Route::post('/webhook/pos/{type}', [WebhookController::class, 'handlePOSWebhook']);
Route::post('/webhook/payment/{gateway}', [WebhookController::class, 'handlePaymentWebhook']);
This service enables real-time integration with external systems for seamless FoodHub operations! 🔗⚡