<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
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
    private const GUARD_OUTFIT_IMAGES = [
        'tactical' => 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=800&q=80',
        'formal' => 'https://images.unsplash.com/photo-1617127365659-c47fa864d8bc?auto=format&fit=crop&w=800&q=80',
        'casual' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=800&q=80',
    ];

    private const STATUS_LABELS = [
        'en' => [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'ongoing' => 'Ongoing',
            'arrived' => 'Arrived',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        'ka' => [
            'pending' => 'მოლოდინში',
            'confirmed' => 'დადასტურებული',
            'ongoing' => 'მიმდინარეობს',
            'arrived' => 'ადგილზე მივიდა',
            'completed' => 'დასრულებული',
            'cancelled' => 'გაუქმებული',
        ],
    ];
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

    private function localeFromRequest(Request $request): string
    {
        $language = strtolower((string) ($request->header('language') ?: $request->header('Accept-Language', 'en')));
        return str_starts_with($language, 'ka') ? 'ka' : 'en';
    }

    private function extractOutfit(?string $adminNotes): ?string
    {
        if (!$adminNotes || !str_starts_with($adminNotes, 'outfit:')) {
            return null;
        }

        return substr($adminNotes, 7) ?: null;
    }

    private function enrichBooking(Booking $booking, string $locale): array
    {
        $data = $booking->toArray();
        $outfit = $this->extractOutfit($booking->admin_notes);
        $status = (string) $booking->status;

        $data['status_label'] = self::STATUS_LABELS[$locale][$status] ?? $status;
        $data['guard_outfit'] = $outfit;
        $data['guard_outfit_image_url'] = $outfit ? (self::GUARD_OUTFIT_IMAGES[$outfit] ?? null) : null;

        return $data;
    }

    private function enrichCollection(iterable $bookings, string $locale): array
    {
        $result = [];
        foreach ($bookings as $booking) {
            $result[] = $this->enrichBooking($booking, $locale);
        }

        return $result;
    }

    #[OA\Get(
        path: "/api/client/services",
        summary: "Get available services",
        description: "Returns security service catalog including pricing components.",
        tags: ["Client Booking"],
        parameters: [
            new OA\Parameter(name: "language", description: "Response language: ka or en", in: "header", required: false, schema: new OA\Schema(type: "string", enum: ["ka", "en"]))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Available services",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "services", type: "array", description: "List of service definitions with pricing metadata", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function getServices(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $services = $locale === 'ka'
            ? [
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
            ]
            : [
                [
                    'type' => 'armed',
                    'name' => 'Armed Security',
                    'description' => 'Armed security protection service.',
                    'base_price_per_hour' => $this->pricingFor('armed')['base_per_hour'],
                    'base_price_per_personnel' => $this->pricingFor('armed')['base_per_personnel'],
                    'min_duration_hours' => 1,
                ],
                [
                    'type' => 'unarmed',
                    'name' => 'Unarmed Security',
                    'description' => 'Unarmed security protection service.',
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
        parameters: [
            new OA\Parameter(name: "language", description: "Response language: ka or en", in: "header", required: false, schema: new OA\Schema(type: "string", enum: ["ka", "en"]))
        ],
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
            ->select('id', 'make', 'model', 'vehicle_type', 'color', 'image_url')
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
        parameters: [
            new OA\Parameter(name: "language", description: "Response language: ka or en", in: "header", required: false, schema: new OA\Schema(type: "string", enum: ["ka", "en"]))
        ],
        responses: [new OA\Response(response: 200, description: "Wizard configuration")]
    )]
    public function getWizardConfig(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);

        return response()->json([
            'status' => 'success',
            'config' => [
                'min_persons_to_protect' => 1,
                'max_persons_to_protect' => 20,
                'min_security_personnel' => 1,
                'max_security_personnel' => 10,
                'min_duration_hours' => 1,
                'max_duration_hours' => 24,
                'min_schedule_notice_minutes' => 30,
                'max_schedule_days_ahead' => 90,
                'outfits' => [
                    [
                        'code' => 'tactical',
                        'title' => $locale === 'ka' ? 'ტაქტიკური ფორმა' : 'Tactical outfit',
                        'image_url' => self::GUARD_OUTFIT_IMAGES['tactical'],
                    ],
                    [
                        'code' => 'formal',
                        'title' => $locale === 'ka' ? 'ოფიციალური ფორმა' : 'Formal outfit',
                        'image_url' => self::GUARD_OUTFIT_IMAGES['formal'],
                    ],
                    [
                        'code' => 'casual',
                        'title' => $locale === 'ka' ? 'ყოველდღიური ფორმა' : 'Casual outfit',
                        'image_url' => self::GUARD_OUTFIT_IMAGES['casual'],
                    ],
                ],
                'booking_types' => [
                    ['code' => 'immediate', 'title' => $locale === 'ka' ? 'ახლავე' : 'Immediate'],
                    ['code' => 'scheduled', 'title' => $locale === 'ka' ? 'დაგეგმილი' : 'Scheduled'],
                ],
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/client/bookings/quote",
        summary: "Calculate booking quote",
        description: "Calculates estimated booking amount for selected service, guard count, and duration.",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["service_type", "security_personnel_count", "duration_hours"],
                properties: [
                    new OA\Property(property: "service_type", type: "string", description: "Requested security service type", enum: ["armed", "unarmed"], example: "armed"),
                    new OA\Property(property: "security_personnel_count", type: "integer", description: "Number of guards requested", example: 2),
                    new OA\Property(property: "duration_hours", type: "integer", description: "Service duration in hours", example: 4)
                ]
            )
        ),
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
                required: ["service_type", "security_personnel_count", "persons_to_protect_count", "address", "duration_hours", "booking_type"],
                properties: [
                    new OA\Property(property: "service_type", type: "string", description: "Security service type.", enum: ["armed", "unarmed"], default: "unarmed", example: "armed"),
                    new OA\Property(property: "security_personnel_count", type: "integer", description: "Requested number of guards.", minimum: 1, maximum: 10, default: 1, example: 2),
                    new OA\Property(property: "persons_to_protect_count", type: "integer", description: "How many people are being protected.", minimum: 1, default: 1, example: 1),
                    new OA\Property(property: "vehicle_id", type: "integer", description: "Optional preferred vehicle ID. Default is null (auto assignment).", nullable: true, default: null, example: 1),
                    new OA\Property(property: "address", type: "string", description: "Service address/location.", example: "Rustaveli Ave 10, Tbilisi"),
                    new OA\Property(property: "latitude", type: "number", format: "float", description: "Address latitude. Default is null.", nullable: true, default: null, example: 41.7151),
                    new OA\Property(property: "longitude", type: "number", format: "float", description: "Address longitude. Default is null.", nullable: true, default: null, example: 44.8271),
                    new OA\Property(property: "start_time", type: "string", format: "date-time", description: "Required for scheduled booking. For immediate booking, server auto-uses current time.", example: "2026-03-20T12:00:00Z"),
                    new OA\Property(property: "duration_hours", type: "integer", description: "Booking duration in hours.", minimum: 1, maximum: 24, default: 1, example: 4),
                    new OA\Property(property: "booking_type", type: "string", description: "Booking execution type.", enum: ["immediate", "scheduled"], default: "immediate", example: "immediate"),
                    new OA\Property(property: "guard_outfit", type: "string", description: "Preferred guard outfit. Default is null.", enum: ["tactical", "formal", "casual"], nullable: true, default: null, example: "formal"),
                    new OA\Property(
                        property: "persons",
                        type: "array",
                        description: "Optional persons details. If omitted/empty, server auto-adds authenticated client as first protected person.",
                        default: [],
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "name", type: "string", description: "Protected person's full name", example: "Gega Gagua"),
                                new OA\Property(property: "phone", type: "string", description: "Protected person's phone", nullable: true, example: "+995555123456"),
                                new OA\Property(property: "notes", type: "string", description: "Optional notes", nullable: true, example: "VIP client")
                            ],
                            type: "object"
                        )
                    )
                ],
                example: [
                    "service_type" => "armed",
                    "security_personnel_count" => 2,
                    "persons_to_protect_count" => 1,
                    "vehicle_id" => 1,
                    "address" => "Rustaveli Ave 10, Tbilisi",
                    "latitude" => 41.7151,
                    "longitude" => 44.8271,
                    "start_time" => "2026-03-20T12:00:00Z",
                    "duration_hours" => 4,
                    "booking_type" => "immediate",
                    "guard_outfit" => "formal",
                    "persons" => [
                        ["name" => "Gega Gagua", "phone" => "+995555123456", "notes" => "VIP client"]
                    ]
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
            'start_time' => 'nullable|date',
            'duration_hours' => 'required|integer|min:1|max:24',
            'booking_type' => 'required|in:immediate,scheduled',
            'guard_outfit' => 'nullable|in:tactical,formal,casual',
            'persons' => 'nullable|array',
            'persons.*.name' => 'nullable|string|max:255',
            'persons.*.phone' => 'nullable|string',
            'persons.*.notes' => 'nullable|string',
        ]);

        $persons = collect($validated['persons'] ?? [])
            ->filter(function ($person) {
                if (!is_array($person)) {
                    return false;
                }

                return !empty($person['name']) || !empty($person['phone']) || !empty($person['notes']);
            })
            ->map(function (array $person): array {
                return [
                    'name' => isset($person['name']) ? trim((string) $person['name']) : null,
                    'phone' => $person['phone'] ?? null,
                    'notes' => $person['notes'] ?? null,
                ];
            })
            ->filter(fn (array $person) => !empty($person['name']))
            ->values()
            ->all();

        if (count($persons) === 0) {
            $selfName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));

            $persons = [[
                'name' => $selfName !== '' ? $selfName : ('Client #' . $client->id),
                'phone' => $client->phone ?? null,
                'notes' => 'Auto-added from authenticated client profile.',
            ]];
        }

        $totalAmount = $this->calculatePrice(
            $validated['service_type'],
            (int) $validated['security_personnel_count'],
            (int) $validated['duration_hours']
        );

        if ($validated['booking_type'] === 'scheduled' && empty($validated['start_time'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'start_time is required for scheduled booking.',
            ], 422);
        }

        $startAt = $validated['booking_type'] === 'immediate'
            ? now()
            : Carbon::parse($validated['start_time']);

        if ($validated['booking_type'] === 'scheduled') {
            if ($startAt->lt(now()->addMinutes(30))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Scheduled booking must be at least 30 minutes in the future.',
                ], 422);
            }

            if ($startAt->gt(now()->addDays(90))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Scheduled booking cannot be more than 90 days ahead.',
                ], 422);
            }
        }

        $booking = DB::transaction(function () use ($client, $validated, $totalAmount, $startAt, $persons) {
            $booking = Booking::create([
                'client_id' => $client->id,
                'service_type' => $validated['service_type'],
                'security_personnel_count' => $validated['security_personnel_count'],
                'persons_to_protect_count' => $validated['persons_to_protect_count'],
                'vehicle_id' => $validated['vehicle_id'] ?? null,
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'start_time' => $startAt,
                'end_time' => Carbon::parse($startAt)->addHours((int) $validated['duration_hours']),
                'duration_hours' => $validated['duration_hours'],
                'booking_type' => $validated['booking_type'],
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'payment_status' => 'pending',
                'admin_notes' => isset($validated['guard_outfit']) ? "outfit:{$validated['guard_outfit']}" : null,
            ]);

            // Add persons to protect
            foreach ($persons as $person) {
                $booking->bookingPersons()->create([
                    'name' => $person['name'],
                    'phone' => $person['phone'] ?? null,
                    'notes' => $person['notes'] ?? null,
                ]);
            }

            return $booking->load(['bookingPersons', 'vehicle']);
        });

        $locale = $this->localeFromRequest($request);
        return response()->json([
            'status' => 'success',
            'message' => 'Booking created successfully',
            'booking' => $this->enrichBooking($booking, $locale),
        ], 201);
    }

    #[OA\Get(
        path: "/api/client/bookings",
        summary: "Get client bookings",
        description: "Returns paginated bookings for authenticated client with optional status filter.",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "language", description: "Response language: ka or en", in: "header", required: false, schema: new OA\Schema(type: "string", enum: ["ka", "en"])),
            new OA\Parameter(name: "status", description: "Optional booking status filter", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Bookings list")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $client = $request->user();
        $status = $request->query('status');

        $query = $client->bookings()->with(['securityTeam', 'vehicle', 'rating']);

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->latest()->paginate(15);
        $items = $this->enrichCollection($bookings->items(), $locale);
        $bookings->setCollection(collect($items));

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
        parameters: [
            new OA\Parameter(name: "language", description: "Response language: ka or en", in: "header", required: false, schema: new OA\Schema(type: "string", enum: ["ka", "en"]))
        ],
        responses: [new OA\Response(response: 200, description: "Active bookings list")]
    )]
    public function active(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $client = $request->user();
        $query = $client->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'ongoing', 'arrived'])
            ->with(['securityTeam', 'vehicle'])
            ->latest();

        $bookings = $query->get();

        return response()->json([
            'status' => 'success',
            'bookings' => $this->enrichCollection($bookings, $locale),
        ]);
    }

    #[OA\Get(
        path: "/api/client/bookings/history",
        summary: "Get booking history",
        description: "Returns completed and cancelled bookings with payment and rating details.",
        tags: ["Client Booking"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "language", description: "Response language: ka or en", in: "header", required: false, schema: new OA\Schema(type: "string", enum: ["ka", "en"]))
        ],
        responses: [new OA\Response(response: 200, description: "Booking history list")]
    )]
    public function history(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $client = $request->user();
        $query = $client->bookings()
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['securityTeam', 'vehicle', 'payments', 'rating'])
            ->latest();

        $bookings = $query->paginate(20);
        $items = $this->enrichCollection($bookings->items(), $locale);
        $bookings->setCollection(collect($items));

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
        parameters: [
            new OA\Parameter(name: "language", description: "Response language: ka or en", in: "header", required: false, schema: new OA\Schema(type: "string", enum: ["ka", "en"])),
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Booking details"),
            new OA\Response(response: 404, description: "Booking not found")
        ]
    )]
    public function show(Request $request, $id): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $client = $request->user();
        $query = $client->bookings()
            ->with(['securityTeam.personnel', 'vehicle', 'bookingPersons', 'messages', 'payments', 'rating'])
            ->whereKey($id);

        $booking = $query->firstOrFail();

        return response()->json([
            'status' => 'success',
            'booking' => $this->enrichBooking($booking, $locale),
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
                    new OA\Property(property: "reason", type: "string", description: "Optional cancellation reason", nullable: true, example: "Plan changed")
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Booking cancelled"),
            new OA\Response(response: 403, description: "Cannot cancel booking")
        ]
    )]
    public function cancel(Request $request, $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->find($id);

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Booking not found for current client.',
            ], 404);
        }

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
