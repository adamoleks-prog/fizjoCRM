<?php

namespace App\Services\Messaging;

interface SmsGateway
{
    public function isConfigured(): bool;

    /**
     * @throws MessagingNotConfigured when no gateway is set up
     * @throws SmsFailed with a reason safe to store
     */
    public function send(string $phone, string $message): void;
}
