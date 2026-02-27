<?php

namespace Database\Factories;

use App\Models\SessionMoveFlag;
use App\Models\UserSession;
use App\Models\YogaMove;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionMoveFlagFactory extends Factory
{
    protected $model = SessionMoveFlag::class;

    public function definition(): array
    {
        return [
            'user_session_id'       => UserSession::factory(),
            'yoga_move_id'          => YogaMove::factory(),
            'flag'                  => $this->faker->randomElement(['loved', 'uncomfortable', 'avoided', 'too_hard', 'too_easy']),
            'conditional_avoidance' => null,
            'notes'                 => null,
        ];
    }
}
