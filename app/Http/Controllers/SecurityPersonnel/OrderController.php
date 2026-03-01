<?php

namespace App\Http\Controllers\SecurityPersonnel;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    #[OA\Get(
        path: "/api/security/orders",
        summary: "Get active orders for security personnel",
        tags: ["Security Personnel Orders"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Orders list")]
    )]
    public function index(Request $request): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;

        if (!$team) {
            return response()->json([
                'status' => 'success',
                'orders' => [],
            ]);
        }

        $orders = Booking::where('security_team_id', $team->id)
            ->whereIn('status', ['confirmed', 'ongoing', 'arrived'])
            ->with(['client', 'bookingPersons', 'vehicle'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'orders' => $orders,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;

        if (!$team) {
            return response()->json([
                'status' => 'success',
                'orders' => [],
            ]);
        }

        $orders = Booking::where('security_team_id', $team->id)
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['client', 'vehicle'])
            ->latest('completed_at')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'orders' => $orders,
        ]);
    }

    #[OA\Get(
        path: "/api/security/orders/{id}",
        summary: "Get order details",
        tags: ["Security Personnel Orders"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Order details")]
    )]
    public function show(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;

        $order = Booking::where('security_team_id', $team->id)
            ->with(['client', 'bookingPersons', 'vehicle', 'messages', 'locationTracking' => function($query) {
                $query->latest('tracked_at')->limit(1);
            }])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'order' => $order,
        ]);
    }
}
