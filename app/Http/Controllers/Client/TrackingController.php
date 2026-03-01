<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LocationTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TrackingController extends Controller
{
    #[OA\Get(
        path: "/api/client/bookings/{id}/tracking",
        summary: "Get real-time tracking for booking",
        description: "Returns latest security team location and booking status for client tracking screen.",
        tags: ["Client Tracking"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Tracking data")]
    )]
    public function getTracking(Request $request, $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->findOrFail($id);

        $latestLocation = $booking->locationTracking()
            ->where('security_personnel_id', '!=', null)
            ->latest('tracked_at')
            ->first();

        return response()->json([
            'status' => 'success',
            'booking' => $booking->load(['securityTeam.personnel', 'vehicle']),
            'current_location' => $latestLocation,
            'booking_status' => $booking->status,
        ]);
    }
}
