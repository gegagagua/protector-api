<?php

namespace App\Services\Payments;

use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGateway
{
    public function charge(array $payload): array
    {
        return [
            'success' => true,
            'transaction_id' => 'txn_' . Str::lower(Str::random(20)),
            'status' => 'completed',
            'gateway' => 'mock_gateway',
            'raw' => $payload,
        ];
    }

    public function refund(array $payload): array
    {
        return [
            'success' => true,
            'transaction_id' => 'rfnd_' . Str::lower(Str::random(20)),
            'status' => $payload['amount'] < $payload['paid_amount'] ? 'partially_refunded' : 'refunded',
            'gateway' => 'mock_gateway',
            'raw' => $payload,
        ];
    }

    public function validateWebhook(array $payload, ?string $signature): bool
    {
        return !empty($payload['event_id']);
    }
}
