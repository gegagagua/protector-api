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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('security_team_id')->constrained('security_teams')->onDelete('cascade');
            $table->integer('rating')->unsigned(); // 1-5 stars
            $table->text('review')->nullable();
            $table->timestamps();
            
            $table->unique(['booking_id', 'client_id']);
            $table->index('security_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
