<?php

namespace Tests\Feature;

use App\Enums\SecurityPersonnelRole;
use App\Models\Booking;
use App\Models\Client;
use App\Models\SecurityPersonnel;
use App\Models\SecurityTeam;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityLocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_can_post_gps_for_active_booking_and_client_reads_map_payload(): void
    {
        $team = SecurityTeam::create([
            'name' => 'Test Team',
            'team_size' => 4,
            'service_type' => 'armed',
            'status' => 'available',
            'description' => 'Test',
            'is_active' => true,
        ]);

        $guard = SecurityPersonnel::create([
            'first_name' => 'Pat',
            'last_name' => 'Rol',
            'username' => 'patrol1',
            'phone' => '+995500000033',
            'password' => 'password123',
            'role' => SecurityPersonnelRole::Driver,
            'security_team_id' => $team->id,
            'status' => 'busy',
            'is_active' => true,
        ]);

        $client = Client::create([
            'first_name' => 'Client',
            'last_name' => 'One',
            'phone' => '+995500000044',
            'verification_status' => 'verified',
            'phone_verified_at' => now(),
        ]);

        $service = Service::query()->where('slug', 'armed')->firstOrFail();

        $booking = Booking::create([
            'client_id' => $client->id,
            'security_team_id' => $team->id,
            'vehicle_id' => null,
            'service_id' => $service->id,
            'service_type' => 'armed',
            'security_personnel_count' => 1,
            'persons_to_protect_count' => 1,
            'address' => 'Tbilisi',
            'latitude' => 41.7,
            'longitude' => 44.8,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
            'duration_hours' => 3,
            'booking_type' => 'immediate',
            'status' => 'ongoing',
            'total_amount' => 100,
            'paid_amount' => 0,
            'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($guard, ['security']);
        $post = $this->postJson('/api/security/location/update', [
            'booking_id' => $booking->id,
            'latitude' => 41.7151,
            'longitude' => 44.8271,
            'accuracy' => 12.5,
        ]);

        $post->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('location.latitude', 41.7151)
            ->assertJsonPath('location.longitude', 44.8271)
            ->assertJsonPath('location.lat', 41.7151)
            ->assertJsonPath('location.lng', 44.8271);

        $mapsUrl = $post->json('location.google_maps_url');
        $this->assertIsString($mapsUrl);
        $this->assertStringContainsString('41.7151', $mapsUrl);
        $this->assertStringContainsString('44.8271', $mapsUrl);

        Sanctum::actingAs($client, ['client']);
        $get = $this->getJson('/api/client/bookings/'.$booking->id.'/tracking?trail_limit=5');

        $get->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('location.latitude', 41.7151)
            ->assertJsonPath('current_location.latitude', 41.7151);

        $this->assertGreaterThanOrEqual(1, count($get->json('recent_locations')));
    }

    public function test_security_cannot_update_location_for_completed_booking(): void
    {
        $team = SecurityTeam::create([
            'name' => 'Team B',
            'team_size' => 2,
            'service_type' => 'armed',
            'status' => 'available',
            'description' => 'Test',
            'is_active' => true,
        ]);

        $guard = SecurityPersonnel::create([
            'first_name' => 'G',
            'last_name' => 'Two',
            'username' => 'guardloc2',
            'phone' => '+995500000055',
            'password' => 'password123',
            'role' => SecurityPersonnelRole::ArmedGuard,
            'security_team_id' => $team->id,
            'status' => 'available',
            'is_active' => true,
        ]);

        $client = Client::create([
            'first_name' => 'C',
            'last_name' => 'Two',
            'phone' => '+995500000066',
            'verification_status' => 'verified',
            'phone_verified_at' => now(),
        ]);

        $service = Service::query()->where('slug', 'armed')->firstOrFail();

        $booking = Booking::create([
            'client_id' => $client->id,
            'security_team_id' => $team->id,
            'vehicle_id' => null,
            'service_id' => $service->id,
            'service_type' => 'armed',
            'security_personnel_count' => 1,
            'persons_to_protect_count' => 1,
            'address' => 'Tbilisi',
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'duration_hours' => 2,
            'booking_type' => 'scheduled',
            'status' => 'completed',
            'total_amount' => 50,
            'paid_amount' => 50,
            'payment_status' => 'paid',
        ]);

        Sanctum::actingAs($guard, ['security']);
        $this->postJson('/api/security/location/update', [
            'booking_id' => $booking->id,
            'latitude' => 41.0,
            'longitude' => 44.0,
        ])
            ->assertStatus(422);
    }
}
