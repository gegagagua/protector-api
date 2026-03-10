<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\SecurityTeam;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DemoClientBookingSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::updateOrCreate(
            ['phone' => '+995555889900'],
            [
                'first_name' => 'Demo',
                'last_name' => 'Client',
                'email' => 'client@proector.local',
                'verification_status' => 'verified',
                'phone_verified_at' => now(),
                'is_active' => true,
                'password' => 'client1234',
            ]
        );

        $team = SecurityTeam::query()->first();
        $vehicle = Vehicle::query()->first();

        if (!$team || !$vehicle) {
            return;
        }

        $activeBooking = Booking::updateOrCreate(
            ['client_id' => $client->id, 'status' => 'ongoing'],
            [
                'security_team_id' => $team->id,
                'vehicle_id' => $vehicle->id,
                'service_type' => 'armed',
                'security_personnel_count' => 2,
                'persons_to_protect_count' => 1,
                'address' => 'Rustaveli Ave 10, Tbilisi',
                'latitude' => 41.7151,
                'longitude' => 44.8271,
                'start_time' => now()->subMinutes(40),
                'end_time' => now()->addHours(3),
                'duration_hours' => 4,
                'booking_type' => 'immediate',
                'total_amount' => 800,
                'paid_amount' => 800,
                'payment_status' => 'paid',
                'status' => 'ongoing',
                'started_at' => now()->subMinutes(30),
                'admin_notes' => 'outfit:tactical',
            ]
        );

        $historyBooking = Booking::updateOrCreate(
            ['client_id' => $client->id, 'status' => 'completed'],
            [
                'security_team_id' => $team->id,
                'vehicle_id' => $vehicle->id,
                'service_type' => 'unarmed',
                'security_personnel_count' => 1,
                'persons_to_protect_count' => 1,
                'address' => 'Vake Park, Tbilisi',
                'latitude' => 41.7099,
                'longitude' => 44.7510,
                'start_time' => now()->subDays(4),
                'end_time' => now()->subDays(4)->addHours(2),
                'duration_hours' => 2,
                'booking_type' => 'scheduled',
                'total_amount' => 220,
                'paid_amount' => 220,
                'payment_status' => 'paid',
                'status' => 'completed',
                'completed_at' => now()->subDays(4)->addHours(2),
                'admin_notes' => 'outfit:formal',
            ]
        );

        Payment::updateOrCreate(
            ['booking_id' => $historyBooking->id, 'client_id' => $client->id],
            [
                'payment_method_id' => null,
                'amount' => 220,
                'payment_type' => 'card',
                'status' => 'completed',
                'transaction_id' => 'demo-history-' . $historyBooking->id,
                'payment_gateway' => 'mock',
                'payment_data' => ['source' => 'seed'],
                'paid_at' => $historyBooking->completed_at ?? now()->subDays(4),
            ]
        );

        Payment::updateOrCreate(
            ['booking_id' => $activeBooking->id, 'client_id' => $client->id],
            [
                'payment_method_id' => null,
                'amount' => 800,
                'payment_type' => 'card',
                'status' => 'completed',
                'transaction_id' => 'demo-active-' . $activeBooking->id,
                'payment_gateway' => 'mock',
                'payment_data' => ['source' => 'seed'],
                'paid_at' => now()->subMinutes(35),
            ]
        );
    }
}
