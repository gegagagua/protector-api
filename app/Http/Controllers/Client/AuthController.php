<?php

namespace App\Http\Controllers\Client;

use App\Events\PasswordChanged;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\OtpCode;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService)
    {
    }

    private function resolvePhone(array $validated): string
    {
        if (!empty($validated['phone'])) {
            return (string) $validated['phone'];
        }

        $countryCode = ltrim((string) ($validated['country_code'] ?? ''), '+');
        $nationalNumber = preg_replace('/\D+/', '', (string) ($validated['phone_national_number'] ?? ''));

        return '+' . $countryCode . $nationalNumber;
    }

    private function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'nullable|string|regex:/^\+?[1-9]\d{1,14}$/',
            'country_code' => 'nullable|string|regex:/^\+?[1-9]\d{0,3}$/',
            'phone_national_number' => 'nullable|string|min:4|max:20',
            'type' => 'nullable|in:registration,login',
        ]);

        if (empty($validated['phone']) && (empty($validated['country_code']) || empty($validated['phone_national_number']))) {
            throw ValidationException::withMessages([
                'phone' => ['Provide either phone or country_code with phone_national_number.'],
            ]);
        }

        $phone = $this->resolvePhone($validated);
        $type = $validated['type'] ?? 'login';
        $cooldownSeconds = (int) config('services.otp.cooldown_seconds', 30);
        $cooldownKey = "otp:cooldown:{$phone}";
        $nowTs = now()->timestamp;
        $cooldownUntil = (int) Cache::get($cooldownKey, 0);

        if ($cooldownUntil > $nowTs) {
            $secondsLeft = $cooldownUntil - $nowTs;

            return response()->json([
                'status' => 'error',
                'message' => "Please wait {$secondsLeft} seconds before requesting a new OTP.",
            ], 429);
        }

        $otpCode = $this->otpService->createAndSend($phone, $type);
        Cache::put($cooldownKey, $nowTs + $cooldownSeconds, now()->addSeconds($cooldownSeconds));

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent to your phone',
            // Remove this in production
            'debug_otp' => config('app.debug') ? $otpCode : null,
        ]);
    }

    #[OA\Post(
        path: "/api/client/signup/send-otp",
        summary: "Send signup OTP",
        description: "Sends OTP code for new client registration using phone number.",
        tags: ["Client Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone"],
                properties: [
                    new OA\Property(property: "phone", type: "string", description: "Client phone in international format", example: "+995555123456"),
                    new OA\Property(property: "country_code", type: "string", description: "Optional country dialing code (alternative input mode)", example: "+995"),
                    new OA\Property(property: "phone_national_number", type: "string", description: "Optional local phone number without country code", example: "555123456")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "OTP sent"),
            new OA\Response(response: 429, description: "Cooldown active")
        ]
    )]
    public function sendSignupOtp(Request $request): JsonResponse
    {
        $request->merge(['type' => 'registration']);
        $validated = $request->validate([
            'phone' => 'nullable|string',
            'country_code' => 'nullable|string',
            'phone_national_number' => 'nullable|string',
        ]);
        $phone = $this->resolvePhone($validated);

        if (Client::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['User with this phone is already registered.'],
            ]);
        }

        return $this->sendOtp($request);
    }

    #[OA\Post(
        path: "/api/client/signin/send-otp",
        summary: "Send signin OTP",
        description: "Sends OTP code for existing client login.",
        tags: ["Client Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone"],
                properties: [
                    new OA\Property(property: "phone", type: "string", description: "Existing client phone in international format", example: "+995555123456"),
                    new OA\Property(property: "country_code", type: "string", description: "Optional country dialing code (alternative input mode)", example: "+995"),
                    new OA\Property(property: "phone_national_number", type: "string", description: "Optional local phone number without country code", example: "555123456")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "OTP sent"),
            new OA\Response(response: 429, description: "Cooldown active")
        ]
    )]
    public function sendSigninOtp(Request $request): JsonResponse
    {
        $request->merge(['type' => 'login']);
        $validated = $request->validate([
            'phone' => 'nullable|string',
            'country_code' => 'nullable|string',
            'phone_national_number' => 'nullable|string',
        ]);
        $phone = $this->resolvePhone($validated);

        if (!Client::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['User with this phone is not registered.'],
            ]);
        }

        return $this->sendOtp($request);
    }

    private function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'nullable|string',
            'country_code' => 'nullable|string|regex:/^\+?[1-9]\d{0,3}$/',
            'phone_national_number' => 'nullable|string|min:4|max:20',
            'code' => 'required|string|size:4',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);
        if (empty($validated['phone']) && (empty($validated['country_code']) || empty($validated['phone_national_number']))) {
            throw ValidationException::withMessages([
                'phone' => ['Provide either phone or country_code with phone_national_number.'],
            ]);
        }
        $phone = $this->resolvePhone($validated);

        $otp = OtpCode::valid()
            ->forPhone($phone)
            ->latest()
            ->first();

        if (!$otp || !$otp->code_hash) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired OTP code.'],
            ]);
        }

        $maxAttempts = (int) config('services.otp.max_attempts', 5);

        if ($otp->attempt_count >= $maxAttempts) {
            throw ValidationException::withMessages([
                'code' => ['OTP attempt limit exceeded. Please request a new OTP.'],
            ]);
        }

        if (!Hash::check($validated['code'], $otp->code_hash)) {
            $otp->increment('attempt_count');

            throw ValidationException::withMessages([
                'code' => ['Invalid or expired OTP code.'],
            ]);
        }

        $otp->markAsUsed();

        // Find or create client
        $client = Client::where('phone', $phone)->first();
        $isNewUser = false;

        if (!$client) {
            // Registration - first_name and last_name are required
            if (empty($validated['first_name']) || empty($validated['last_name'])) {
                throw ValidationException::withMessages([
                    'first_name' => ['First name is required for registration.'],
                    'last_name' => ['Last name is required for registration.'],
                ]);
            }

            $client = Client::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $phone,
                'phone_verified_at' => now(),
            ]);
            $isNewUser = true;
        } else {
            // Login - update phone verification if needed
            if (!$client->phone_verified_at) {
                $client->update(['phone_verified_at' => now()]);
            }
        }

        // Generate token
        $token = $client->createToken('client-auth', ['client'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'client' => $client->makeHidden(['deleted_at']),
            'is_new_user' => $isNewUser,
        ]);
    }

    #[OA\Post(
        path: "/api/client/signup",
        summary: "Signup client",
        description: "Completes client registration by verifying OTP and requiring first and last name.",
        tags: ["Client Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone", "code", "first_name", "last_name"],
                properties: [
                    new OA\Property(property: "phone", type: "string", description: "Phone number that received OTP", example: "+995555123456"),
                    new OA\Property(property: "code", type: "string", description: "OTP verification code", example: "1111"),
                    new OA\Property(property: "first_name", type: "string", description: "Client first name", example: "Giorgi"),
                    new OA\Property(property: "last_name", type: "string", description: "Client last name", example: "Gelashvili")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Signup successful"),
            new OA\Response(response: 422, description: "Validation/OTP failed")
        ]
    )]
    public function signup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'nullable|string',
            'country_code' => 'nullable|string',
            'phone_national_number' => 'nullable|string',
        ]);
        $phone = $this->resolvePhone($validated);
        if (Client::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['User with this phone is already registered.'],
            ]);
        }

        return $this->verifyOtp($request);
    }

    #[OA\Post(
        path: "/api/client/signin",
        summary: "Signin client",
        description: "Completes client login by verifying OTP for an existing account.",
        tags: ["Client Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone", "code"],
                properties: [
                    new OA\Property(property: "phone", type: "string", description: "Existing client phone number", example: "+995555123456"),
                    new OA\Property(property: "code", type: "string", description: "OTP verification code", example: "1111")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Signin successful"),
            new OA\Response(response: 422, description: "Validation/OTP failed")
        ]
    )]
    public function signin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'nullable|string',
            'country_code' => 'nullable|string',
            'phone_national_number' => 'nullable|string',
        ]);
        $phone = $this->resolvePhone($validated);
        if (!Client::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['User with this phone is not registered.'],
            ]);
        }

        return $this->verifyOtp($request);
    }

    #[OA\Post(
        path: "/api/client/logout",
        summary: "Logout client",
        description: "Revokes current access token for authenticated client session.",
        tags: ["Client Auth"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logged out successfully"
            )
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }

    #[OA\Post(
        path: "/api/client/change-password",
        summary: "Change client password",
        description: "Sets or changes authenticated client password. current_password is required only when password already exists.",
        tags: ["Client Auth"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["new_password", "new_password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", nullable: true, description: "Required when client already has a password", example: "old-password"),
                    new OA\Property(property: "new_password", type: "string", description: "New password (minimum 8 characters)", example: "new-strong-password"),
                    new OA\Property(property: "new_password_confirmation", type: "string", description: "Repeat new password to confirm", example: "new-strong-password"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Password changed")]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user();
        $hasPassword = !empty($client->password);

        $rules = [
            'new_password' => 'required|string|min:8|confirmed',
            'current_password' => $hasPassword ? 'required|string' : 'nullable|string',
        ];

        $validated = $request->validate($rules);

        if ($hasPassword && !Hash::check((string) $validated['current_password'], (string) $client->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $client->update([
            'password' => $validated['new_password'],
        ]);

        event(new PasswordChanged('client', (int) $client->id, now()->toIso8601String()));

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
        ]);
    }
}
