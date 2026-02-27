<?php

namespace Database\Factories;

use App\Models\YogaMove;
use Illuminate\Database\Eloquent\Factories\Factory;

class YogaMoveFactory extends Factory
{
    protected $model = YogaMove::class;

    public function definition(): array
    {
        return [
            'name'                    => $this->faker->words(3, true) . ' Pose',
            'sanskrit_name'           => $this->faker->words(2, true) . 'asana',
            'aliases'                 => null,
            'description'             => $this->faker->sentence(),
            'category'                => $this->faker->randomElement(['standing', 'seated', 'supine', 'prone', 'inversion', 'balancing', 'restorative']),
            'difficulty_base'         => $this->faker->numberBetween(1, 10),
            'enrichment_status'       => 'enriched',
            'data_source'             => 'api',

            // Body areas — all false by default; use states to enable specific ones
            'targets_lower_back'  => false,
            'targets_upper_back'  => false,
            'targets_mid_back'    => false,
            'targets_pelvis'      => false,
            'targets_hips'        => false,
            'targets_hamstrings'  => false,
            'targets_hip_flexors' => false,
            'targets_glutes'      => false,
            'targets_core'        => false,
            'targets_shoulders'   => false,
            'targets_neck'        => false,
            'targets_chest'       => false,
            'targets_quads'       => false,
            'targets_calves'      => false,
            'targets_ankles'      => false,
            'targets_wrists'      => false,

            // Health benefits
            'benefit_back_pain_lower'   => 'neutral',
            'benefit_back_pain_upper'   => 'neutral',
            'benefit_back_pain_general' => 'neutral',
            'benefit_pelvic_floor'      => 'neutral',
            'benefit_hip_mobility'      => 'neutral',
            'benefit_flexibility'       => false,
            'benefit_strength'          => false,
            'benefit_balance'           => false,
            'benefit_stress_relief'     => false,
            'benefit_circulation'       => false,
            'benefit_digestion'         => false,
            'benefit_posture'           => false,

            // Contraindications / risk
            'contraindications'   => null,
            'spinal_compression'  => false,
            'spinal_flexion'      => false,
            'spinal_extension'    => false,
            'spinal_rotation'     => false,
            'high_impact'         => false,
            'weight_bearing_joints' => null,
            'is_inversion'        => false,
            'modifications_available'    => false,
            'modifications_description'  => null,
            'image_url'           => null,
            'source_url'          => null,
        ];
    }

    public function backPainHelps(): static
    {
        return $this->state(fn (array $attrs) => [
            'benefit_back_pain_lower'   => 'helps',
            'benefit_back_pain_general' => 'helps',
        ]);
    }

    public function backPainAvoid(): static
    {
        return $this->state(fn (array $attrs) => [
            'benefit_back_pain_lower'   => 'avoid',
            'benefit_back_pain_general' => 'avoid',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attrs) => [
            'enrichment_status' => 'pending',
        ]);
    }
}
