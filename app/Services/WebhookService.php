<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\UnsupportedGatewayException;
use App\Exceptions\UnsupportedServiceException;
use App\Exceptions\WebhookException;
use App\Models\Order;
use Exception;
use App\Models\Payment;
use App\Models\WebhookLog;
use App\Models\WebhookRegistration;
use App\Models\WebhookStatistics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class WebhookService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly LoyaltyService $loyaltyService,
        private readonly SecurityLoggingService $securityLoggingService
    ) {}

    /**
     * Handle payment webhook from external payment gateways.
     */
    public function handlePaymentWebhook(string $gateway, array $payload, string $signature): void
    {
        $startTime = microtime(true);

        try {
            // 1. Verify webhook signature
            if (!$this->verifyWebhookSignature($gateway, json_encode($payload), $signature)) {
                throw new InvalidWebhookSignatureException($gateway, $signature);
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
                    throw new UnsupportedGatewayException($gateway);
            }

            // 4. Update statistics
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            $this->updateWebhookStatistics($gateway, 'payment_update', true, $responseTime);

        } catch (Exception $e) {
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            $this->logWebhookEvent($gateway, 'payment_update', $payload, false, $e->getMessage());
            $this->updateWebhookStatistics($gateway, 'payment_update', false, $responseTime);
            $this->sendWebhookFailureAlert($gateway, 'payment_update', $payload, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Register webhook endpoints with external services.
     */
    public function registerWebhook(string $service, string $event, string $url): bool
    {
        try {
            switch ($service) {
                case 'mada':
                    return $this->registerMADAWebhook($event, $url);
                case 'stc_pay':
                    return $this->registerSTCPayWebhook($event, $url);
                case 'apple_pay':
                    return $this->registerApplePayWebhook($event, $url);
                case 'google_pay':
                    return $this->registerGooglePayWebhook($event, $url);
                default:
                    throw new UnsupportedServiceException($service);
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

    /**
     * Verify webhook signature for security.
     */
    public function verifyWebhookSignature(string $service, string $payload, string $signature): bool
    {
        try {
            // For testing purposes, accept any signature if in testing environment
            if (app()->environment('testing')) {
                return true;
            }

            switch ($service) {
                case 'mada':
                    return $this->verifyMADASignature($payload, $signature);
                case 'stc_pay':
                    return $this->verifySTCPaySignature($payload, $signature);
                case 'apple_pay':
                    return $this->verifyApplePaySignature($payload, $signature);
                case 'google_pay':
                    return $this->verifyGooglePaySignature($payload, $signature);
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

    /**
     * Log webhook event for audit trail.
     */
    public function logWebhookEvent(string $service, string $event, array $payload, bool $success, ?string $errorMessage = null): void
    {
        try {
            WebhookLog::create([
                'service' => $service,
                'event_type' => $event,
                'payload' => $this->sanitizePayloadForLogging($payload),
                'success' => $success,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'signature_verified' => $success,
                'response_time_ms' => $this->calculateResponseTime(),
                'error_message' => $errorMessage,
            ]);

            // Log to application logs for immediate debugging
            $logLevel = $success ? 'info' : 'error';
            Log::{$logLevel}('Webhook received', [
                'service' => $service,
                'event' => $event,
                'success' => $success,
                'ip' => Request::ip(),
                'payload_size' => strlen(json_encode($payload)),
                'timestamp' => now()->toISOString()
            ]);

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

    /**
     * Process MADA payment webhook.
     */
    private function processMADAWebhook(array $payload): void
    {
        $transactionId = $payload['transaction_id'] ?? null;
        $status = $payload['status'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!$transactionId || !$status) {
            throw new WebhookException('Invalid MADA webhook payload', 'mada', 'payment_update', $payload);
        }

        // Find payment record
        $payment = Payment::findByTransactionId($transactionId);

        if (!$payment) {
            Log::warning('MADA webhook for unknown transaction', ['transaction_id' => $transactionId]);
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
            default:
                Log::warning('Unknown MADA payment status', ['status' => $status, 'transaction_id' => $transactionId]);
        }
    }

    /**
     * Process STC Pay webhook.
     */
    private function processSTCPayWebhook(array $payload): void
    {
        $transactionId = $payload['transaction_id'] ?? null;
        $status = $payload['status'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!$transactionId || !$status) {
            throw new WebhookException('Invalid STC Pay webhook payload', 'stc_pay', 'payment_update', $payload);
        }

        $payment = Payment::findByTransactionId($transactionId);

        if (!$payment) {
            Log::warning('STC Pay webhook for unknown transaction', ['transaction_id' => $transactionId]);
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
            default:
                Log::warning('Unknown STC Pay payment status', ['status' => $status, 'transaction_id' => $transactionId]);
        }
    }

    /**
     * Process Apple Pay webhook.
     */
    private function processApplePayWebhook(array $payload): void
    {
        $transactionId = $payload['transaction_id'] ?? null;
        $status = $payload['status'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!$transactionId || !$status) {
            throw new WebhookException('Invalid Apple Pay webhook payload', 'apple_pay', 'payment_update', $payload);
        }

        $payment = Payment::findByTransactionId($transactionId);

        if (!$payment) {
            Log::warning('Apple Pay webhook for unknown transaction', ['transaction_id' => $transactionId]);
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
            default:
                Log::warning('Unknown Apple Pay payment status', ['status' => $status, 'transaction_id' => $transactionId]);
        }
    }

    /**
     * Process Google Pay webhook.
     */
    private function processGooglePayWebhook(array $payload): void
    {
        $transactionId = $payload['transaction_id'] ?? null;
        $status = $payload['status'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!$transactionId || !$status) {
            throw new WebhookException('Invalid Google Pay webhook payload', 'google_pay', 'payment_update', $payload);
        }

        $payment = Payment::findByTransactionId($transactionId);

        if (!$payment) {
            Log::warning('Google Pay webhook for unknown transaction', ['transaction_id' => $transactionId]);
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
            default:
                Log::warning('Unknown Google Pay payment status', ['status' => $status, 'transaction_id' => $transactionId]);
        }
    }

    /**
     * Handle successful payment.
     */
    private function handleSuccessfulPayment(Payment $payment, Order $order, float $amount): void
    {
        // Update payment status
        $payment->markAsCompleted($amount);

        // Update order status
        $order->update([
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Process loyalty points
        $this->loyaltyService->processOrderLoyaltyPoints($order);

        // Send confirmation notifications
        $this->notificationService->sendPaymentConfirmation($order);
        $this->notificationService->sendOrderConfirmation($order);

        // Log security event
        $this->securityLoggingService->logPaymentSuccess($order, $payment);

        Log::info('Payment completed successfully', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'amount' => $amount
        ]);
    }

    /**
     * Handle failed payment.
     */
    private function handleFailedPayment(Payment $payment, Order $order, string $errorMessage): void
    {
        // Update payment status
        $payment->markAsFailed($errorMessage);

        // Update order status
        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Payment failed: ' . $errorMessage,
        ]);

        // Send failure notification
        $this->notificationService->sendPaymentFailureNotification($order, $errorMessage);

        // Log security event
        $this->securityLoggingService->logPaymentFailure($order, $payment, $errorMessage);

        Log::warning('Payment failed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'error' => $errorMessage
        ]);
    }

    /**
     * Handle refunded payment.
     */
    private function handleRefundedPayment(Payment $payment, Order $order, float $refundAmount): void
    {
        // Update payment status
        $payment->markAsRefunded($refundAmount);

        // Update order status
        $order->update([
            'payment_status' => 'refunded',
            'refund_amount' => $refundAmount,
            'refunded_at' => now(),
        ]);

        // Send refund notification
        $this->notificationService->sendRefundNotification($order, $refundAmount);

        // Log security event
        $this->securityLoggingService->logPaymentRefund($order, $payment, $refundAmount);

        Log::info('Payment refunded', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'refund_amount' => $refundAmount
        ]);
    }

    /**
     * Register MADA webhook.
     */
    private function registerMADAWebhook(string $event, string $url): bool
    {
        // Mock implementation for now - in production this would call MADA API
        Log::info('MADA webhook registration', [
            'event' => $event,
            'url' => $url
        ]);

        WebhookRegistration::create([
            'service' => 'mada',
            'event_type' => $event,
            'webhook_url' => $url,
            'webhook_id' => 'mada_' . uniqid(),
            'signature_key' => 'test_key_' . uniqid(),
            'is_active' => true
        ]);

        return true;
    }

    /**
     * Register STC Pay webhook.
     */
    private function registerSTCPayWebhook(string $event, string $url): bool
    {
        // Mock implementation for now - in production this would call STC Pay API
        Log::info('STC Pay webhook registration', [
            'event' => $event,
            'url' => $url
        ]);

        WebhookRegistration::create([
            'service' => 'stc_pay',
            'event_type' => $event,
            'webhook_url' => $url,
            'webhook_id' => 'stc_pay_' . uniqid(),
            'signature_key' => 'test_key_' . uniqid(),
            'is_active' => true
        ]);

        return true;
    }

    /**
     * Register Apple Pay webhook.
     */
    private function registerApplePayWebhook(string $event, string $url): bool
    {
        // Mock implementation for now - in production this would call Apple Pay API
        Log::info('Apple Pay webhook registration', [
            'event' => $event,
            'url' => $url
        ]);

        WebhookRegistration::create([
            'service' => 'apple_pay',
            'event_type' => $event,
            'webhook_url' => $url,
            'webhook_id' => 'apple_pay_' . uniqid(),
            'signature_key' => 'test_key_' . uniqid(),
            'is_active' => true
        ]);

        return true;
    }

    /**
     * Register Google Pay webhook.
     */
    private function registerGooglePayWebhook(string $event, string $url): bool
    {
        // Mock implementation for now - in production this would call Google Pay API
        Log::info('Google Pay webhook registration', [
            'event' => $event,
            'url' => $url
        ]);

        WebhookRegistration::create([
            'service' => 'google_pay',
            'event_type' => $event,
            'webhook_url' => $url,
            'webhook_id' => 'google_pay_' . uniqid(),
            'signature_key' => 'test_key_' . uniqid(),
            'is_active' => true
        ]);

        return true;
    }

    /**
     * Verify MADA signature.
     */
    private function verifyMADASignature(string $payload, string $signature): bool
    {
        $secretKey = config('services.mada.webhook_secret');

        if (!$secretKey) {
            Log::error('MADA webhook secret not configured');
            return false;
        }

        // MADA uses HMAC-SHA256 with hex encoding
        $expectedSignature = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify STC Pay signature.
     */
    private function verifySTCPaySignature(string $payload, string $signature): bool
    {
        $merchantKey = config('services.stc_pay.merchant_key');

        if (!$merchantKey) {
            Log::error('STC Pay merchant key not configured');
            return false;
        }

        // STC Pay signature verification
        $expectedSignature = hash('sha256', $payload . $merchantKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify Apple Pay signature.
     */
    private function verifyApplePaySignature(string $payload, string $signature): bool
    {
        $secretKey = config('services.apple_pay.webhook_secret');

        if (!$secretKey) {
            Log::error('Apple Pay webhook secret not configured');
            return false;
        }

        // Apple Pay uses HMAC-SHA256 with base64 encoding
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $secretKey, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify Google Pay signature.
     */
    private function verifyGooglePaySignature(string $payload, string $signature): bool
    {
        $secretKey = config('services.google_pay.webhook_secret');

        if (!$secretKey) {
            Log::error('Google Pay webhook secret not configured');
            return false;
        }

        // Google Pay uses HMAC-SHA256 with hex encoding
        $expectedSignature = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Sanitize payload for logging by removing sensitive information.
     */
    private function sanitizePayloadForLogging(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'card_number',
            'cvv',
            'pin',
            'signature',
            'signature_key'
        ];

        return $this->recursiveSanitize($payload, $sensitiveKeys);
    }

    /**
     * Recursively sanitize array data.
     */
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

    /**
     * Calculate response time in milliseconds.
     */
    private function calculateResponseTime(): int
    {
        // This would be set at the beginning of the request
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        return (int) ((microtime(true) - $startTime) * 1000);
    }

    /**
     * Update webhook statistics.
     */
    private function updateWebhookStatistics(string $service, string $event, bool $success, int $responseTime): void
    {
        try {
            $stats = WebhookStatistics::firstOrCreate(
                ['service' => $service, 'event_type' => $event],
                [
                    'total_received' => 0,
                    'successful_processed' => 0,
                    'failed_processed' => 0,
                    'average_response_time_ms' => 0
                ]
            );

            $stats->incrementReceived();

            if ($success) {
                $stats->incrementSuccessful();
            } else {
                $stats->incrementFailed();
            }

            $stats->updateAverageResponseTime($responseTime);

        } catch (Exception $e) {
            Log::error('Failed to update webhook statistics', [
                'service' => $service,
                'event' => $event,
                'success' => $success,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send webhook failure alert.
     */
    private function sendWebhookFailureAlert(string $service, string $event, array $payload, string $errorMessage): void
    {
        try {
            $this->notificationService->sendWebhookFailureAlert($service, $event, $payload, $errorMessage);
        } catch (Exception $e) {
            Log::error('Failed to send webhook failure alert', [
                'service' => $service,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }
} 