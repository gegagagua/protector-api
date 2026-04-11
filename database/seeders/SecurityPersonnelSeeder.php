<?php

namespace Database\Seeders;

use App\Enums\SecurityPersonnelRole;
use App\Models\SecurityPersonnel;
use App\Models\SecurityTeam;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SecurityPersonnelSeeder extends Seeder
{
    public function run(): void
    {
        $team = SecurityTeam::firstOrCreate(
            ['name' => 'Alpha Tactical Team'],
            [
                'team_size' => 4,
                'service_type' => 'armed',
                'status' => 'available',
                'description' => 'Rapid response tactical armed team.',
                'is_active' => true,
            ]
        );

        SecurityPersonnel::updateOrCreate(
            ['username' => 'guard1'],
            [
                'first_name' => 'Giorgi',
                'last_name' => 'Guard',
                'phone' => '+995555770001',
                'email' => 'guard1@proector.local',
                'password' => Hash::make('guard1234'),
                'security_team_id' => $team->id,
                'role' => SecurityPersonnelRole::ArmedGuard,
                'status' => 'available',
                'is_active' => true,
            ]
        );
    }
}
