<?php

namespace Database\Factories;

use App\Models\Video;
use App\Models\VideoSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoSegmentFactory extends Factory
{
    protected $model = VideoSegment::class;

    public function definition(): array
    {
        $start = $this->faker->numberBetween(0, 3000);
        $end   = $start + $this->faker->numberBetween(5, 60);

        return [
            'video_id'           => Video::factory()->analyzed(),
            'order_index'        => $this->faker->numberBetween(1, 200),
            'segment_type'       => 'pose',
            'start_time_seconds' => $start,
            'end_time_seconds'   => $end,
            // duration_seconds is a generated column (end - start); MySQL computes it automatically
        ];
    }

    public function transition(): static
    {
        return $this->state(fn (array $attrs) => [
            'segment_type' => 'transition',
        ]);
    }
}
