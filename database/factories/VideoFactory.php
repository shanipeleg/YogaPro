<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        return [
            'channel_id'        => Channel::factory(),
            'youtube_id'        => $this->faker->regexify('[A-Za-z0-9_-]{11}'),
            'title'             => $this->faker->sentence(4),
            'description'       => $this->faker->paragraph(),
            'url'               => 'https://www.youtube.com/watch?v=' . $this->faker->regexify('[A-Za-z0-9_-]{11}'),
            'thumbnail_url'     => 'https://img.youtube.com/vi/abc/hqdefault.jpg',
            'duration_seconds'  => $this->faker->numberBetween(300, 3600),
            'published_at'      => $this->faker->dateTimeBetween('-2 years', 'now'),
            'view_count'        => $this->faker->numberBetween(100, 100000),
            'like_count'        => $this->faker->numberBetween(10, 5000),
            'analysis_status'   => 'pending',
            'analyzed_at'       => null,
            'analysis_error'    => null,
            'gemini_tokens_used'=> null,
        ];
    }

    public function analyzed(): static
    {
        return $this->state(fn (array $attrs) => [
            'analysis_status' => 'analyzed',
            'analyzed_at'     => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attrs) => [
            'analysis_status' => 'pending',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attrs) => [
            'analysis_status' => 'failed',
            'analysis_error'  => 'Gemini API error',
        ]);
    }
}
