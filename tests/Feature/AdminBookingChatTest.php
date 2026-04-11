<?php

namespace Tests\Feature;

use App\Enums\SecurityPersonnelRole;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Message;
use App\Models\SecurityPersonnel;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBookingChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_booking_chat_by_booking_id(): void
    {
        $admin = Admin::create([
            'first_name' => 'A',
            'last_name' => 'D',
            'username' => 'adminchat1',
            'email' => 'adminchat@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $client = Client::create([
            'first_name' => 'C',
            'last_name' => 'L',
            'phone' => '+995500000077',
            'verification_status' => 'verified',
            'phone_verified_at' => now(),
        ]);

        $service = Service::query()->where('slug', 'armed')->firstOrFail();

        $guard = SecurityPersonnel::create([
            'first_name' => 'G',
            'last_name' => 'U',
            'username' => 'guardchat1',
            'phone' => '+995500000088',
            'password' => 'password123',
            'role' => SecurityPersonnelRole::UnarmedGuard,
            'security_team_id' => null,
            'status' => 'available',
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'security_team_id' => null,
            'vehicle_id' => null,
            'service_id' => $service->id,
            'service_type' => 'armed',
            'security_personnel_count' => 1,
            'persons_to_protect_count' => 1,
            'address' => 'Tbilisi',
            'start_time' => now(),
            'end_time' => now()->addHours(2),
            'duration_hours' => 2,
            'booking_type' => 'immediate',
            'status' => 'pending',
            'total_amount' => 100,
            'paid_amount' => 0,
            'payment_status' => 'pending',
        ]);

        Message::create([
            'booking_id' => $booking->id,
            'sender_type' => Client::class,
            'sender_id' => $client->id,
            'receiver_type' => SecurityPersonnel::class,
            'receiver_id' => $guard->id,
            'message' => 'Hello from client',
            'message_type' => 'text',
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/bookings/'.$booking->id.'/messages');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('booking_id', $booking->id)
            ->assertJsonPath('messages.data.0.message', 'Hello from client');
    }
}
