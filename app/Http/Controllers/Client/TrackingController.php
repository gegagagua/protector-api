<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LocationTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TrackingController extends Controller
{
    #[OA\Get(
        path: '/api/client/bookings/{id}/tracking',
        summary: 'Get real-time tracking for booking',
        description: 'Returns the latest guard GPS point (latitude/longitude as floats), a Google Maps URL, optional recent trail for polylines, and booking context.',
        tags: ['Client Tracking'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'Booking ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(
                name: 'trail_limit',
                description: 'Max recent security GPS points (newest last), 0–100. Default 40. Use for map polyline.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 40, maximum: 100)
            ),
        ],
        responses: [new OA\Response(response: 200, description: 'Tracking data')]
    )]
    public function getTracking(Request $request, $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->findOrFail($id);

        $trailLimit = min(100, max(0, (int) $request->query('trail_limit', 40)));

        $latestLocation = $booking->locationTracking()
            ->where('security_personnel_id', '!=', null)
            ->latest('tracked_at')
            ->first();

        $trail = [];
        if ($trailLimit > 0) {
            $trail = $booking->locationTracking()
                ->where('security_personnel_id', '!=', null)
                ->latest('tracked_at')
                ->limit($trailLimit)
                ->get()
                ->sortBy('tracked_at')
                ->values()
                ->map(fn (LocationTracking $row) => $row->toMapArray())
                ->all();
        }

        return response()->json([
            'status' => 'success',
            'booking' => $booking->load(['securityTeam.personnel', 'vehicle', 'service']),
            'booking_status' => $booking->status,
            'location' => $latestLocation ? $latestLocation->toMapArray() : null,
            'current_location' => $latestLocation ? $latestLocation->toMapArray() : null,
            'recent_locations' => $trail,
        ]);
    }
}
