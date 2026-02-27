<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_analysis_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->string('gemini_model', 64);
            $table->text('prompt_used');
            $table->json('raw_response')->nullable();
            $table->integer('tokens_prompt')->nullable();
            $table->integer('tokens_response')->nullable();
            $table->enum('status', ['success', 'error']);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['video_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_analysis_log');
    }
};
