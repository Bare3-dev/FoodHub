<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class WebhookException extends Exception
{
    protected $service;
    protected $eventType;
    protected $payload;

    public function __construct(string $message, string $service = '', string $eventType = '', array $payload = [])
    {
        parent::__construct($message);
        $this->service = $service;
        $this->eventType = $eventType;
        $this->payload = $payload;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
} 