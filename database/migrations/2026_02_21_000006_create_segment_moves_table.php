<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_segment_id')->constrained('video_segments')->cascadeOnDelete();
            $table->foreignId('yoga_move_id')->constrained('yoga_moves')->cascadeOnDelete();
            $table->enum('role', ['main', 'transition_from', 'transition_to']);
            $table->enum('side', ['left', 'right', 'both', 'n_a'])->default('n_a');
            $table->tinyInteger('hold_count')->nullable();
            $table->decimal('ai_confidence', 3, 2)->nullable()->comment('0.00–1.00');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['video_segment_id', 'role']);
            $table->index('yoga_move_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_moves');
    }
};
