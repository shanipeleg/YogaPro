<?php

namespace Database\Factories;

use App\Models\UserSession;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSessionFactory extends Factory
{
    protected $model = UserSession::class;

    public function definition(): array
    {
        return [
            'video_id'             => Video::factory()->analyzed(),
            'watched_at'           => $this->faker->dateTimeBetween('-6 months', 'now'),
            'completed_full_video' => $this->faker->boolean(),
            'overall_rating'       => $this->faker->numberBetween(1, 5),
            'notes'                => $this->faker->optional()->sentence(),
            'body_state'           => null,
            'energy_level'         => $this->faker->numberBetween(1, 5),
            'time_available'       => $this->faker->randomElement([20, 30, 45, 60]),
            'goals'                => ['stretch'],
            'tags'                 => [],
        ];
    }
}
