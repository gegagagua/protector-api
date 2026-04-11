<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Vehicle;
use App\Services\Booking\BookingStateMachine;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
    ) {}

    /**
     * Hourly total = hourly_rate × guards × hours. If daily_rate > 0, total is capped at daily_rate × guards (per booking window up to 24h).
     */
    private function calculatePriceFromService(Service $service, int $securityCount, float $durationHours): float
    {
        $hourlyPart = (float) $service->hourly_rate * $securityCount * $durationHours;
        $dailyCapPerLine = (float) $service->daily_rate;

        if ($dailyCapPerLine <= 0) {
            return round($hourlyPart, 2);
        }

        $capped = $dailyCapPerLine * $securityCount;

        return round(min($hourlyPart, $capped), 2);
    }

    private function resolveActiveService(?int $serviceId, ?string $serviceSlug): ?Service
    {
        if ($serviceId) {
            return Service::query()->active()->whereKey($serviceId)->first();
        }
        if ($serviceSlug !== null && $serviceSlug !== '') {
            return Service::query()->active()->where('slug', $serviceSlug)->first();
        }

        return null;
    }

    /**
     * Validates end_time vs start and returns fractional hours in (1, 24], or JSON error response.
     */
    private function validateEndTimeWindow(Carbon $startAt, Carbon $endAt): JsonResponse|float
    {
        if ($endAt->lte($startAt)) {
            return response()->json([
                'status' => 'error',
                'message' => 'end_time must be after start_time.',
            ], 422);
        }

        $minutes = $startAt->diffInMinutes($endAt);
        if ($minutes < 60) {
            return response()->json([
                'status' => 'error',
                'message' => 'end_time must be at least 1 hour after the booking start.',
            ], 422);
        }

        if ($minutes > 24 * 60) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking duration cannot exceed 24 hours.',
            ], 422);
        }

        return $minutes / 60;
    }

    private function localeFromRequest(Request $request): string
    {
        $language = strtolower((string) ($request->header('language') ?: $request->header('Accept-Language', 'en')));

        return str_starts_with($language, 'ka') ? 'ka' : 'en';
    }

    private function extractOutfit(?string $adminNotes): ?string
    {
        if (! $adminNotes || ! str_starts_with($adminNotes, 'outfit:')) {
            return null;
        }

        return substr($adminNotes, 7) ?: null;
    }

    private function enrichBooking(Booking $booking, string $locale): array
    {
        $booking->loadMissing('service');
        $data = $booking->toArray();
        $outfit = $this->extractOutfit($booking->admin_notes);
        $status = (string) $booking->status;

        $data['status_label'] = self::STATUS_LABELS[$locale][$status] ?? $status;
        $data['guard_outfit'] = $outfit;
        $data['guard_outfit_image_url'] = $outfit ? (self::GUARD_OUTFIT_IMAGES[$outfit] ?? null) : null;

        if ($booking->service) {
            $data['service_catalog'] = [
                'id' => $booking->service->id,
                'slug' => $booking->service->slug,
                'name' => $booking->service->localizedName($locale),
                'description' => $booking->service->localizedDescription($locale),
                'icon' => $booking->service->icon?->value,
                'hourly_rate' => (float) $booking->service->hourly_rate,
                'daily_rate' => (float) $booking->service->daily_rate,
                'currency' => 'GEL',
                'requires_vehicle' => $booking->service->requires_vehicle,
            ];
        }

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
        path: '/api/client/services',
        summary: 'Get available services',
        description: 'Returns security service catalog including pricing components.',
        tags: ['Client Booking'],
        parameters: [
            new OA\Parameter(name: 'language', description: 'Response language: ka or en', in: 'header', required: false, schema: new OA\Schema(type: 'string', enum: ['ka', 'en'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Available services',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'services', type: 'array', description: 'List of service definitions with pricing metadata', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function getServices(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $rows = Service::query()->active()->ordered()->get();

        $services = $rows->map(function (Service $service) use ($locale) {
            return [
                'id' => $service->id,
                'slug' => $service->slug,
                'type' => $service->slug,
                'name' => $service->localizedName($locale),
                'description' => $service->localizedDescription($locale),
                'icon' => $service->icon?->value,
                'hourly_rate' => (float) $service->hourly_rate,
                'daily_rate' => (float) $service->daily_rate,
                'currency' => 'GEL',
                'requires_vehicle' => $service->requires_vehicle,
                'team_service_type' => $service->team_service_type,
                'min_duration_hours' => 1,
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'services' => $services,
        ]);
    }

    #[OA\Get(
        path: '/api/client/vehicles',
        summary: 'Get available vehicles',
        description: 'Returns currently available vehicles that can be selected during booking.',
        tags: ['Client Booking'],
        parameters: [
            new OA\Parameter(name: 'language', description: 'Response language: ka or en', in: 'header', required: false, schema: new OA\Schema(type: 'string', enum: ['ka', 'en'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Available vehicles'
            ),
        ]
    )]
    public function getVehicles(): JsonResponse
    {
        $vehicles = Vehicle::available()
            ->select('id', 'make', 'model', 'vehicle_type', 'role', 'color', 'image_url')
            ->get();

        return response()->json([
            'status' => 'success',
            'vehicles' => $vehicles,
        ]);
    }

    #[OA\Get(
        path: '/api/client/wizard-config',
        summary: 'Get booking wizard config',
        description: 'Returns UI constraints and option lists for booking flow steps.',
        tags: ['Client Booking'],
        parameters: [
            new OA\Parameter(name: 'language', description: 'Response language: ka or en', in: 'header', required: false, schema: new OA\Schema(type: 'string', enum: ['ka', 'en'])),
        ],
        responses: [new OA\Response(response: 200, description: 'Wizard configuration')]
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
                'currency' => 'GEL',
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
                    [
                        'code' => 'immediate',
                        'title' => $locale === 'ka' ? 'ახლავე' : 'Immediate',
                        'api_send_start_time' => false,
                    ],
                    [
                        'code' => 'scheduled',
                        'title' => $locale === 'ka' ? 'დაგეგმილი' : 'Scheduled',
                        'api_send_start_time' => true,
                    ],
                ],
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/client/bookings/quote',
        summary: 'Calculate booking quote',
        description: 'Calculates estimated GEL amount from catalog service (hourly rate × guards × hours, capped by daily rate × guards when daily_rate > 0). Send service_id (preferred) or service_type (service slug, e.g. armed, mobile_patrol).',
        tags: ['Client Booking'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['security_personnel_count', 'end_time'],
                properties: [
                    new OA\Property(property: 'service_id', type: 'integer', description: 'Catalog service id from GET /client/services', nullable: true, example: 1),
                    new OA\Property(property: 'service_type', type: 'string', description: 'Catalog service slug (legacy; same as slug). Required if service_id omitted.', nullable: true, example: 'armed'),
                    new OA\Property(property: 'security_personnel_count', type: 'integer', description: 'Number of guards requested', example: 2),
                    new OA\Property(property: 'start_time', type: 'string', format: 'date-time', description: 'Window start (defaults to now if omitted)', nullable: true, example: '2026-03-20T12:00:00Z'),
                    new OA\Property(property: 'end_time', type: 'string', format: 'date-time', description: 'Service end time (must be 1–24 hours after start)', example: '2026-03-20T16:00:00Z'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Quote calculated')]
    )]
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required_without:service_type', 'nullable', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'service_type' => 'required_without:service_id|nullable|string|max:64',
            'security_personnel_count' => 'required|integer|min:1|max:10',
            'start_time' => 'nullable|date',
            'end_time' => 'required|date',
        ]);

        $service = $this->resolveActiveService(
            isset($validated['service_id']) ? (int) $validated['service_id'] : null,
            $validated['service_type'] ?? null
        );

        if (! $service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unknown or inactive service. Send service_id or a valid service slug as service_type.',
            ], 422);
        }

        $startAt = isset($validated['start_time'])
            ? Carbon::parse($validated['start_time'])
            : now();
        $endAt = Carbon::parse($validated['end_time']);

        $hoursFloat = $this->validateEndTimeWindow($startAt, $endAt);
        if ($hoursFloat instanceof JsonResponse) {
            return $hoursFloat;
        }

        $count = (int) $validated['security_personnel_count'];
        $hourlySubtotal = (float) $service->hourly_rate * $count * $hoursFloat;
        $dailyCapTotal = (float) $service->daily_rate > 0
            ? (float) $service->daily_rate * $count
            : null;
        $amount = $this->calculatePriceFromService($service, $count, $hoursFloat);

        return response()->json([
            'status' => 'success',
            'quote' => [
                'currency' => 'GEL',
                'amount' => $amount,
                'service_id' => $service->id,
                'service_slug' => $service->slug,
                'team_service_type' => $service->team_service_type,
                'security_personnel_count' => $count,
                'start_time' => $startAt->toIso8601String(),
                'end_time' => $endAt->toIso8601String(),
                'duration_hours' => round($hoursFloat, 4),
                'hourly_subtotal' => round($hourlySubtotal, 2),
                'daily_cap_total' => $dailyCapTotal !== null ? round($dailyCapTotal, 2) : null,
                'pricing_applied' => $dailyCapTotal !== null && $amount < round($hourlySubtotal, 2) ? 'daily_cap' : 'hourly',
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/client/bookings',
        summary: 'Create a new booking',
        description: 'Creates a new booking request after verification checks and quote calculation. Scheduling is inferred from start_time: omit it for immediate (starts now), send it for scheduled (≥30 minutes ahead).',
        tags: ['Client Booking'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['security_personnel_count', 'persons_to_protect_count', 'address', 'end_time'],
                properties: [
                    new OA\Property(property: 'service_id', type: 'integer', description: 'Catalog service id (preferred).', nullable: true, example: 1),
                    new OA\Property(property: 'service_type', type: 'string', description: 'Catalog service slug if service_id omitted (e.g. armed, mobile_patrol).', nullable: true, example: 'armed'),
                    new OA\Property(property: 'security_personnel_count', type: 'integer', description: 'Requested number of guards.', minimum: 1, maximum: 10, default: 1, example: 2),
                    new OA\Property(property: 'persons_to_protect_count', type: 'integer', description: 'How many people are being protected.', minimum: 1, default: 1, example: 1),
                    new OA\Property(property: 'vehicle_id', type: 'integer', description: 'Optional preferred vehicle ID. Default is null (auto assignment).', nullable: true, default: null, example: 1),
                    new OA\Property(property: 'address', type: 'string', description: 'Service address/location.', example: 'Rustaveli Ave 10, Tbilisi'),
                    new OA\Property(property: 'latitude', type: 'number', format: 'float', description: 'Address latitude. Default is null.', nullable: true, default: null, example: 41.7151),
                    new OA\Property(property: 'longitude', type: 'number', format: 'float', description: 'Address longitude. Default is null.', nullable: true, default: null, example: 44.8271),
                    new OA\Property(property: 'start_time', type: 'string', format: 'date-time', description: 'Omit or null = immediate (start is server now). Set = scheduled (must be ≥30 minutes in the future, within 90 days).', nullable: true, example: '2026-03-20T12:00:00Z'),
                    new OA\Property(property: 'end_time', type: 'string', format: 'date-time', description: 'Service end time (must be 1–24 hours after booking start).', example: '2026-03-20T16:00:00Z'),
                    new OA\Property(property: 'guard_outfit', type: 'string', description: 'Preferred guard outfit. Default is null.', enum: ['tactical', 'formal', 'casual'], nullable: true, default: null, example: 'formal'),
                    new OA\Property(
                        property: 'persons',
                        type: 'array',
                        description: 'Optional persons details. If omitted/empty, server auto-adds authenticated client as first protected person.',
                        default: [],
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string', description: "Protected person's full name", example: 'Gega Gagua'),
                                new OA\Property(property: 'phone', type: 'string', description: "Protected person's phone", nullable: true, example: '+995555123456'),
                                new OA\Property(property: 'notes', type: 'string', description: 'Optional notes', nullable: true, example: 'VIP client'),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                example: [
                    'service_id' => 1,
                    'security_personnel_count' => 2,
                    'persons_to_protect_count' => 1,
                    'vehicle_id' => 1,
                    'address' => 'Rustaveli Ave 10, Tbilisi',
                    'latitude' => 41.7151,
                    'longitude' => 44.8271,
                    'start_time' => '2026-03-20T12:00:00Z',
                    'end_time' => '2026-03-20T16:00:00Z',
                    'guard_outfit' => 'formal',
                    'persons' => [
                        ['name' => 'Gega Gagua', 'phone' => '+995555123456', 'notes' => 'VIP client'],
                    ],
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Booking created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $client = $request->user();

        // Booking requires verified profile.
        if (! $client->isVerified()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verification required to create booking',
                'verification_required' => true,
            ], 403);
        }

        $validated = $request->validate([
            'service_id' => ['required_without:service_type', 'nullable', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'service_type' => 'required_without:service_id|nullable|string|max:64',
            'security_personnel_count' => 'required|integer|min:1|max:10',
            'persons_to_protect_count' => 'required|integer|min:1',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'start_time' => 'nullable|date',
            'end_time' => 'required|date',
            'guard_outfit' => 'nullable|in:tactical,formal,casual',
            'persons' => 'nullable|array',
            'persons.*.name' => 'nullable|string|max:255',
            'persons.*.phone' => 'nullable|string',
            'persons.*.notes' => 'nullable|string',
        ]);

        $service = $this->resolveActiveService(
            isset($validated['service_id']) ? (int) $validated['service_id'] : null,
            $validated['service_type'] ?? null
        );

        if (! $service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unknown or inactive service. Send service_id or a valid service slug as service_type.',
            ], 422);
        }

        if ($service->requires_vehicle && empty($validated['vehicle_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'This service requires a selected vehicle (vehicle_id).',
            ], 422);
        }

        $persons = collect($validated['persons'] ?? [])
            ->filter(function ($person) {
                if (! is_array($person)) {
                    return false;
                }

                return ! empty($person['name']) || ! empty($person['phone']) || ! empty($person['notes']);
            })
            ->map(function (array $person): array {
                return [
                    'name' => isset($person['name']) ? trim((string) $person['name']) : null,
                    'phone' => $person['phone'] ?? null,
                    'notes' => $person['notes'] ?? null,
                ];
            })
            ->filter(fn (array $person) => ! empty($person['name']))
            ->values()
            ->all();

        if (count($persons) === 0) {
            $selfName = trim(($client->first_name ?? '').' '.($client->last_name ?? ''));

            $persons = [[
                'name' => $selfName !== '' ? $selfName : ('Client #'.$client->id),
                'phone' => $client->phone ?? null,
                'notes' => 'Auto-added from authenticated client profile.',
            ]];
        }

        $isScheduled = ! empty($validated['start_time']);

        $startAt = $isScheduled
            ? Carbon::parse($validated['start_time'])
            : now();

        $endAt = Carbon::parse($validated['end_time']);

        if ($isScheduled) {
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

        $hoursFloat = $this->validateEndTimeWindow($startAt, $endAt);
        if ($hoursFloat instanceof JsonResponse) {
            return $hoursFloat;
        }

        $totalAmount = $this->calculatePriceFromService(
            $service,
            (int) $validated['security_personnel_count'],
            $hoursFloat
        );

        $durationHoursStored = (int) max(1, min(24, (int) round($hoursFloat)));

        $booking = DB::transaction(function () use ($client, $validated, $service, $totalAmount, $startAt, $endAt, $durationHoursStored, $persons, $isScheduled) {
            $booking = Booking::create([
                'client_id' => $client->id,
                'service_id' => $service->id,
                'service_type' => $service->team_service_type,
                'security_personnel_count' => $validated['security_personnel_count'],
                'persons_to_protect_count' => $validated['persons_to_protect_count'],
                'vehicle_id' => $validated['vehicle_id'] ?? null,
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'start_time' => $startAt,
                'end_time' => $endAt,
                'duration_hours' => $durationHoursStored,
                'booking_type' => $isScheduled ? 'scheduled' : 'immediate',
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
        path: '/api/client/bookings',
        summary: 'Get client bookings',
        description: 'Returns paginated bookings for authenticated client with optional status filter.',
        tags: ['Client Booking'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'language', description: 'Response language: ka or en', in: 'header', required: false, schema: new OA\Schema(type: 'string', enum: ['ka', 'en'])),
            new OA\Parameter(name: 'status', description: 'Optional booking status filter', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Bookings list'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $client = $request->user();
        $status = $request->query('status');

        $query = $client->bookings()->with(['securityTeam', 'vehicle', 'service', 'rating']);

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
        path: '/api/client/bookings/active',
        summary: 'Get active bookings',
        description: 'Returns active client bookings in pending/confirmed/ongoing/arrived states.',
        tags: ['Client Booking'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'language', description: 'Response language: ka or en', in: 'header', required: false, schema: new OA\Schema(type: 'string', enum: ['ka', 'en'])),
        ],
        responses: [new OA\Response(response: 200, description: 'Active bookings list')]
    )]
    public function active(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $client = $request->user();
        $query = $client->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'ongoing', 'arrived'])
            ->with(['securityTeam', 'vehicle', 'service'])
            ->latest();

        $bookings = $query->get();

        return response()->json([
            'status' => 'success',
            'bookings' => $this->enrichCollection($bookings, $locale),
        ]);
    }

    #[OA\Get(
        path: '/api/client/bookings/history',
        summary: 'Get booking history',
        description: 'Returns completed and cancelled bookings with payment and rating details.',
        tags: ['Client Booking'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'language', description: 'Response language: ka or en', in: 'header', required: false, schema: new OA\Schema(type: 'string', enum: ['ka', 'en'])),
        ],
        responses: [new OA\Response(response: 200, description: 'Booking history list')]
    )]
    public function history(Request $request): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $client = $request->user();
        $query = $client->bookings()
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['securityTeam', 'vehicle', 'service', 'payments', 'rating'])
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
        path: '/api/client/bookings/{id}',
        summary: 'Get booking details',
        description: 'Returns full booking details with team, chat, payment, and rating data.',
        tags: ['Client Booking'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'language', description: 'Response language: ka or en', in: 'header', required: false, schema: new OA\Schema(type: 'string', enum: ['ka', 'en'])),
            new OA\Parameter(name: 'id', description: 'Booking ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Booking details'),
            new OA\Response(response: 404, description: 'Booking not found'),
        ]
    )]
    public function show(Request $request, $id): JsonResponse
    {
        $locale = $this->localeFromRequest($request);
        $client = $request->user();
        $query = $client->bookings()
            ->with(['securityTeam.personnel', 'vehicle', 'service', 'bookingPersons', 'messages', 'payments', 'rating'])
            ->whereKey($id);

        $booking = $query->firstOrFail();

        return response()->json([
            'status' => 'success',
            'booking' => $this->enrichBooking($booking, $locale),
        ]);
    }

    #[OA\Post(
        path: '/api/client/bookings/{id}/cancel',
        summary: 'Cancel a booking',
        description: 'Cancels booking according to state machine rules and applies refund policy.',
        tags: ['Client Booking'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', description: 'Optional cancellation reason', nullable: true, example: 'Plan changed'),
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: 'id', description: 'Booking ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Booking cancelled'),
            new OA\Response(response: 403, description: 'Cannot cancel booking'),
        ]
    )]
    public function cancel(Request $request, $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->find($id);

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Booking not found for current client.',
            ], 404);
        }

        if (! $booking->canBeCancelled()) {
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
