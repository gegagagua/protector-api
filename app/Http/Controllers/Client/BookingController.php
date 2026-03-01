<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
use App\Models\SecurityTeam;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    #[OA\Get(
        path: "/api/client/services",
        summary: "Get available services",
        tags: ["Client Booking"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Available services",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "services", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function getServices(): JsonResponse
    {
        $services = [
            [
                'type' => 'armed',
                'name' => 'იარაღიანი დაცვა',
                'description' => 'იარაღიანი დაცვის სერვისი',
                'base_price_per_hour' => 100,
                'base_price_per_personnel' => 50,
            ],
            [
                'type' => 'unarmed',
                'name' => 'უიარაღო დაცვა',
                'description' => 'უიარაღო დაცვის სერვისი',
                'base_price_per_hour' => 70,
                'base_price_per_personnel' => 40,
            ],
        ];

        return response()->json([
            'status' => 'success',
            'services' => $services,
        ]);
    }

    #[OA\Get(
        path: "/api/client/vehicles",
        summary: "Get available vehicles",
        tags: ["Client Booking"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Available vehicles"
            )
        ]
    )]
    public function getVehicles(): JsonResponse
    {
        $vehicles = Vehicle::available()
            ->select('id', 'make', 'model', 'vehicle_type', 'color')
            ->get();

        return response()->json([
            'status' => 'success',
            'vehicles' => $vehicles,
        ]);
    }

    #[OA\Post(
        path: "/api/client/bookings",
        summary: "Create a new booking",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["service_type", "security_personnel_count", "persons_to_protect_count", "address", "start_time", "duration_hours", "booking_type"],
                properties: [
                    new OA\Property(property: "service_type", type: "string", enum: ["armed", "unarmed"]),
                    new OA\Property(property: "security_personnel_count", type: "integer", example: 2),
                    new OA\Property(property: "persons_to_protect_count", type: "integer", example: 1),
                    new OA\Property(property: "vehicle_id", type: "integer", nullable: true),
                    new OA\Property(property: "address", type: "string"),
                    new OA\Property(property: "latitude", type: "number", format: "float", nullable: true),
                    new OA\Property(property: "longitude", type: "number", format: "float", nullable: true),
                    new OA\Property(property: "start_time", type: "string", format: "date-time"),
                    new OA\Property(property: "duration_hours", type: "integer", example: 4),
                    new OA\Property(property: "booking_type", type: "string", enum: ["immediate", "scheduled"]),
                    new OA\Property(property: "persons", type: "array", items: new OA\Items(type: "object"))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Booking created"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $client = $request->user();

        // Check if client is verified (required for booking)
        if (!$client->isVerified() && $request->input('booking_type') === 'immediate') {
            return response()->json([
                'status' => 'error',
                'message' => 'Verification required to create booking',
                'verification_required' => true,
            ], 403);
        }

        $validated = $request->validate([
            'service_type' => 'required|in:armed,unarmed',
            'security_personnel_count' => 'required|integer|min:1|max:10',
            'persons_to_protect_count' => 'required|integer|min:1',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'start_time' => 'required|date',
            'duration_hours' => 'required|integer|min:1|max:24',
            'booking_type' => 'required|in:immediate,scheduled',
            'persons' => 'nullable|array',
            'persons.*.name' => 'required|string|max:255',
            'persons.*.phone' => 'nullable|string',
            'persons.*.notes' => 'nullable|string',
        ]);

        // Calculate price
        $basePricePerHour = $validated['service_type'] === 'armed' ? 100 : 70;
        $basePricePerPersonnel = $validated['service_type'] === 'armed' ? 50 : 40;
        $totalAmount = ($basePricePerHour + ($basePricePerPersonnel * $validated['security_personnel_count'])) * $validated['duration_hours'];

        DB::beginTransaction();
        try {
            $booking = Booking::create([
                'client_id' => $client->id,
                'service_type' => $validated['service_type'],
                'security_personnel_count' => $validated['security_personnel_count'],
                'persons_to_protect_count' => $validated['persons_to_protect_count'],
                'vehicle_id' => $validated['vehicle_id'] ?? null,
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'start_time' => $validated['start_time'],
                'end_time' => now()->parse($validated['start_time'])->addHours($validated['duration_hours']),
                'duration_hours' => $validated['duration_hours'],
                'booking_type' => $validated['booking_type'],
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'payment_status' => 'pending',
            ]);

            // Add persons to protect
            if (!empty($validated['persons'])) {
                foreach ($validated['persons'] as $person) {
                    $booking->bookingPersons()->create([
                        'name' => $person['name'],
                        'phone' => $person['phone'] ?? null,
                        'notes' => $person['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Booking created successfully',
                'booking' => $booking->load(['bookingPersons', 'vehicle']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create booking',
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/client/bookings",
        summary: "Get client bookings",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "Bookings list")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $client = $request->user();
        $status = $request->query('status');

        $query = $client->bookings()->with(['securityTeam', 'vehicle', 'rating']);

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->latest()->paginate(15);

        return response()->json([
            'status' => 'success',
            'bookings' => $bookings,
        ]);
    }

    #[OA\Get(
        path: "/api/client/bookings/{id}",
        summary: "Get booking details",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "Booking details"),
            new OA\Response(response: 404, description: "Booking not found")
        ]
    )]
    public function show(Request $request, $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()
            ->with(['securityTeam.personnel', 'vehicle', 'bookingPersons', 'messages', 'payments', 'rating'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'booking' => $booking,
        ]);
    }

    #[OA\Post(
        path: "/api/client/bookings/{id}/cancel",
        summary: "Cancel a booking",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reason", type: "string", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Booking cancelled"),
            new OA\Response(response: 403, description: "Cannot cancel booking")
        ]
    )]
    public function cancel(Request $request, $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->findOrFail($id);

        if (!$booking->canBeCancelled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This booking cannot be cancelled',
            ], 403);
        }

        $refundAmount = $booking->calculateRefund();

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
            'refunded_amount' => $refundAmount,
            'payment_status' => $refundAmount < $booking->total_amount ? 'partially_refunded' : 'fully_refunded',
        ]);

        // TODO: Process refund through payment gateway

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled successfully',
            'refund_amount' => $refundAmount,
            'booking' => $booking,
        ]);
    }
}
