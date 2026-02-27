<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'youtube_channel_id' => 'UC' . $this->faker->regexify('[A-Za-z0-9]{22}'),
            'name'               => $this->faker->company() . ' Yoga',
            'handle'             => '@' . $this->faker->userName(),
            'description'        => $this->faker->sentence(),
            'thumbnail_url'      => null,
            'last_scanned_at'    => null,
        ];
    }
}
