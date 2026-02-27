<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoga_moves', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('sanskrit_name', 255)->nullable();
            $table->json('aliases')->nullable();
            $table->text('description')->nullable();
            $table->enum('category', ['standing', 'seated', 'supine', 'prone', 'inversion', 'balancing', 'restorative', 'transition'])->nullable();
            $table->tinyInteger('difficulty_base')->nullable()->comment('1–10');

            // Body areas targeted
            $table->boolean('targets_lower_back')->default(false);
            $table->boolean('targets_upper_back')->default(false);
            $table->boolean('targets_mid_back')->default(false);
            $table->boolean('targets_pelvis')->default(false);
            $table->boolean('targets_hips')->default(false);
            $table->boolean('targets_hamstrings')->default(false);
            $table->boolean('targets_hip_flexors')->default(false);
            $table->boolean('targets_glutes')->default(false);
            $table->boolean('targets_core')->default(false);
            $table->boolean('targets_shoulders')->default(false);
            $table->boolean('targets_neck')->default(false);
            $table->boolean('targets_chest')->default(false);
            $table->boolean('targets_quads')->default(false);
            $table->boolean('targets_calves')->default(false);
            $table->boolean('targets_ankles')->default(false);
            $table->boolean('targets_wrists')->default(false);

            // Health benefits
            $table->enum('benefit_back_pain_lower', ['helps', 'neutral', 'avoid'])->default('neutral');
            $table->enum('benefit_back_pain_upper', ['helps', 'neutral', 'avoid'])->default('neutral');
            $table->enum('benefit_back_pain_general', ['helps', 'neutral', 'avoid'])->default('neutral');
            $table->enum('benefit_pelvic_floor', ['helps', 'neutral', 'avoid'])->default('neutral');
            $table->enum('benefit_hip_mobility', ['helps', 'neutral', 'avoid'])->default('neutral');
            $table->boolean('benefit_flexibility')->default(false);
            $table->boolean('benefit_strength')->default(false);
            $table->boolean('benefit_balance')->default(false);
            $table->boolean('benefit_stress_relief')->default(false);
            $table->boolean('benefit_circulation')->default(false);
            $table->boolean('benefit_digestion')->default(false);
            $table->boolean('benefit_posture')->default(false);

            // Contraindications / risks
            $table->json('contraindications')->nullable();
            $table->boolean('spinal_compression')->default(false);
            $table->boolean('spinal_flexion')->default(false);
            $table->boolean('spinal_extension')->default(false);
            $table->boolean('spinal_rotation')->default(false);
            $table->boolean('high_impact')->default(false);
            $table->json('weight_bearing_joints')->nullable();
            $table->boolean('is_inversion')->default(false);
            $table->boolean('modifications_available')->default(false);
            $table->text('modifications_description')->nullable();

            // Meta
            $table->string('image_url', 512)->nullable();
            $table->string('source_url', 512)->nullable();
            $table->enum('data_source', ['api', 'scraped', 'manual'])->default('api');
            $table->enum('enrichment_status', ['pending', 'enriched', 'manual'])->default('pending');

            $table->timestamps();

            $table->index('enrichment_status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoga_moves');
    }
};
