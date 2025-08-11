<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\UnsupportedGatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookLog;
use App\Models\WebhookStatistics;
use App\Services\LoyaltyService;
use App\Services\NotificationService;
use App\Services\SecurityLoggingService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebhookService $webhookService;
    private NotificationService $notificationService;
    private LoyaltyService $loyaltyService;
    private SecurityLoggingService $securityLoggingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->loyaltyService = $this->createMock(LoyaltyService::class);
        $this->securityLoggingService = $this->createMock(SecurityLoggingService::class);
        
        $this->webhookService = new WebhookService(
            $this->notificationService,
            $this->loyaltyService,
            $this->securityLoggingService
        );
    }

    public function test_handle_payment_webhook_with_invalid_signature_throws_exception(): void
    {
        // Temporarily disable testing environment to test signature verification
        $this->app['env'] = 'production';
        
        $this->expectException(InvalidWebhookSignatureException::class);

        $payload = ['transaction_id' => 'test123', 'status' => 'success'];
        $signature = 'invalid_signature';

        $this->webhookService->handlePaymentWebhook('mada', $payload, $signature);
        
        // Restore testing environment
        $this->app['env'] = 'testing';
    }

    public function test_handle_payment_webhook_with_unsupported_gateway_throws_exception(): void
    {
        $this->expectException(UnsupportedGatewayException::class);

        $payload = ['transaction_id' => 'test123', 'status' => 'success'];
        $signature = 'valid_signature';

        $this->webhookService->handlePaymentWebhook('unsupported_gateway', $payload, $signature);
    }

    public function test_handle_payment_webhook_logs_event(): void
    {
        $payload = ['transaction_id' => 'test123', 'status' => 'success'];
        $signature = 'valid_signature';

        $this->webhookService->handlePaymentWebhook('mada', $payload, $signature);

        $this->assertDatabaseHas('webhook_logs', [
            'service' => 'mada',
            'event_type' => 'payment_update',
            'success' => true,
        ]);
    }

    public function test_handle_payment_webhook_updates_statistics(): void
    {
        $payload = ['transaction_id' => 'test123', 'status' => 'success'];
        $signature = 'valid_signature';

        $this->webhookService->handlePaymentWebhook('mada', $payload, $signature);

        $this->assertDatabaseHas('webhook_statistics', [
            'service' => 'mada',
            'event_type' => 'payment_update',
            'total_received' => 1,
            'successful_processed' => 1,
        ]);
    }

    public function test_handle_successful_payment_updates_order_and_payment(): void
    {
        // Create test order and payment
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'transaction_id' => 'test123',
            'status' => 'pending',
            'amount' => 100.00,
        ]);

        // Mock service methods - use any() to match any order object
        $this->loyaltyService->expects($this->once())
            ->method('processOrderLoyaltyPoints')
            ->with($this->isInstanceOf(Order::class));

        $this->notificationService->expects($this->once())
            ->method('sendPaymentConfirmation')
            ->with($this->isInstanceOf(Order::class));

        $this->notificationService->expects($this->once())
            ->method('sendOrderConfirmation')
            ->with($this->isInstanceOf(Order::class));

        $this->securityLoggingService->expects($this->once())
            ->method('logPaymentSuccess')
            ->with($this->isInstanceOf(Order::class), $this->isInstanceOf(Payment::class));

        $payload = [
            'transaction_id' => 'test123',
            'status' => 'success',
            'amount' => 100.00,
        ];
        $signature = 'valid_signature';

        $this->webhookService->handlePaymentWebhook('mada', $payload, $signature);

        // Assert order was updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        // Assert payment was updated
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'completed',
            'paid_amount' => 100.00,
        ]);
    }

    public function test_handle_failed_payment_updates_order_and_payment(): void
    {
        // Create test order and payment
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'transaction_id' => 'test123',
            'status' => 'pending',
            'amount' => 100.00,
        ]);

        // Mock service methods
        $this->notificationService->expects($this->once())
            ->method('sendPaymentFailureNotification')
            ->with($this->isInstanceOf(Order::class), 'Payment failed');

        $this->securityLoggingService->expects($this->once())
            ->method('logPaymentFailure')
            ->with($this->isInstanceOf(Order::class), $this->isInstanceOf(Payment::class), 'Payment failed');

        $payload = [
            'transaction_id' => 'test123',
            'status' => 'failed',
            'error_message' => 'Payment failed',
        ];
        $signature = 'valid_signature';

        $this->webhookService->handlePaymentWebhook('mada', $payload, $signature);

        // Assert order was updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'payment_status' => 'failed',
        ]);

        // Assert payment was updated
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
            'error_message' => 'Payment failed',
        ]);
    }

    public function test_handle_refunded_payment_updates_order_and_payment(): void
    {
        // Create test order and payment
        $order = Order::factory()->create([
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'transaction_id' => 'test123',
            'status' => 'completed',
            'amount' => 100.00,
        ]);

        // Mock service methods
        $this->notificationService->expects($this->once())
            ->method('sendRefundNotification')
            ->with($this->isInstanceOf(Order::class), 100.00);

        $this->securityLoggingService->expects($this->once())
            ->method('logPaymentRefund')
            ->with($this->isInstanceOf(Order::class), $this->isInstanceOf(Payment::class), 100.00);

        $payload = [
            'transaction_id' => 'test123',
            'status' => 'refunded',
            'amount' => 100.00,
        ];
        $signature = 'valid_signature';

        $this->webhookService->handlePaymentWebhook('mada', $payload, $signature);

        // Assert order was updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'refunded',
            'refund_amount' => 100.00,
        ]);

        // Assert payment was updated
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'refunded',
        ]);
    }

    public function test_webhook_with_unknown_transaction_logs_warning(): void
    {
        $payload = [
            'transaction_id' => 'unknown_transaction',
            'status' => 'success',
        ];
        $signature = 'valid_signature';

        $this->webhookService->handlePaymentWebhook('mada', $payload, $signature);

        // Should not throw exception, just log warning
        $this->assertDatabaseHas('webhook_logs', [
            'service' => 'mada',
            'event_type' => 'payment_update',
            'success' => true,
        ]);
    }
} 