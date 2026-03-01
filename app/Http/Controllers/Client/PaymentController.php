<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentGateway $paymentGateway)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->with(['booking', 'paymentMethod'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'payments' => $payments,
        ]);
    }

    public function payBooking(Request $request, int $bookingId): JsonResponse
    {
        $client = $request->user();

        $validated = $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
        ]);

        $booking = $client->bookings()->findOrFail($bookingId);
        $method = $client->paymentMethods()->active()->findOrFail($validated['payment_method_id']);

        if ($booking->payment_status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking already paid.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $gatewayResult = $this->paymentGateway->charge([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'amount' => (float) $booking->total_amount,
                'token' => $method->token,
            ]);

            if (!$gatewayResult['success']) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment was declined.',
                ], 422);
            }

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'payment_method_id' => $method->id,
                'amount' => (float) $booking->total_amount,
                'payment_type' => $method->type,
                'status' => 'completed',
                'transaction_id' => $gatewayResult['transaction_id'],
                'payment_gateway' => $gatewayResult['gateway'],
                'payment_data' => $gatewayResult['raw'],
                'paid_at' => now(),
            ]);

            $booking->update([
                'paid_amount' => (float) $booking->total_amount,
                'payment_status' => 'paid',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment completed successfully.',
                'payment' => $payment,
                'booking' => $booking->fresh(),
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Payment processing failed.',
            ], 500);
        }
    }
}
