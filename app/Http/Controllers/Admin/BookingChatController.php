<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BookingChatController extends Controller
{
    #[OA\Get(
        path: '/api/admin/bookings/{id}/messages',
        summary: 'Get booking chat thread (admin)',
        description: 'Returns paginated messages between the client and security personnel for monitoring.',
        tags: ['Admin Bookings'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'Booking ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated messages'),
            new OA\Response(response: 404, description: 'Booking not found'),
        ]
    )]
    public function index(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()->findOrFail($id);

        $messages = $booking->messages()
            ->with(['sender', 'receiver'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->query('per_page', 50))));

        return response()->json([
            'status' => 'success',
            'booking_id' => $booking->id,
            'messages' => $messages,
        ]);
    }
}
