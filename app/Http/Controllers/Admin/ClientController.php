<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ClientController extends Controller
{
    #[OA\Get(
        path: "/api/admin/clients",
        summary: "Get all clients",
        description: "Returns client list for admin with verification and activity details.",
        tags: ["Admin Clients"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Clients list")]
    )]
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('verification_status');
        
        $query = Client::withCount(['bookings', 'payments']);

        if ($status) {
            $query->where('verification_status', $status);
        }

        $clients = $query->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'clients' => $clients,
        ]);
    }

    #[OA\Get(
        path: "/api/admin/clients/{id}",
        summary: "Get client details",
        description: "Returns full client profile including verification docs and booking/payment history.",
        tags: ["Admin Clients"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Client ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Client details")]
    )]
    public function show($id): JsonResponse
    {
        $client = Client::with(['bookings', 'paymentMethods', 'verificationDocuments'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'client' => $client,
        ]);
    }

    #[OA\Post(
        path: "/api/admin/clients/{id}/verify",
        summary: "Verify or reject client verification",
        description: "Approves or rejects client verification request with optional rejection reason.",
        tags: ["Admin Clients"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["verification_status"],
                properties: [
                    new OA\Property(property: "verification_status", type: "string", description: "Verification decision", enum: ["verified", "rejected"], example: "verified"),
                    new OA\Property(property: "rejection_reason", type: "string", description: "Reason when verification is rejected", nullable: true, example: "Document image is blurry")
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: "id", description: "Client ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Verification status updated")]
    )]
    public function updateVerification(Request $request, $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        $validated = $request->validate([
            'verification_status' => 'required|in:verified,rejected',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $client->update([
            'verification_status' => $validated['verification_status'],
            'verification_rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        // TODO: Send notification to client

        return response()->json([
            'status' => 'success',
            'message' => 'Verification status updated',
            'client' => $client->fresh(),
        ]);
    }
}
