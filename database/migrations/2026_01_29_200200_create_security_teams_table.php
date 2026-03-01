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
        Schema::create('security_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('team_size'); // Number of security personnel in team
            $table->enum('service_type', ['armed', 'unarmed']); // იარაღიანი / უიარაღო
            $table->enum('status', ['available', 'busy', 'offline'])->default('available');
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
        Schema::dropIfExists('security_teams');
    }
};
