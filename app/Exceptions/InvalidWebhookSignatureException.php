<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidWebhookSignatureException extends WebhookException
{
    public function __construct(string $service, string $signature = '')
    {
        parent::__construct(
            "Invalid webhook signature for service: {$service}",
            $service,
            '',
            ['provided_signature' => $signature]
        );
    }
} 