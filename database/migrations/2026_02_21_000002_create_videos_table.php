<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('youtube_id', 32)->unique();
            $table->string('title', 512);
            $table->text('description')->nullable();
            $table->string('url', 512);
            $table->string('thumbnail_url', 512)->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->enum('analysis_status', ['pending', 'processing', 'analyzed', 'failed'])->default('pending');
            $table->timestamp('analyzed_at')->nullable();
            $table->text('analysis_error')->nullable();
            $table->integer('gemini_tokens_used')->nullable();
            $table->timestamps();

            $table->index('analysis_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
