<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Unsupported Gateway Exception
 * 
 * For handling unsupported payment gateways, POS systems, or other integrations
 */
class UnsupportedGatewayException extends Exception
{
    protected string $errorCode;
    protected array $context;
    protected int $statusCode;

    public function __construct(
        string $message = 'Unsupported gateway or integration',
        string $errorCode = 'UNSUPPORTED_GATEWAY',
        array $context = [],
        int $statusCode = 422
    ) {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'Unsupported Gateway',
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
            'timestamp' => now()->toISOString(),
            'request_id' => 'req_' . now()->format('Ymd_His') . '_' . strtoupper(substr(md5(uniqid()), 0, 8)),
        ], $this->statusCode);
    }

    /**
     * Common unsupported gateway exceptions
     */
    public static function unsupportedPOSType(string $posType): self
    {
        return new self(
            "Unsupported POS system type: {$posType}",
            'UNSUPPORTED_POS_TYPE',
            ['pos_type' => $posType],
            422
        );
    }

    public static function unsupportedPaymentGateway(string $gateway): self
    {
        return new self(
            "Unsupported payment gateway: {$gateway}",
            'UNSUPPORTED_PAYMENT_GATEWAY',
            ['gateway' => $gateway],
            422
        );
    }

    public static function unsupportedDeliveryProvider(string $provider): self
    {
        return new self(
            "Unsupported delivery provider: {$provider}",
            'UNSUPPORTED_DELIVERY_PROVIDER',
            ['provider' => $provider],
            422
        );
    }
} 