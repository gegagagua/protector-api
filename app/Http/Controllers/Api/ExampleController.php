<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Proector API",
    description: "API documentation for Proector backend application"
)]
#[OA\Server(
    url: "/api",
    description: "API Server"
)]
class ExampleController extends Controller
{
    #[OA\Get(
        path: "/api/example",
        summary: "Get example data",
        tags: ["Example"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful response",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "This is an example endpoint"),
                        new OA\Property(property: "data", type: "object", example: ["key" => "value"])
                    ]
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'This is an example endpoint',
            'data' => [
                'key' => 'value'
            ]
        ]);
    }

    #[OA\Post(
        path: "/api/example",
        summary: "Create example data",
        tags: ["Example"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Resource created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Resource created"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "Validation failed"),
                        new OA\Property(property: "errors", type: "object")
                    ]
                )
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Resource created',
            'data' => $validated
        ], 201);
    }

    #[OA\Get(
        path: "/api/example/{id}",
        summary: "Get example data by ID",
        tags: ["Example"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Example ID",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful response",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Resource not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "Resource not found")
                    ]
                )
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $id,
                'name' => 'Example Item'
            ]
        ]);
    }
}


