<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UnsupportedGatewayException extends WebhookException
{
    public function __construct(string $gateway)
    {
        parent::__construct(
            "Unsupported payment gateway: {$gateway}",
            $gateway,
            'payment_update'
        );
    }
} 