<?php

namespace Database\Factories;

use App\Models\BodyStatePreset;
use Illuminate\Database\Eloquent\Factories\Factory;

class BodyStatePresetFactory extends Factory
{
    protected $model = BodyStatePreset::class;

    public function definition(): array
    {
        return [
            'name'  => $this->faker->words(2, true),
            'zones' => [
                ['zone' => 'lower_back', 'mode' => 'sore'],
            ],
        ];
    }
}
