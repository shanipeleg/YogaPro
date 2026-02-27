<?php

namespace Database\Factories;

use App\Models\UserMoveOpinion;
use App\Models\YogaMove;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserMoveOpinionFactory extends Factory
{
    protected $model = UserMoveOpinion::class;

    public function definition(): array
    {
        return [
            'yoga_move_id'        => YogaMove::factory(),
            'personal_difficulty' => $this->faker->numberBetween(1, 10),
            'comfort_level'       => $this->faker->numberBetween(1, 5),
            'is_avoided'          => false,
            'avoid_reason'        => null,
            'personal_notes'      => null,
            'conditional_avoidance' => null,
        ];
    }

    public function avoided(): static
    {
        return $this->state(fn (array $attrs) => [
            'is_avoided'    => true,
            'avoid_reason'  => 'Triggers lower back pain',
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn (array $attrs) => [
            'comfort_level' => 5,
            'is_avoided'    => false,
        ]);
    }
}
