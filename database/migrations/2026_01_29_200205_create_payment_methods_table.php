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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->enum('type', ['card', 'mobile_pay']);
            $table->string('provider')->nullable(); // e.g., 'tbc', 'bog', 'liberty', 'payze', etc.
            $table->string('last_four')->nullable(); // Last 4 digits of card
            $table->string('card_holder_name')->nullable();
            $table->string('token')->nullable(); // Payment token from gateway
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional payment method data
            $table->timestamps();
            
            $table->index(['client_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
