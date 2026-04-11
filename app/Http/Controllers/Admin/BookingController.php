<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SecurityTeam;
use App\Services\Booking\BookingStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    /** @var list<string> */
    private const ACTIVE_BOOKING_STATUSES = ['pending', 'confirmed', 'ongoing', 'arrived'];

    public function __construct(private readonly BookingStateMachine $stateMachine)
    {
    }

    #[OA\Get(
        path: "/api/admin/bookings/active",
        summary: "Get active bookings",
        description: "Returns all non-terminal bookings (pending, confirmed, ongoing, arrived) with full booking payload, assigned security team including personnel, client, vehicle, protected persons, recent messages, payments, and rating when present.",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Active bookings with team and relations")]
    )]
    public function active(): JsonResponse
    {
        $bookings = Booking::query()
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->with([
                'client',
                'securityTeam.personnel',
                'vehicle',
                'bookingPersons',
                'messages' => fn ($q) => $q->latest('created_at')->limit(100),
                'payments',
                'rating',
            ])
            ->latest('start_time')
            ->get();

        return response()->json([
            'status' => 'success',
            'bookings' => $bookings,
        ]);
    }

    #[OA\Get(
        path: "/api/admin/bookings",
        summary: "Get all bookings",
        description: "Returns paginated bookings list for admin operations with optional status filter.",
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
        description: "Returns full booking details for admin review and management.",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
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
        description: "Assigns an available team to pending booking and transitions status to confirmed.",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["security_team_id"],
                properties: [
                    new OA\Property(property: "security_team_id", type: "integer", description: "ID of available security team to assign", example: 3)
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
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

        $booking = $this->stateMachine->transition($booking, 'confirmed', [
            'security_team_id' => $validated['security_team_id'],
        ]);

        $team->update(['status' => 'busy']);

        // TODO: Send notification to security team

        return response()->json([
            'status' => 'success',
            'message' => 'Security team assigned successfully',
            'booking' => $booking->load('securityTeam'),
        ]);
    }

    #[OA\Put(
        path: "/api/admin/bookings/{id}",
        summary: "Update booking",
        description: "Updates editable booking attributes such as start time, duration, team, and notes.",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "start_time", type: "string", format: "date-time", description: "Updated booking start date/time", example: "2026-03-10T12:00:00Z"),
                    new OA\Property(property: "duration_hours", type: "integer", description: "Updated duration in hours", example: 4),
                    new OA\Property(property: "security_team_id", type: "integer", description: "Reassign booking to another team", example: 2),
                    new OA\Property(property: "admin_notes", type: "string", description: "Internal admin notes", example: "Client requested earlier arrival")
                ]
            )
        ),
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

    #[OA\Post(
        path: "/api/admin/bookings/{id}/complete",
        summary: "Complete booking by admin",
        description: "Completes booking from admin side and releases assigned team availability.",
        tags: ["Admin Bookings"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Booking completed")]
    )]
    public function complete($id): JsonResponse
    {
        $booking = Booking::with('securityTeam')->findOrFail($id);
        $booking = $this->stateMachine->transition($booking, 'completed');

        if ($booking->securityTeam) {
            $booking->securityTeam->update(['status' => 'available']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking completed successfully',
            'booking' => $booking,
        ]);
    }
}
