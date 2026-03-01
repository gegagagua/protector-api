<?php

namespace App\Http\Controllers\SecurityPersonnel;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LocationTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StatusController extends Controller
{
    #[OA\Post(
        path: "/api/security/orders/{id}/en-route",
        summary: "Mark order as en route",
        tags: ["Security Personnel Status"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "latitude", type: "number"),
                    new OA\Property(property: "longitude", type: "number")
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Status updated")]
    )]
    public function enRoute(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;

        $booking = Booking::where('security_team_id', $team->id)->findOrFail($id);

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid booking status',
            ], 400);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $booking->update([
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        // Update personnel status
        $personnel->update(['status' => 'busy']);

        // Track location
        LocationTracking::create([
            'booking_id' => $booking->id,
            'security_personnel_id' => $personnel->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'tracked_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated to en route',
            'booking' => $booking->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/api/security/orders/{id}/arrived",
        summary: "Mark order as arrived",
        tags: ["Security Personnel Status"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Status updated")]
    )]
    public function arrived(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;

        $booking = Booking::where('security_team_id', $team->id)->findOrFail($id);

        if ($booking->status !== 'ongoing') {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid booking status',
            ], 400);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $booking->update([
            'status' => 'arrived',
            'arrived_at' => now(),
        ]);

        // Track location
        LocationTracking::create([
            'booking_id' => $booking->id,
            'security_personnel_id' => $personnel->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'tracked_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated to arrived',
            'booking' => $booking->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/api/security/location/update",
        summary: "Update location tracking",
        tags: ["Security Personnel Status"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["booking_id", "latitude", "longitude"],
                properties: [
                    new OA\Property(property: "booking_id", type: "integer"),
                    new OA\Property(property: "latitude", type: "number"),
                    new OA\Property(property: "longitude", type: "number"),
                    new OA\Property(property: "accuracy", type: "number", nullable: true),
                    new OA\Property(property: "speed", type: "number", nullable: true),
                    new OA\Property(property: "heading", type: "number", nullable: true)
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Location updated")]
    )]
    public function updateLocation(Request $request): JsonResponse
    {
        $personnel = $request->user();

        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        if ($booking->security_team_id !== $personnel->securityTeam->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        LocationTracking::create([
            'booking_id' => $validated['booking_id'],
            'security_personnel_id' => $personnel->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'tracked_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Location updated',
        ]);
    }
}
