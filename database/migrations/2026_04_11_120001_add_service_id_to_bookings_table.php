<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('service_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('services')
                ->restrictOnDelete();
        });

        $armedId = DB::table('services')->where('slug', 'armed')->value('id');
        $unarmedId = DB::table('services')->where('slug', 'unarmed')->value('id');

        if ($armedId) {
            DB::table('bookings')->where('service_type', 'armed')->whereNull('service_id')->update(['service_id' => $armedId]);
        }
        if ($unarmedId) {
            DB::table('bookings')->where('service_type', 'unarmed')->whereNull('service_id')->update(['service_id' => $unarmedId]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
    }
};
