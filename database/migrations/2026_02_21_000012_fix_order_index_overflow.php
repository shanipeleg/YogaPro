<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change order_index from TINYINT UNSIGNED (max 255) to SMALLINT UNSIGNED (max 65535).
     * A dense 40-minute yoga video can easily have 256+ segments (poses + transitions),
     * causing an "Out of range value" error at segment 256.
     */
    public function up(): void
    {
        Schema::table('video_segments', function (Blueprint $table) {
            $table->smallInteger('order_index')->unsigned()->change();
        });
    }

    public function down(): void
    {
        Schema::table('video_segments', function (Blueprint $table) {
            $table->tinyInteger('order_index')->unsigned()->change();
        });
    }
};
