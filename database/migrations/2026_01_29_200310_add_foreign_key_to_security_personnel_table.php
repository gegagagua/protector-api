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
        Schema::table('security_personnel', function (Blueprint $table) {
            $table->foreign('security_team_id')->references('id')->on('security_teams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_personnel', function (Blueprint $table) {
            $table->dropForeign(['security_team_id']);
        });
    }
};
