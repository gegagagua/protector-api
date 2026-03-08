<?php

namespace App\Http\Controllers\SecurityPersonnel;

use App\Events\LocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LocationTracking;
use App\Services\Booking\BookingStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StatusController extends Controller
{
    public function __construct(private readonly BookingStateMachine $stateMachine)
    {
    }

    #[OA\Post(
        path: "/api/security/orders/{id}/en-route",
        summary: "Mark order as en route",
        description: "Transitions booking to ongoing state and starts live location tracking.",
        tags: ["Security Personnel Status"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "latitude", type: "number", description: "Current latitude of security personnel", example: 41.7151),
                    new OA\Property(property: "longitude", type: "number", description: "Current longitude of security personnel", example: 44.8271)
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Status updated")]
    )]
    public function enRoute(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Security personnel is not assigned to a team.',
            ], 422);
        }

        $booking = Booking::where('security_team_id', $team->id)->findOrFail($id);

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $booking = $this->stateMachine->transition($booking, 'ongoing');

        // Update personnel status
        $personnel->update(['status' => 'busy']);

        // Track location
        $location = LocationTracking::create([
            'booking_id' => $booking->id,
            'security_personnel_id' => $personnel->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'tracked_at' => now(),
        ]);
        event(new LocationUpdated($location));

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated to en route',
            'booking' => $booking->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/api/security/orders/{id}/arrived",
        summary: "Mark order as arrived",
        description: "Transitions booking to arrived state and records latest team location.",
        tags: ["Security Personnel Status"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["latitude", "longitude"],
                properties: [
                    new OA\Property(property: "latitude", type: "number", description: "Arrival latitude", example: 41.7151),
                    new OA\Property(property: "longitude", type: "number", description: "Arrival longitude", example: 44.8271)
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Status updated")]
    )]
    public function arrived(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Security personnel is not assigned to a team.',
            ], 422);
        }

        $booking = Booking::where('security_team_id', $team->id)->findOrFail($id);

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $booking = $this->stateMachine->transition($booking, 'arrived');

        // Track location
        $location = LocationTracking::create([
            'booking_id' => $booking->id,
            'security_personnel_id' => $personnel->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'tracked_at' => now(),
        ]);
        event(new LocationUpdated($location));

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated to arrived',
            'booking' => $booking->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/api/security/orders/{id}/complete",
        summary: "Mark order as completed",
        description: "Transitions arrived booking to completed and frees team/personnel availability.",
        tags: ["Security Personnel Status"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Booking completed")]
    )]
    public function complete(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Security personnel is not assigned to a team.',
            ], 422);
        }

        $booking = Booking::where('security_team_id', $team->id)->findOrFail($id);
        $booking = $this->stateMachine->transition($booking, 'completed');

        $team->update(['status' => 'available']);
        $personnel->update(['status' => 'available']);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking marked as completed',
            'booking' => $booking,
        ]);
    }

    #[OA\Post(
        path: "/api/security/location/update",
        summary: "Update location tracking",
        description: "Pushes periodic location updates for active booking tracking.",
        tags: ["Security Personnel Status"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["booking_id", "latitude", "longitude"],
                properties: [
                    new OA\Property(property: "booking_id", type: "integer", description: "Booking being tracked", example: 10),
                    new OA\Property(property: "latitude", type: "number", description: "Current latitude", example: 41.7151),
                    new OA\Property(property: "longitude", type: "number", description: "Current longitude", example: 44.8271),
                    new OA\Property(property: "accuracy", type: "number", description: "GPS accuracy in meters", nullable: true, example: 5.2),
                    new OA\Property(property: "speed", type: "number", description: "Speed in m/s", nullable: true, example: 11.4),
                    new OA\Property(property: "heading", type: "number", description: "Heading in degrees (0-360)", nullable: true, example: 180)
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

        if (!$personnel->securityTeam || $booking->security_team_id !== $personnel->securityTeam->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        $location = LocationTracking::create([
            'booking_id' => $validated['booking_id'],
            'security_personnel_id' => $personnel->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'tracked_at' => now(),
        ]);
        event(new LocationUpdated($location));

        return response()->json([
            'status' => 'success',
            'message' => 'Location updated',
        ]);
    }
}
