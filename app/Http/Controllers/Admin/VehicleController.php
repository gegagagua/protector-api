<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class VehicleController extends Controller
{
    #[OA\Get(
        path: "/api/admin/vehicles",
        summary: "Get all vehicles",
        description: "Returns all vehicles with availability and usage metadata.",
        tags: ["Admin Vehicles"],
        responses: [new OA\Response(response: 200, description: "Vehicles list")]
    )]
    public function index(): JsonResponse
    {
        $vehicles = Vehicle::withCount('bookings')->latest()->get();

        return response()->json([
            'status' => 'success',
            'vehicles' => $vehicles,
        ]);
    }

    #[OA\Post(
        path: "/api/admin/vehicles",
        summary: "Create vehicle",
        description: "Creates a vehicle record that can be assigned to bookings.",
        tags: ["Admin Vehicles"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["title", "image_url", "license_plate", "vehicle_type"],
                properties: [
                    new OA\Property(property: "title", type: "string", description: "Human readable vehicle title", example: "Cadillac Escalade"),
                    new OA\Property(property: "image_url", type: "string", format: "uri", description: "Public image URL of vehicle", example: "https://example.com/vehicles/escalade.png"),
                    new OA\Property(property: "make", type: "string", description: "Vehicle manufacturer", example: "Cadillac", nullable: true),
                    new OA\Property(property: "model", type: "string", description: "Vehicle model", example: "Escalade", nullable: true),
                    new OA\Property(property: "license_plate", type: "string", description: "Unique license plate", example: "SEC-2914"),
                    new OA\Property(property: "color", type: "string", description: "Vehicle color", example: "Black", nullable: true),
                    new OA\Property(property: "year", type: "integer", description: "Production year", example: 2022, nullable: true),
                    new OA\Property(property: "vehicle_type", type: "string", description: "Vehicle category used in booking", enum: ["sedan", "suv", "van", "motorcycle"], example: "suv"),
                    new OA\Property(property: "description", type: "string", description: "Optional internal/admin notes", example: "VIP transport SUV", nullable: true)
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: "Vehicle created")]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'make' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'image_url' => 'required|url|max:2048',
            'license_plate' => 'required|string|unique:vehicles,license_plate',
            'color' => 'nullable|string|max:50',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'vehicle_type' => 'required|in:sedan,suv,van,motorcycle',
            'description' => 'nullable|string',
        ]);

        $title = trim($validated['title']);
        $make = $validated['make'] ?? strtok($title, ' ') ?: 'Vehicle';
        $model = $validated['model'] ?? $title;

        $vehicle = Vehicle::create([
            'make' => $make,
            'model' => $model,
            'image_url' => $validated['image_url'],
            'license_plate' => $validated['license_plate'],
            'color' => $validated['color'] ?? null,
            'year' => $validated['year'] ?? null,
            'vehicle_type' => $validated['vehicle_type'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Vehicle created successfully',
            'vehicle' => $vehicle,
        ], 201);
    }
}
