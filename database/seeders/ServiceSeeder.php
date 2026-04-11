<?php

namespace Database\Seeders;

use App\Enums\ServiceIcon;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'slug' => 'armed',
                'name_en' => 'Armed Security',
                'name_ka' => 'იარაღიანი დაცვა',
                'description_en' => 'Armed security protection service.',
                'description_ka' => 'იარაღიანი დაცვის სერვისი',
                'icon' => ServiceIcon::ShieldCheck,
                'hourly_rate' => 100,
                'daily_rate' => 1800,
                'requires_vehicle' => false,
                'team_service_type' => 'armed',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'slug' => 'unarmed',
                'name_en' => 'Unarmed Security',
                'name_ka' => 'უიარაღო დაცვა',
                'description_en' => 'Unarmed security protection service.',
                'description_ka' => 'უიარაღო დაცვის სერვისი',
                'icon' => ServiceIcon::Shield,
                'hourly_rate' => 70,
                'daily_rate' => 1200,
                'requires_vehicle' => false,
                'team_service_type' => 'unarmed',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'slug' => 'mobile_patrol',
                'name_en' => 'Mobile patrol (vehicle)',
                'name_ka' => 'მობილური პატრული (ავტომობილი)',
                'description_en' => 'Vehicle-based guarding and patrol.',
                'description_ka' => 'ავტომობილზე დაფუძნებული დაცვა და პატრული',
                'icon' => ServiceIcon::Car,
                'hourly_rate' => 90,
                'daily_rate' => 1600,
                'requires_vehicle' => true,
                'team_service_type' => 'armed',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'slug' => 'heavy_armed',
                'name_en' => 'Heavy armed security',
                'name_ka' => 'მძიმე იარაღიანი დაცვა',
                'description_en' => 'Heavy armed protection detail.',
                'description_ka' => 'მძიმე იარაღიანი დაცვის დეტალი',
                'icon' => ServiceIcon::ShieldCheck,
                'hourly_rate' => 130,
                'daily_rate' => 2400,
                'requires_vehicle' => false,
                'team_service_type' => 'armed',
                'is_active' => true,
                'sort_order' => 40,
            ],
        ];

        foreach ($rows as $row) {
            Service::updateOrCreate(
                ['slug' => $row['slug']],
                $row
            );
        }

        foreach (['armed' => 'armed', 'unarmed' => 'unarmed'] as $stype => $slug) {
            $sid = Service::where('slug', $slug)->value('id');
            if ($sid) {
                Booking::query()
                    ->where('service_type', $stype)
                    ->whereNull('service_id')
                    ->update(['service_id' => $sid]);
            }
        }
    }
}
