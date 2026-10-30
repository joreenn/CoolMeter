<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('cooling_settings', function (Blueprint $table) {
            $table->id(); $table->json('values'); $table->timestamps();
        });
        Schema::create('cooling_sessions', function (Blueprint $table) {
            $table->id(); $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable(); $table->dateTime('eco_at')->nullable();
            $table->unsignedTinyInteger('active_slot')->nullable()->unique();
            $table->unsignedTinyInteger('temperature'); $table->string('room_heat');
            $table->string('fan')->default('low'); $table->text('notes')->nullable();
            $table->json('assumptions'); $table->json('estimate')->nullable(); $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('cooling_sessions'); Schema::dropIfExists('cooling_settings');
    }
};
