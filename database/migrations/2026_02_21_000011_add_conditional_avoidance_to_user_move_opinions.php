<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_move_opinions', function (Blueprint $table) {
            $table->json('conditional_avoidance')->nullable()->after('avoid_reason')
                ->comment('Zone-conditional avoidance rules, e.g. {"zones": ["lower_back"], "permanent": false}');
        });
    }

    public function down(): void
    {
        Schema::table('user_move_opinions', function (Blueprint $table) {
            $table->dropColumn('conditional_avoidance');
        });
    }
};
