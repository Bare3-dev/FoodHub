<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService
    ) {}

    /**
     * Handle payment webhook from external payment gateways.
     */
    public function handlePaymentWebhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Webhook-Signature') ?? '';

            // Validate required fields
            if (empty($payload)) {
                return response()->json(['error' => 'Empty payload'], 400);
            }

            if (empty($signature)) {
                return response()->json(['error' => 'Missing signature'], 400);
            }

            // Add debugging
            Log::info('Webhook received', [
                'gateway' => $gateway,
                'payload' => $payload,
                'signature' => $signature
            ]);

            // Process the webhook
            $this->webhookService->handlePaymentWebhook($gateway, $payload, $signature);

            Log::info('Payment webhook processed successfully', [
                'gateway' => $gateway,
                'transaction_id' => $payload['transaction_id'] ?? 'unknown'
            ]);

            return response()->json(['status' => 'success'], 200);

        } catch (\App\Exceptions\InvalidWebhookSignatureException $e) {
            Log::warning('Invalid webhook signature', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);

        } catch (\App\Exceptions\UnsupportedGatewayException $e) {
            Log::warning('Unsupported gateway', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Unsupported gateway'], 400);

        } catch (\Exception $e) {
            Log::error('Payment webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Register webhook with external service.
     */
    public function registerWebhook(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'service' => 'required|string',
                'event' => 'required|string',
                'url' => 'required|url'
            ]);

            $service = $request->input('service');
            $event = $request->input('event');
            $url = $request->input('url');

            $success = $this->webhookService->registerWebhook($service, $event, $url);

            if ($success) {
                Log::info('Webhook registered successfully', [
                    'service' => $service,
                    'event' => $event,
                    'url' => $url
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Webhook registered successfully'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to register webhook'
                ], 500);
            }

        } catch (\App\Exceptions\UnsupportedServiceException $e) {
            Log::warning('Unsupported service for webhook registration', [
                'service' => $request->input('service'),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Unsupported service'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Webhook registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get webhook statistics.
     */
    public function getWebhookStatistics(Request $request): JsonResponse
    {
        try {
            $service = $request->query('service');
            $eventType = $request->query('event_type');

            $query = \App\Models\WebhookStatistics::query();

            if ($service) {
                $query->service($service);
            }

            if ($eventType) {
                $query->eventType($eventType);
            }

            $statistics = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $statistics
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get webhook statistics', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get webhook logs.
     */
    public function getWebhookLogs(Request $request): JsonResponse
    {
        try {
            $service = $request->query('service');
            $eventType = $request->query('event_type');
            $success = $request->query('success');
            $limit = $request->query('limit', 50);

            $query = \App\Models\WebhookLog::query();

            if ($service) {
                $query->service($service);
            }

            if ($eventType) {
                $query->eventType($eventType);
            }

            if ($success !== null) {
                $query->where('success', $success);
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->limit($limit)
                         ->get();

            return response()->json([
                'status' => 'success',
                'data' => $logs
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get webhook logs', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }
} 