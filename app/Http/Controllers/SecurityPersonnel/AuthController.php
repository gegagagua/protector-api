<?php

namespace App\Http\Controllers\SecurityPersonnel;

use App\Events\PasswordChanged;
use App\Http\Controllers\Controller;
use App\Models\SecurityPersonnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/security/login",
        summary: "Login security personnel",
        description: "Authenticates security personnel with username/password and returns token.",
        tags: ["Security Personnel Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["username", "password"],
                properties: [
                    new OA\Property(property: "username", type: "string", description: "Security personnel username", example: "guard01"),
                    new OA\Property(property: "password", type: "string", description: "Security personnel password", example: "guard1234")
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Login successful")]
    )]
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $personnel = SecurityPersonnel::where('username', $validated['username'])->first();

        if (!$personnel || !Hash::check($validated['password'], $personnel->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$personnel->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Your account is inactive.'],
            ]);
        }

        $token = $personnel->createToken('security-auth', ['security'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'personnel' => $personnel->load('securityTeam'),
        ]);
    }

    #[OA\Post(
        path: "/api/security/logout",
        summary: "Logout security personnel",
        description: "Revokes current security personnel access token.",
        tags: ["Security Personnel Auth"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Logged out")]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }

    #[OA\Post(
        path: "/api/security/change-password",
        summary: "Change security personnel password",
        description: "Changes authenticated security personnel password after validating current password.",
        tags: ["Security Personnel Auth"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "new_password", "new_password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", description: "Current personnel password", example: "old-password"),
                    new OA\Property(property: "new_password", type: "string", description: "New password (minimum 8 characters)", example: "new-strong-password"),
                    new OA\Property(property: "new_password_confirmation", type: "string", description: "Repeat new password to confirm", example: "new-strong-password"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Password changed")]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        /** @var SecurityPersonnel $personnel */
        $personnel = $request->user();

        if (!Hash::check($validated['current_password'], $personnel->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $personnel->update([
            'password' => $validated['new_password'],
        ]);

        Event::dispatch(new PasswordChanged('security', (int) $personnel->id, now()->toIso8601String()));

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
        ]);
    }
}
