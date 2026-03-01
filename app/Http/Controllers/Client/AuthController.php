<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\OtpCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/client/send-otp",
        summary: "Send OTP code to client phone",
        tags: ["Client Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone"],
                properties: [
                    new OA\Property(property: "phone", type: "string", example: "+995555123456"),
                    new OA\Property(property: "type", type: "string", enum: ["registration", "login"], example: "login")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "OTP sent successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "OTP sent to your phone")
                    ]
                )
            )
        ]
    )]
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
            'type' => 'nullable|in:registration,login,verification',
        ]);

        $phone = $validated['phone'];
        $type = $validated['type'] ?? 'login';

        // Generate 6-digit OTP
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate previous OTPs for this phone
        OtpCode::where('phone', $phone)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // Create new OTP
        OtpCode::create([
            'phone' => $phone,
            'code' => $otpCode,
            'type' => $type,
            'expires_at' => now()->addMinutes(5),
        ]);

        // TODO: Send OTP via SMS service (Twilio, etc.)
        // For now, we'll log it (remove in production)
        Log::info("OTP for {$phone}: {$otpCode}");

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent to your phone',
            // Remove this in production
            'debug_otp' => config('app.debug') ? $otpCode : null,
        ]);
    }

    #[OA\Post(
        path: "/api/client/verify-otp",
        summary: "Verify OTP and authenticate client",
        tags: ["Client Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone", "code"],
                properties: [
                    new OA\Property(property: "phone", type: "string", example: "+995555123456"),
                    new OA\Property(property: "code", type: "string", example: "123456"),
                    new OA\Property(property: "first_name", type: "string", example: "John", description: "Required for new registration (when user doesn't exist)"),
                    new OA\Property(property: "last_name", type: "string", example: "Doe", description: "Required for new registration (when user doesn't exist)")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Authentication successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "token", type: "string"),
                        new OA\Property(property: "client", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Invalid OTP")
        ]
    )]
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        $otp = OtpCode::valid()
            ->forPhone($validated['phone'])
            ->forCode($validated['code'])
            ->first();

        if (!$otp) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired OTP code.'],
            ]);
        }

        $otp->markAsUsed();

        // Find or create client
        $client = Client::where('phone', $validated['phone'])->first();
        $isNewUser = false;

        if (!$client) {
            // Registration - first_name and last_name are required
            if (empty($validated['first_name']) || empty($validated['last_name'])) {
                throw ValidationException::withMessages([
                    'first_name' => ['First name is required for registration.'],
                    'last_name' => ['Last name is required for registration.'],
                ]);
            }

            $client = Client::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'],
                'phone_verified_at' => now(),
            ]);
            $isNewUser = true;
        } else {
            // Login - update phone verification if needed
            if (!$client->phone_verified_at) {
                $client->update(['phone_verified_at' => now()]);
            }
        }

        // Generate token
        $token = $client->createToken('client-auth')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'client' => $client->makeHidden(['deleted_at']),
            'is_new_user' => $isNewUser,
        ]);
    }

    #[OA\Post(
        path: "/api/client/logout",
        summary: "Logout client",
        tags: ["Client Auth"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logged out successfully"
            )
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }
}
