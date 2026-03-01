<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        return preg_replace('/\s+/', '', $phone);
    }

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
            'phone' => 'sometimes|string|regex:/^\+?[1-9]\d{1,14}$/',
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'sex' => 'sometimes|nullable|in:male,female,other',
            'notification_preferences' => 'sometimes|array',
            'notification_preferences.text' => 'nullable|boolean',
            'notification_preferences.voice' => 'nullable|boolean',
            'notification_preferences.push' => 'nullable|boolean',
        ]);

        if (array_key_exists('phone', $validated)) {
            $validated['phone'] = $this->normalizePhone($validated['phone']);
        }

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

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $client = $request->user();

        $validated = $request->validate([
            'text' => 'nullable|boolean',
            'voice' => 'nullable|boolean',
            'push' => 'nullable|boolean',
        ]);

        $client->update([
            'notification_preferences' => [
                'text' => (bool) ($validated['text'] ?? false),
                'voice' => (bool) ($validated['voice'] ?? false),
                'push' => (bool) ($validated['push'] ?? true),
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification preferences updated.',
            'notification_preferences' => $client->fresh()->notification_preferences,
        ]);
    }

    public function verificationStatus(Request $request): JsonResponse
    {
        $client = $request->user();

        return response()->json([
            'status' => 'success',
            'verification' => [
                'status' => $client->verification_status,
                'rejection_reason' => $client->verification_rejection_reason,
                'documents_uploaded' => !empty($client->selfie_path) && !empty($client->id_document_path),
            ],
        ]);
    }
}
