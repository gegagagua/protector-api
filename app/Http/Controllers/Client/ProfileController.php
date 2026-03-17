<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    private const ACTIVE_BOOKING_STATUSES = ['pending', 'confirmed', 'ongoing', 'arrived'];
    private const HISTORY_BOOKING_STATUSES = ['completed', 'cancelled'];

    #[OA\Get(
        path: "/api/client/me",
        summary: "Get authenticated client full profile",
        description: "Returns authenticated client profile with active bookings and booking history in one response.",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Client full profile")]
    )]
    public function me(Request $request): JsonResponse
    {
        $client = $request->user()->load(['paymentMethods', 'verificationDocuments']);

        $activeBookings = $client->bookings()
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->with(['securityTeam', 'vehicle', 'bookingPersons'])
            ->latest()
            ->get();

        $bookingHistory = $client->bookings()
            ->whereIn('status', self::HISTORY_BOOKING_STATUSES)
            ->with(['securityTeam', 'vehicle', 'payments', 'rating'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'client' => $client,
            'active_bookings' => $activeBookings,
            'booking_history' => $bookingHistory,
            'storage_base_url' => rtrim((string) config('app.url'), '/') . '/storage',
        ]);
    }

    #[OA\Get(
        path: "/api/client/profile",
        summary: "Get client profile",
        description: "Returns authenticated client profile, payment methods, and verification documents.",
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
            'storage_base_url' => rtrim((string) config('app.url'), '/') . '/storage',
        ]);
    }

    #[OA\Put(
        path: "/api/client/profile",
        summary: "Update client profile",
        description: "Updates account details such as name, email, date of birth, and sex.",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "first_name", type: "string", description: "Client first name", example: "Giorgi"),
                    new OA\Property(property: "last_name", type: "string", description: "Client last name", example: "Gelashvili"),
                    new OA\Property(property: "email", type: "string", format: "email", description: "Client email address", nullable: true, example: "giorgi@example.com"),
                    new OA\Property(property: "date_of_birth", type: "string", format: "date", description: "Date of birth (YYYY-MM-DD)", nullable: true, example: "1995-05-10"),
                    new OA\Property(property: "sex", type: "string", description: "Client sex value", nullable: true, enum: ["male", "female", "other"], example: "male")
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Profile updated")]
    )]
    public function update(Request $request): JsonResponse
    {
        $client = $request->user();
        
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'prohibited',
            'phone_country_code' => 'prohibited',
            'phone_national_number' => 'prohibited',
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'sex' => 'sometimes|nullable|in:male,female,other',
            'notification_preferences' => 'prohibited',
            'notification_preferences.text' => 'prohibited',
            'notification_preferences.voice' => 'prohibited',
            'notification_preferences.push' => 'prohibited',
        ]);

        $client->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'client' => $client->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/api/client/profile/avatar",
        summary: "Upload or replace profile avatar",
        description: "Uploads client avatar image. Replaces previous avatar when it exists.",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["avatar"],
                    properties: [
                        new OA\Property(property: "avatar", type: "string", format: "binary", description: "Avatar image file")
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: "Avatar updated")]
    )]
    public function uploadAvatar(Request $request): JsonResponse
    {
        $client = $request->user();
        $validated = $request->validate([
            'avatar' => 'required|image|max:5120',
        ]);

        if (!empty($client->avatar_path)) {
            Storage::disk('public')->delete($client->avatar_path);
        }

        $avatarPath = $validated['avatar']->store('avatars/' . $client->id, 'public');
        $client->update(['avatar_path' => $avatarPath]);
        $client = $client->fresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Avatar updated successfully',
            'avatar_url' => $client->avatar_url,
            'avatar_path' => $client->avatar_path,
            'storage_base_url' => rtrim((string) config('app.url'), '/') . '/storage',
        ]);
    }

    #[OA\Post(
        path: "/api/client/verification/upload",
        summary: "Upload verification documents",
        description: "Uploads selfie and ID document and switches verification status to pending review.",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "selfie", type: "string", format: "binary", description: "Client selfie image file"),
                        new OA\Property(property: "id_document", type: "string", format: "binary", description: "Government ID photo file")
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

    #[OA\Put(
        path: "/api/client/notification-preferences",
        summary: "Update notification preferences",
        description: "Updates notification settings for text, voice, and push channels.",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "text", type: "boolean", description: "Enable text/SMS notifications", example: true),
                    new OA\Property(property: "voice", type: "boolean", description: "Enable voice call notifications", example: false),
                    new OA\Property(property: "push", type: "boolean", description: "Enable push notifications", example: true)
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Notification preferences updated")]
    )]
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

    #[OA\Get(
        path: "/api/client/verification/status",
        summary: "Get verification status",
        description: "Returns current KYC status with rejection reason and document upload flag.",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Verification status")]
    )]
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
