<?php

namespace Database\Factories;

use App\Models\SegmentMove;
use App\Models\VideoSegment;
use App\Models\YogaMove;
use Illuminate\Database\Eloquent\Factories\Factory;

class SegmentMoveFactory extends Factory
{
    protected $model = SegmentMove::class;

    public function definition(): array
    {
        return [
            'video_segment_id' => VideoSegment::factory(),
            'yoga_move_id'     => YogaMove::factory(),
            'role'             => 'main',
            'side'             => 'n_a',
            'hold_count'       => null,
            'ai_confidence'    => 0.95,
            'created_at'       => now(),
        ];
    }
}
