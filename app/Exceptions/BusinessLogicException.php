<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Business Logic Exception
 * 
 * For handling business rule violations and domain-specific errors
 * such as insufficient loyalty points, restaurant closed, etc.
 */
class BusinessLogicException extends Exception
{
    protected string $errorCode;
    protected array $context;
    protected int $statusCode;

    public function __construct(
        string $message = 'Business logic violation',
        string $errorCode = 'BUSINESS_LOGIC_ERROR',
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
            'error' => 'Business Logic Error',
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
            'timestamp' => now()->toISOString(),
            'request_id' => 'req_' . now()->format('Ymd_His') . '_' . strtoupper(substr(md5(uniqid()), 0, 8)),
        ], $this->statusCode);
    }

    /**
     * Common business logic exceptions
     */
    public static function restaurantClosed(string $restaurantName, ?string $reopenTime = null): self
    {
        return new self(
            "Restaurant '{$restaurantName}' is currently closed.",
            'RESTAURANT_CLOSED',
            ['restaurant_name' => $restaurantName, 'reopen_time' => $reopenTime],
            422
        );
    }

    public static function insufficientLoyaltyPoints(int $required, int $available): self
    {
        return new self(
            "Insufficient loyalty points. Required: {$required}, Available: {$available}",
            'INSUFFICIENT_LOYALTY_POINTS',
            ['required_points' => $required, 'available_points' => $available],
            422
        );
    }

    public static function menuItemUnavailable(string $itemName, ?string $reason = null): self
    {
        return new self(
            "Menu item '{$itemName}' is currently unavailable." . ($reason ? " Reason: {$reason}" : ''),
            'MENU_ITEM_UNAVAILABLE',
            ['item_name' => $itemName, 'reason' => $reason],
            422
        );
    }

    public static function orderNotEditable(string $status): self
    {
        return new self(
            "Order cannot be modified because it is already '{$status}'.",
            'ORDER_NOT_EDITABLE',
            ['current_status' => $status],
            422
        );
    }

    public static function deliveryZoneNotCovered(string $address): self
    {
        return new self(
            "Delivery is not available to the specified address: {$address}",
            'DELIVERY_ZONE_NOT_COVERED',
            ['address' => $address],
            422
        );
    }

    public static function paymentFailed(string $reason, ?string $transactionId = null): self
    {
        return new self(
            "Payment processing failed: {$reason}",
            'PAYMENT_FAILED',
            ['reason' => $reason, 'transaction_id' => $transactionId],
            422
        );
    }

    public static function driverUnavailable(): self
    {
        return new self(
            'No drivers are currently available for delivery.',
            'DRIVER_UNAVAILABLE',
            [],
            503
        );
    }

    public static function stockLimitExceeded(string $itemName, int $requestedQuantity, int $availableStock): self
    {
        return new self(
            "Cannot add {$requestedQuantity} of '{$itemName}'. Only {$availableStock} items available in stock.",
            'STOCK_LIMIT_EXCEEDED',
            [
                'item_name' => $itemName,
                'requested_quantity' => $requestedQuantity,
                'available_stock' => $availableStock
            ],
            422
        );
    }

    public static function posNotIntegrated(string $posType): self
    {
        return new self(
            "Restaurant is not integrated with {$posType} POS system.",
            'POS_NOT_INTEGRATED',
            ['pos_type' => $posType],
            422
        );
    }

    public static function posOrderCreationFailed(string $reason): self
    {
        return new self(
            "Failed to create order in POS system: {$reason}",
            'POS_ORDER_CREATION_FAILED',
            ['reason' => $reason],
            422
        );
    }

    public static function posOrderNotFound(string $posOrderId): self
    {
        return new self(
            "POS order with ID '{$posOrderId}' not found.",
            'POS_ORDER_NOT_FOUND',
            ['pos_order_id' => $posOrderId],
            404
        );
    }

    public static function orderNotFound(string $orderId): self
    {
        return new self(
            "Order with ID '{$orderId}' not found.",
            'ORDER_NOT_FOUND',
            ['order_id' => $orderId],
            404
        );
    }
}