<?php

namespace Database\Seeders;

use App\Enums\VehicleRole;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'make' => 'Chrysler',
                'model' => '300',
                'image_url' => 'https://picsum.photos/seed/chrysler300/1200/700',
                'license_plate' => 'SEC-2142',
                'color' => 'Black',
                'year' => 2021,
                'vehicle_type' => 'sedan',
                'role' => VehicleRole::LuxurySedan,
                'status' => 'available',
                'description' => 'Executive sedan for urban escort.',
                'is_active' => true,
            ],
            [
                'make' => 'Cadillac',
                'model' => 'Escalade',
                'image_url' => 'https://picsum.photos/seed/escalade/1200/700',
                'license_plate' => 'SEC-2914',
                'color' => 'Black',
                'year' => 2022,
                'vehicle_type' => 'suv',
                'role' => VehicleRole::ArmoredSuv,
                'status' => 'available',
                'description' => 'Armored-ready SUV for VIP transport.',
                'is_active' => true,
            ],
            [
                'make' => 'Mercedes-Benz',
                'model' => 'V-Class',
                'image_url' => 'https://picsum.photos/seed/vclass/1200/700',
                'license_plate' => 'SEC-3306',
                'color' => 'Gray',
                'year' => 2020,
                'vehicle_type' => 'van',
                'role' => VehicleRole::LuxuryVan,
                'status' => 'in_use',
                'description' => 'Team transport van for multi-person missions.',
                'is_active' => true,
            ],
            [
                'make' => 'BMW',
                'model' => 'R 1250 RT',
                'image_url' => 'https://picsum.photos/seed/bmwmoto/1200/700',
                'license_plate' => 'SEC-0007',
                'color' => 'White',
                'year' => 2023,
                'vehicle_type' => 'motorcycle',
                'role' => VehicleRole::Utility,
                'status' => 'available',
                'description' => 'Rapid response motorcycle unit.',
                'is_active' => true,
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::updateOrCreate(
                ['license_plate' => $vehicle['license_plate']],
                $vehicle
            );
        }
    }
}
