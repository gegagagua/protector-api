<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PaymentMethodController extends Controller
{
    #[OA\Get(
        path: "/api/client/payment-methods",
        summary: "List client payment methods",
        description: "Returns active client payment methods with default method first.",
        tags: ["Client Payments"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Payment methods list")]
    )]
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

    #[OA\Post(
        path: "/api/client/payment-methods",
        summary: "Save payment method",
        description: "Adds a new card or mobile pay method for the authenticated client.",
        tags: ["Client Payments"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["type", "token"],
                properties: [
                    new OA\Property(property: "type", type: "string", description: "Payment method type", enum: ["card", "mobile_pay"], example: "card"),
                    new OA\Property(property: "provider", type: "string", description: "Payment provider name", nullable: true, example: "Visa"),
                    new OA\Property(property: "last_four", type: "string", description: "Last 4 digits of card", nullable: true, example: "4242"),
                    new OA\Property(property: "card_holder_name", type: "string", description: "Card holder full name", nullable: true, example: "Giorgi Gelashvili"),
                    new OA\Property(property: "token", type: "string", description: "Provider tokenized payment source", example: "pm_tok_123"),
                    new OA\Property(property: "is_default", type: "boolean", description: "Marks method as default when true", example: true),
                    new OA\Property(property: "metadata", type: "object", description: "Optional provider specific metadata", nullable: true)
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: "Payment method created")]
    )]
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

    #[OA\Post(
        path: "/api/client/payment-methods/{id}/set-default",
        summary: "Set default payment method",
        description: "Marks the selected payment method as default and unsets previous default.",
        tags: ["Client Payments"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Payment method ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Default method updated")]
    )]
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

    #[OA\Delete(
        path: "/api/client/payment-methods/{id}",
        summary: "Remove payment method",
        description: "Soft-removes a payment method from client account by disabling it.",
        tags: ["Client Payments"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Payment method ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Payment method removed")]
    )]
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
