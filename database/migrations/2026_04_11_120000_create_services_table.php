<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_en', 255);
            $table->string('name_ka', 255);
            $table->text('description_en')->nullable();
            $table->text('description_ka')->nullable();
            $table->string('icon', 32);
            $table->decimal('hourly_rate', 10, 2);
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->boolean('requires_vehicle')->default(false);
            $table->string('team_service_type', 16);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('services')->insert([
            [
                'slug' => 'armed',
                'name_en' => 'Armed Security',
                'name_ka' => 'იარაღიანი დაცვა',
                'description_en' => 'Armed security protection service.',
                'description_ka' => 'იარაღიანი დაცვის სერვისი',
                'icon' => 'shield_check',
                'hourly_rate' => 100,
                'daily_rate' => 1800,
                'requires_vehicle' => false,
                'team_service_type' => 'armed',
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'unarmed',
                'name_en' => 'Unarmed Security',
                'name_ka' => 'უიარაღო დაცვა',
                'description_en' => 'Unarmed security protection service.',
                'description_ka' => 'უიარაღო დაცვის სერვისი',
                'icon' => 'shield',
                'hourly_rate' => 70,
                'daily_rate' => 1200,
                'requires_vehicle' => false,
                'team_service_type' => 'unarmed',
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'mobile_patrol',
                'name_en' => 'Mobile patrol (vehicle)',
                'name_ka' => 'მობილური პატრული (ავტომობილი)',
                'description_en' => 'Vehicle-based guarding and patrol.',
                'description_ka' => 'ავტომობილზე დაფუძნებული დაცვა და პატრული',
                'icon' => 'car',
                'hourly_rate' => 90,
                'daily_rate' => 1600,
                'requires_vehicle' => true,
                'team_service_type' => 'armed',
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'heavy_armed',
                'name_en' => 'Heavy armed security',
                'name_ka' => 'მძიმე იარაღიანი დაცვა',
                'description_en' => 'Heavy armed protection detail.',
                'description_ka' => 'მძიმე იარაღიანი დაცვის დეტალი',
                'icon' => 'shield_check',
                'hourly_rate' => 130,
                'daily_rate' => 2400,
                'requires_vehicle' => false,
                'team_service_type' => 'armed',
                'is_active' => true,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
