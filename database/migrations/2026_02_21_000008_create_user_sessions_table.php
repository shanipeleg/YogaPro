<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->timestamp('watched_at')->useCurrent();
            $table->boolean('completed_full_video')->default(false);
            $table->tinyInteger('overall_rating')->nullable()->comment('1–5');
            $table->text('notes')->nullable();
            $table->json('body_state')->nullable()->comment('Snapshot of zone selections at time of session');
            $table->tinyInteger('energy_level')->nullable()->comment('1–5');
            $table->tinyInteger('time_available')->nullable()->comment('Minutes available');
            $table->json('goals')->nullable()->comment('Array of goal strings selected');
            $table->json('tags')->nullable()->comment('Array of session tag strings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
