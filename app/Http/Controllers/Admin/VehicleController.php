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
        security: [["sanctum" => []]],
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
        responses: [new OA\Response(response: 201, description: "Vehicle created")]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'license_plate' => 'required|string|unique:vehicles,license_plate',
            'color' => 'nullable|string|max:50',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'vehicle_type' => 'required|in:sedan,suv,van,motorcycle',
            'description' => 'nullable|string',
        ]);

        $vehicle = Vehicle::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Vehicle created successfully',
            'vehicle' => $vehicle,
        ], 201);
    }
}
