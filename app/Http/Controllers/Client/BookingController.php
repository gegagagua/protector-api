<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Services\Booking\BookingStateMachine;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingStateMachine $stateMachine,
        private readonly PaymentGateway $paymentGateway
    )
    {
    }

    private function pricingFor(string $serviceType): array
    {
        if ($serviceType === 'armed') {
            return ['base_per_hour' => 100, 'base_per_personnel' => 50];
        }

        return ['base_per_hour' => 70, 'base_per_personnel' => 40];
    }

    private function calculatePrice(string $serviceType, int $securityCount, int $durationHours): float
    {
        $pricing = $this->pricingFor($serviceType);

        return (float) (($pricing['base_per_hour'] + ($pricing['base_per_personnel'] * $securityCount)) * $durationHours);
    }

    #[OA\Get(
        path: "/api/client/services",
        summary: "Get available services",
        description: "Returns security service catalog including pricing components.",
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
                'base_price_per_hour' => $this->pricingFor('armed')['base_per_hour'],
                'base_price_per_personnel' => $this->pricingFor('armed')['base_per_personnel'],
                'min_duration_hours' => 1,
            ],
            [
                'type' => 'unarmed',
                'name' => 'უიარაღო დაცვა',
                'description' => 'უიარაღო დაცვის სერვისი',
                'base_price_per_hour' => $this->pricingFor('unarmed')['base_per_hour'],
                'base_price_per_personnel' => $this->pricingFor('unarmed')['base_per_personnel'],
                'min_duration_hours' => 1,
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
        description: "Returns currently available vehicles that can be selected during booking.",
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

    #[OA\Get(
        path: "/api/client/wizard-config",
        summary: "Get booking wizard config",
        description: "Returns UI constraints and option lists for booking flow steps.",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Wizard configuration")]
    )]
    public function getWizardConfig(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'config' => [
                'min_persons_to_protect' => 1,
                'max_persons_to_protect' => 20,
                'min_security_personnel' => 1,
                'max_security_personnel' => 10,
                'min_duration_hours' => 1,
                'max_duration_hours' => 24,
                'outfits' => ['tactical', 'formal', 'casual'],
                'booking_types' => ['immediate', 'scheduled'],
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/client/bookings/quote",
        summary: "Calculate booking quote",
        description: "Calculates estimated booking amount for selected service, guard count, and duration.",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Quote calculated")]
    )]
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_type' => 'required|in:armed,unarmed',
            'security_personnel_count' => 'required|integer|min:1|max:10',
            'duration_hours' => 'required|integer|min:1|max:24',
        ]);

        $amount = $this->calculatePrice(
            $validated['service_type'],
            (int) $validated['security_personnel_count'],
            (int) $validated['duration_hours']
        );

        return response()->json([
            'status' => 'success',
            'quote' => [
                'currency' => 'GEL',
                'amount' => $amount,
                'service_type' => $validated['service_type'],
                'security_personnel_count' => (int) $validated['security_personnel_count'],
                'duration_hours' => (int) $validated['duration_hours'],
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/client/bookings",
        summary: "Create a new booking",
        description: "Creates a new booking request after verification checks and quote calculation.",
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

        // Booking requires verified profile.
        if (!$client->isVerified()) {
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
            'guard_outfit' => 'nullable|in:tactical,formal,casual',
            'persons' => 'nullable|array',
            'persons.*.name' => 'required|string|max:255',
            'persons.*.phone' => 'nullable|string',
            'persons.*.notes' => 'nullable|string',
        ]);

        $totalAmount = $this->calculatePrice(
            $validated['service_type'],
            (int) $validated['security_personnel_count'],
            (int) $validated['duration_hours']
        );

        $booking = DB::transaction(function () use ($client, $validated, $totalAmount) {
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
                'end_time' => Carbon::parse($validated['start_time'])->addHours((int) $validated['duration_hours']),
                'duration_hours' => $validated['duration_hours'],
                'booking_type' => $validated['booking_type'],
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'payment_status' => 'pending',
                'admin_notes' => isset($validated['guard_outfit']) ? "outfit:{$validated['guard_outfit']}" : null,
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

            return $booking->load(['bookingPersons', 'vehicle']);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Booking created successfully',
            'booking' => $booking,
        ], 201);
    }

    #[OA\Get(
        path: "/api/client/bookings",
        summary: "Get client bookings",
        description: "Returns paginated bookings for authenticated client with optional status filter.",
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
        path: "/api/client/bookings/active",
        summary: "Get active bookings",
        description: "Returns active client bookings in pending/confirmed/ongoing/arrived states.",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Active bookings list")]
    )]
    public function active(Request $request): JsonResponse
    {
        $client = $request->user();
        $bookings = $client->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'ongoing', 'arrived'])
            ->with(['securityTeam', 'vehicle'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'bookings' => $bookings,
        ]);
    }

    #[OA\Get(
        path: "/api/client/bookings/history",
        summary: "Get booking history",
        description: "Returns completed and cancelled bookings with payment and rating details.",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Booking history list")]
    )]
    public function history(Request $request): JsonResponse
    {
        $client = $request->user();
        $bookings = $client->bookings()
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['securityTeam', 'vehicle', 'payments', 'rating'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'bookings' => $bookings,
        ]);
    }

    #[OA\Get(
        path: "/api/client/bookings/{id}",
        summary: "Get booking details",
        description: "Returns full booking details with team, chat, payment, and rating data.",
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
        description: "Cancels booking according to state machine rules and applies refund policy.",
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

        $refundAmount = $booking->calculateRefundAmount();
        $refundStatus = $booking->payment_status;

        if ($refundAmount > 0) {
            $latestPayment = $booking->payments()->latest()->first();

            if ($latestPayment) {
                $result = $this->paymentGateway->refund([
                    'booking_id' => $booking->id,
                    'payment_id' => $latestPayment->id,
                    'paid_amount' => (float) $latestPayment->amount,
                    'amount' => $refundAmount,
                    'transaction_id' => $latestPayment->transaction_id,
                ]);

                $latestPayment->update([
                    'status' => $result['status'],
                    'notes' => 'Refund processed on cancellation',
                    'payment_data' => array_merge($latestPayment->payment_data ?? [], ['refund' => $result]),
                ]);

                $refundStatus = $refundAmount < (float) $booking->paid_amount ? 'partially_refunded' : 'fully_refunded';
            }
        }

        $updated = $this->stateMachine->transition($booking, 'cancelled', [
            'cancellation_reason' => $request->input('reason'),
            'refunded_amount' => $refundAmount,
            'payment_status' => $refundStatus,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled successfully',
            'refund_amount' => $refundAmount,
            'booking' => $updated,
        ]);
    }
}
