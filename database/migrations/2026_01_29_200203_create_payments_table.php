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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_type', ['card', 'mobile_pay', 'cash'])->default('card');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            $table->string('transaction_id')->nullable()->unique();
            $table->string('payment_gateway')->nullable(); // Which payment gateway was used
            $table->json('payment_data')->nullable(); // Additional payment information
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index(['client_id', 'status']);
            $table->index(['booking_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
