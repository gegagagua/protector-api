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
        Schema::create('location_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('security_personnel_id')->nullable()->constrained('security_personnel')->onDelete('set null');
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable(); // GPS accuracy in meters
            $table->decimal('speed', 8, 2)->nullable(); // Speed in km/h
            $table->decimal('heading', 5, 2)->nullable(); // Direction in degrees
            $table->timestamp('tracked_at');
            $table->timestamps();
            
            $table->index(['booking_id', 'tracked_at']);
            $table->index(['security_personnel_id', 'tracked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_tracking');
    }
};
