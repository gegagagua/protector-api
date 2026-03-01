<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                    new OA\Property(property: "username", type: "string"),
                    new OA\Property(property: "password", type: "string")
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
}
