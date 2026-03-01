<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentGateway $paymentGateway)
    {
    }

    #[OA\Post(
        path: "/api/payments/webhook",
        summary: "Handle payment webhook",
        description: "Processes payment provider webhook events with idempotency protection.",
        tags: ["Payments"],
        responses: [
            new OA\Response(response: 200, description: "Webhook processed"),
            new OA\Response(response: 400, description: "Invalid webhook signature")
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventId = (string) ($payload['event_id'] ?? '');
        $eventType = (string) ($payload['event_type'] ?? 'unknown');
        $signature = $request->header('X-Payment-Signature');

        if (!$eventId || !$this->paymentGateway->validateWebhook($payload, $signature)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid webhook signature'], 400);
        }

        if (PaymentWebhook::where('event_id', $eventId)->exists()) {
            return response()->json(['status' => 'success', 'message' => 'Webhook already processed']);
        }

        DB::beginTransaction();
        try {
            $payment = Payment::where('transaction_id', $payload['transaction_id'] ?? null)->first();

            if ($payment && $eventType === 'payment.succeeded') {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);

                $payment->booking->update([
                    'paid_amount' => $payment->amount,
                    'payment_status' => 'paid',
                ]);
            }

            if ($payment && $eventType === 'refund.succeeded') {
                $refundAmount = (float) ($payload['amount'] ?? 0);

                $payment->update([
                    'status' => $refundAmount < (float) $payment->amount ? 'partially_refunded' : 'refunded',
                ]);

                $payment->booking->update([
                    'refunded_amount' => $refundAmount,
                    'payment_status' => $refundAmount < (float) $payment->amount ? 'partially_refunded' : 'fully_refunded',
                ]);
            }

            PaymentWebhook::create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payment_id' => $payment?->id,
                'payload' => $payload,
                'status' => 'processed',
            ]);

            DB::commit();

            return response()->json(['status' => 'success']);
        } catch (\Throwable $exception) {
            DB::rollBack();

            PaymentWebhook::create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }
}
