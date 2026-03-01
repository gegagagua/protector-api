<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\SecurityTeam;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function summary(): JsonResponse
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $bookingStats = Booking::selectRaw('status, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')
            ->pluck('total', 'status');

        $topTeams = SecurityTeam::withAvg('ratings', 'rating')
            ->withCount('bookings')
            ->orderByDesc('ratings_avg_rating')
            ->limit(10)
            ->get();

        $revenue = [
            'today' => Payment::whereDate('paid_at', now())->where('status', 'completed')->sum('amount'),
            'this_week' => Payment::whereBetween('paid_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->where('status', 'completed')
                ->sum('amount'),
            'this_month' => Payment::whereBetween('paid_at', [$from, $to])
                ->where('status', 'completed')
                ->sum('amount'),
        ];

        $ratings = [
            'average' => (float) Rating::avg('rating'),
            'total_reviews' => Rating::count(),
        ];

        return response()->json([
            'status' => 'success',
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'bookings' => $bookingStats,
            'revenue' => $revenue,
            'ratings' => $ratings,
            'top_teams' => $topTeams,
        ]);
    }
}
