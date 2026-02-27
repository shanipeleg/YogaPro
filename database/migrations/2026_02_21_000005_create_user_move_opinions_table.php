<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_move_opinions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yoga_move_id')->constrained('yoga_moves')->cascadeOnDelete();
            $table->tinyInteger('personal_difficulty')->nullable()->comment('1–10');
            $table->tinyInteger('comfort_level')->nullable()->comment('1=painful, 5=love it');
            $table->boolean('is_avoided')->default(false);
            $table->text('avoid_reason')->nullable();
            $table->text('personal_notes')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('yoga_move_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_move_opinions');
    }
};
