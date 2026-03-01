<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make'); // Manufacturer
            $table->string('model');
            $table->string('license_plate')->unique();
            $table->string('color')->nullable();
            $table->year('year')->nullable();
            $table->enum('vehicle_type', ['sedan', 'suv', 'van', 'motorcycle']); // Vehicle type
            $table->enum('status', ['available', 'in_use', 'maintenance', 'offline'])->default('available');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
