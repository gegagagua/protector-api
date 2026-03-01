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
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('code', 6);
            $table->enum('type', ['registration', 'login', 'verification'])->default('login');
            $table->boolean('is_used')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['phone', 'code', 'is_used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
