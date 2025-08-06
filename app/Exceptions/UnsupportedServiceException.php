<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UnsupportedServiceException extends WebhookException
{
    public function __construct(string $service)
    {
        parent::__construct(
            "Service not supported: {$service}",
            $service
        );
    }
} 