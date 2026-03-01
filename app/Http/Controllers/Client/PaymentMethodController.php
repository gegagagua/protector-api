<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $methods = $request->user()
            ->paymentMethods()
            ->active()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'payment_methods' => $methods,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $client = $request->user();

        $validated = $request->validate([
            'type' => 'required|in:card,mobile_pay',
            'provider' => 'nullable|string|max:50',
            'last_four' => 'nullable|string|size:4',
            'card_holder_name' => 'nullable|string|max:255',
            'token' => 'required|string|max:255',
            'is_default' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        if (($validated['is_default'] ?? false) === true) {
            $client->paymentMethods()->update(['is_default' => false]);
        }

        $method = $client->paymentMethods()->create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment method saved.',
            'payment_method' => $method,
        ], 201);
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $client = $request->user();
        $method = $client->paymentMethods()->findOrFail($id);

        $client->paymentMethods()->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Default payment method updated.',
            'payment_method' => $method->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = $request->user();
        $method = $client->paymentMethods()->findOrFail($id);

        $method->update([
            'is_active' => false,
            'is_default' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment method removed.',
        ]);
    }
}
