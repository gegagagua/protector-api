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
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('code_hash')->nullable()->after('code');
            $table->unsignedTinyInteger('attempt_count')->default(0)->after('is_used');
            $table->timestamp('last_sent_at')->nullable()->after('expires_at');

            $table->index(['phone', 'is_used', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex(['phone', 'is_used', 'expires_at']);
            $table->dropColumn(['code_hash', 'attempt_count', 'last_sent_at']);
        });
    }
};
