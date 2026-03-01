<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: "/api/client/profile",
        summary: "Get client profile",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Profile data")]
    )]
    public function show(Request $request): JsonResponse
    {
        $client = $request->user()->load(['paymentMethods', 'verificationDocuments']);
        
        return response()->json([
            'status' => 'success',
            'client' => $client,
        ]);
    }

    #[OA\Put(
        path: "/api/client/profile",
        summary: "Update client profile",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Profile updated")]
    )]
    public function update(Request $request): JsonResponse
    {
        $client = $request->user();
        
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'notification_preferences' => 'sometimes|array',
        ]);

        $client->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'client' => $client->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/api/client/verification/upload",
        summary: "Upload verification documents",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "selfie", type: "string", format: "binary"),
                        new OA\Property(property: "id_document", type: "string", format: "binary")
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: "Documents uploaded")]
    )]
    public function uploadVerification(Request $request): JsonResponse
    {
        $client = $request->user();
        
        $validated = $request->validate([
            'selfie' => 'required|image|max:5120', // 5MB
            'id_document' => 'required|image|max:5120',
        ]);

        $selfiePath = $request->file('selfie')->store('verifications/' . $client->id, 'public');
        $idDocumentPath = $request->file('id_document')->store('verifications/' . $client->id, 'public');

        $client->update([
            'selfie_path' => $selfiePath,
            'id_document_path' => $idDocumentPath,
            'verification_status' => 'pending',
        ]);

        // Create verification document records
        $client->verificationDocuments()->createMany([
            [
                'document_type' => 'selfie',
                'file_path' => $selfiePath,
                'file_name' => $request->file('selfie')->getClientOriginalName(),
                'mime_type' => $request->file('selfie')->getMimeType(),
                'file_size' => $request->file('selfie')->getSize(),
            ],
            [
                'document_type' => 'id_document',
                'file_path' => $idDocumentPath,
                'file_name' => $request->file('id_document')->getClientOriginalName(),
                'mime_type' => $request->file('id_document')->getMimeType(),
                'file_size' => $request->file('id_document')->getSize(),
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Verification documents uploaded successfully',
            'verification_status' => 'pending',
        ]);
    }
}
