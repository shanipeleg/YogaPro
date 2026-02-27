<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->tinyInteger('order_index')->unsigned()->comment('1-based order within the video');
            $table->enum('segment_type', ['pose', 'transition']);
            $table->integer('start_time_seconds');
            $table->integer('end_time_seconds');
            $table->integer('duration_seconds')->storedAs('end_time_seconds - start_time_seconds');
            $table->timestamps();

            $table->index(['video_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_segments');
    }
};
