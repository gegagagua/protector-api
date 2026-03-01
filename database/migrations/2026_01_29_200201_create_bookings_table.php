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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('security_team_id')->nullable()->constrained('security_teams')->onDelete('set null');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('set null');
            $table->enum('service_type', ['armed', 'unarmed']); // იარაღიანი / უიარაღო
            $table->integer('security_personnel_count'); // Number of security personnel
            $table->integer('persons_to_protect_count'); // Number of people to protect
            $table->string('address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('duration_hours'); // Duration in hours
            $table->enum('booking_type', ['immediate', 'scheduled']); // ახლავე / წინასწარ დაჯავშნა
            $table->enum('status', ['pending', 'confirmed', 'ongoing', 'arrived', 'completed', 'cancelled'])->default('pending');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'partially_refunded', 'fully_refunded'])->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable(); // When security team started en route
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['client_id', 'status']);
            $table->index(['security_team_id', 'status']);
            $table->index('start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
