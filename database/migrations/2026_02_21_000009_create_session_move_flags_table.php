<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_move_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_session_id')->constrained('user_sessions')->cascadeOnDelete();
            $table->foreignId('yoga_move_id')->constrained('yoga_moves')->cascadeOnDelete();
            $table->enum('flag', ['loved', 'uncomfortable', 'avoided', 'unclear_instructions', 'too_hard', 'too_easy']);
            $table->json('conditional_avoidance')->nullable()->comment('e.g. {"zones": ["lower_back"], "permanent": false}');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_move_flags');
    }
};
