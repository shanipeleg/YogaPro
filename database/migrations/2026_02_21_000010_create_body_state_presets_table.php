<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_state_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('e.g. "Bad back day", "Morning stiffness"');
            $table->json('zones')->comment('Array of zone objects: [{name, mode}] where mode is "sore" or "target"');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_state_presets');
    }
};
