<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SecurityTeam;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    #[OA\Get(
        path: "/api/admin/bookings",
        summary: "Get all bookings",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Bookings list")]
    )]
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        
        $query = Booking::with(['client', 'securityTeam', 'vehicle']);

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'bookings' => $bookings,
        ]);
    }

    #[OA\Get(
        path: "/api/admin/bookings/{id}",
        summary: "Get booking details",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Booking details")]
    )]
    public function show($id): JsonResponse
    {
        $booking = Booking::with(['client', 'securityTeam.personnel', 'vehicle', 'bookingPersons', 'messages', 'payments', 'rating'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'booking' => $booking,
        ]);
    }

    #[OA\Post(
        path: "/api/admin/bookings/{id}/assign-team",
        summary: "Assign security team to booking",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["security_team_id"],
                properties: [
                    new OA\Property(property: "security_team_id", type: "integer")
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Team assigned")]
    )]
    public function assignTeam(Request $request, $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'security_team_id' => 'required|exists:security_teams,id',
        ]);

        $team = SecurityTeam::findOrFail($validated['security_team_id']);

        // Check if team is available
        if ($team->status !== 'available') {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected team is not available',
            ], 400);
        }

        $booking->update([
            'security_team_id' => $validated['security_team_id'],
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $team->update(['status' => 'busy']);

        // TODO: Send notification to security team

        return response()->json([
            'status' => 'success',
            'message' => 'Security team assigned successfully',
            'booking' => $booking->fresh(['securityTeam']),
        ]);
    }

    #[OA\Put(
        path: "/api/admin/bookings/{id}",
        summary: "Update booking",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Booking updated")]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'start_time' => 'sometimes|date',
            'duration_hours' => 'sometimes|integer|min:1',
            'security_team_id' => 'sometimes|exists:security_teams,id',
            'admin_notes' => 'sometimes|string',
        ]);

        $booking->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking updated successfully',
            'booking' => $booking->fresh(),
        ]);
    }
}
