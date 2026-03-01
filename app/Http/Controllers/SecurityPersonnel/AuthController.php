<?php

namespace App\Http\Controllers\SecurityPersonnel;

use App\Http\Controllers\Controller;
use App\Models\SecurityPersonnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/security/login",
        summary: "Login security personnel",
        tags: ["Security Personnel Auth"],
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

        $token = $personnel->createToken('security-auth')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'personnel' => $personnel->load('securityTeam'),
        ]);
    }

    #[OA\Post(
        path: "/api/security/logout",
        summary: "Logout security personnel",
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
}
