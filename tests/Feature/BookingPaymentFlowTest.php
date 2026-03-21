<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_pay_and_cancel_booking(): void
    {
        $client = Client::create([
            'first_name' => 'Nika',
            'last_name' => 'Test',
            'phone' => '+995500001111',
            'verification_status' => 'verified',
            'phone_verified_at' => now(),
        ]);

        $vehicle = Vehicle::create([
            'make' => 'Chrysler',
            'model' => '300',
            'license_plate' => 'TEST-001',
            'vehicle_type' => 'sedan',
            'status' => 'available',
            'is_active' => true,
        ]);

        $token = $client->createToken('client-auth', ['client'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        $start = now()->addHours(2);
        $end = $start->copy()->addHours(4);

        $bookingResponse = $this->withHeaders($headers)->postJson('/api/client/bookings', [
            'service_type' => 'armed',
            'security_personnel_count' => 2,
            'persons_to_protect_count' => 1,
            'vehicle_id' => $vehicle->id,
            'address' => 'Tbilisi, Rustaveli Ave',
            'latitude' => 41.7151,
            'longitude' => 44.8271,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'booking_type' => 'scheduled',
        ]);

        $bookingResponse->assertCreated()->assertJsonPath('status', 'success');
        $bookingId = $bookingResponse->json('booking.id');

        $methodResponse = $this->withHeaders($headers)->postJson('/api/client/payment-methods', [
            'type' => 'card',
            'provider' => 'mock',
            'last_four' => '4242',
            'card_holder_name' => 'Nika Test',
            'token' => 'pm_mock_token',
            'is_default' => true,
        ]);

        $methodResponse->assertCreated();
        $methodId = $methodResponse->json('payment_method.id');

        $payResponse = $this->withHeaders($headers)->postJson("/api/client/bookings/{$bookingId}/pay", [
            'payment_method_id' => $methodId,
        ]);

        $payResponse->assertOk()->assertJsonPath('status', 'success');

        $cancelResponse = $this->withHeaders($headers)->postJson("/api/client/bookings/{$bookingId}/cancel", [
            'reason' => 'Change of plans',
        ]);

        $cancelResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('booking.status', 'cancelled');
    }
}
