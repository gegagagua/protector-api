<?php

namespace App\Http\Controllers\Admin;

use App\Events\PasswordChanged;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/admin/login",
        summary: "Login admin",
        description: "Authenticates admin with username/password and returns scoped access token.",
        tags: ["Admin Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["username", "password"],
                properties: [
                    new OA\Property(property: "username", type: "string", description: "Admin username", example: "admin"),
                    new OA\Property(property: "password", type: "string", description: "Admin account password", example: "admin1234")
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

        $admin = Admin::where('username', $validated['username'])->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$admin->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Your account is inactive.'],
            ]);
        }

        $token = $admin->createToken('admin-auth', ['admin'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'admin' => $admin,
        ]);
    }

    #[OA\Post(
        path: "/api/admin/logout",
        summary: "Logout admin",
        description: "Revokes current admin access token.",
        tags: ["Admin Auth"],
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
        path: "/api/admin/change-password",
        summary: "Change admin password",
        description: "Changes authenticated admin password after validating current password.",
        tags: ["Admin Auth"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "new_password", "new_password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", description: "Current admin password", example: "old-password"),
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

        /** @var Admin $admin */
        $admin = $request->user();

        if (!Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $admin->update([
            'password' => $validated['new_password'],
        ]);

        Event::dispatch(new PasswordChanged('admin', (int) $admin->id, now()->toIso8601String()));

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
        ]);
    }
}
