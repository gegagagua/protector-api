<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['username' => 'admin'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'email' => 'admin@proector.local',
                'phone' => '+995555000000',
                'password' => Hash::make('admin1234'),
                'is_active' => true,
                'notification_preferences' => [
                    'push' => true,
                    'sms' => false,
                    'email' => true,
                ],
            ]
        );
    }
}
