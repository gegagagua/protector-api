<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\SecurityTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: "/api/admin/dashboard",
        summary: "Get dashboard statistics",
        tags: ["Admin Dashboard"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Dashboard data")]
    )]
    public function index(Request $request): JsonResponse
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        $stats = [
            'bookings' => [
                'active_today' => Booking::whereDate('start_time', $today)->whereIn('status', ['confirmed', 'ongoing', 'arrived'])->count(),
                'pending' => Booking::pending()->count(),
                'completed_today' => Booking::whereDate('completed_at', $today)->completed()->count(),
                'completed_this_week' => Booking::where('completed_at', '>=', $thisWeek)->completed()->count(),
                'completed_this_month' => Booking::where('completed_at', '>=', $thisMonth)->completed()->count(),
            ],
            'teams' => [
                'available' => SecurityTeam::available()->count(),
                'busy' => SecurityTeam::where('status', 'busy')->count(),
                'total' => SecurityTeam::where('is_active', true)->count(),
            ],
            'clients' => [
                'total' => Client::count(),
                'verified' => Client::verified()->count(),
                'pending_verification' => Client::pending()->count(),
            ],
            'revenue' => [
                'today' => Payment::whereDate('paid_at', $today)->where('status', 'completed')->sum('amount'),
                'this_week' => Payment::where('paid_at', '>=', $thisWeek)->where('status', 'completed')->sum('amount'),
                'this_month' => Payment::where('paid_at', '>=', $thisMonth)->where('status', 'completed')->sum('amount'),
            ],
        ];

        return response()->json([
            'status' => 'success',
            'stats' => $stats,
        ]);
    }
}
