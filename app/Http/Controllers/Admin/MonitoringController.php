<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LocationTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MonitoringController extends Controller
{
    #[OA\Get(
        path: "/api/admin/monitoring/live",
        summary: "Get live tracking data",
        description: "Returns current active bookings with latest security team locations for monitoring map.",
        tags: ["Admin Monitoring"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Live tracking data")]
    )]
    public function liveTracking(): JsonResponse
    {
        $activeBookings = Booking::whereIn('status', ['ongoing', 'arrived'])
            ->with(['client', 'securityTeam.personnel', 'vehicle'])
            ->get();

        $trackingData = [];
        foreach ($activeBookings as $booking) {
            $latestLocation = $booking->locationTracking()
                ->where('security_personnel_id', '!=', null)
                ->latest('tracked_at')
                ->first();

            $trackingData[] = [
                'booking' => $booking,
                'location' => $latestLocation,
            ];
        }

        return response()->json([
            'status' => 'success',
            'tracking' => $trackingData,
        ]);
    }
}
