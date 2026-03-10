<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    private const ACTIVE_BOOKING_STATUSES = ['pending', 'confirmed', 'ongoing', 'arrived'];
    private const HISTORY_BOOKING_STATUSES = ['completed', 'cancelled'];

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        return preg_replace('/\s+/', '', $phone);
    }

    private function buildPhoneFromParts(?string $countryCode, ?string $nationalNumber): ?string
    {
        if (!$countryCode || !$nationalNumber) {
            return null;
        }

        $normalizedCode = '+' . ltrim($countryCode, '+');
        $normalizedNational = preg_replace('/\D+/', '', $nationalNumber);

        return $normalizedCode . $normalizedNational;
    }

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
        ]);
    }

    #[OA\Put(
        path: "/api/client/profile",
        summary: "Update client profile",
        description: "Updates account details such as name, phone, date of birth, sex, and preferences.",
        tags: ["Client Profile"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "first_name", type: "string", description: "Client first name", example: "Giorgi"),
                    new OA\Property(property: "last_name", type: "string", description: "Client last name", example: "Gelashvili"),
                    new OA\Property(property: "email", type: "string", format: "email", description: "Client email address", nullable: true, example: "giorgi@example.com"),
                    new OA\Property(property: "phone", type: "string", description: "Client phone number", nullable: true, example: "+995555123456"),
                    new OA\Property(property: "date_of_birth", type: "string", format: "date", description: "Date of birth (YYYY-MM-DD)", nullable: true, example: "1995-05-10"),
                    new OA\Property(property: "sex", type: "string", description: "Client sex value", nullable: true, enum: ["male", "female", "other"], example: "male"),
                    new OA\Property(
                        property: "notification_preferences",
                        type: "object",
                        description: "Notification delivery preferences",
                        nullable: true,
                        properties: [
                            new OA\Property(property: "text", type: "boolean", description: "Enable SMS/text notifications", example: true),
                            new OA\Property(property: "voice", type: "boolean", description: "Enable voice call notifications", example: false),
                            new OA\Property(property: "push", type: "boolean", description: "Enable push notifications", example: true),
                        ]
                    ),
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
            'phone' => 'sometimes|string|regex:/^\+?[1-9]\d{1,14}$/',
            'phone_country_code' => 'sometimes|string|regex:/^\+?[1-9]\d{0,3}$/',
            'phone_national_number' => 'sometimes|string|min:4|max:20',
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

        if (array_key_exists('phone_country_code', $validated) || array_key_exists('phone_national_number', $validated)) {
            if (empty($validated['phone_country_code']) || empty($validated['phone_national_number'])) {
                throw ValidationException::withMessages([
                    'phone_country_code' => ['phone_country_code and phone_national_number must be sent together.'],
                ]);
            }

            $validated['phone'] = $this->buildPhoneFromParts(
                $validated['phone_country_code'],
                $validated['phone_national_number']
            );
        }

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

        return response()->json([
            'status' => 'success',
            'message' => 'Avatar updated successfully',
            'avatar_url' => Storage::disk('public')->url($avatarPath),
            'avatar_path' => $avatarPath,
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
