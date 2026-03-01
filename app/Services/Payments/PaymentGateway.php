<?php

namespace App\Services\Payments;

interface PaymentGateway
{
    public function charge(array $payload): array;

    public function refund(array $payload): array;

    public function validateWebhook(array $payload, ?string $signature): bool;
}
