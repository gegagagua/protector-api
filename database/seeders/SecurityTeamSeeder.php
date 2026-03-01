<?php

namespace Database\Seeders;

use App\Models\SecurityTeam;
use Illuminate\Database\Seeder;

class SecurityTeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = [
            [
                'name' => 'Alpha Tactical Team',
                'team_size' => 4,
                'service_type' => 'armed',
                'status' => 'available',
                'description' => 'Rapid response tactical armed team.',
                'is_active' => true,
            ],
            [
                'name' => 'Bravo Executive Team',
                'team_size' => 3,
                'service_type' => 'unarmed',
                'status' => 'available',
                'description' => 'Executive close protection team.',
                'is_active' => true,
            ],
            [
                'name' => 'Charlie Escort Team',
                'team_size' => 5,
                'service_type' => 'armed',
                'status' => 'busy',
                'description' => 'Long distance escort and convoy operations.',
                'is_active' => true,
            ],
        ];

        foreach ($teams as $team) {
            SecurityTeam::updateOrCreate(
                ['name' => $team['name']],
                $team
            );
        }
    }
}
