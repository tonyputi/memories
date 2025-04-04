<?php

namespace Database\Factories;

use App\Models\Disk;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Medium>
 */
class MediumFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk_id' => Disk::factory(),
            'path' => $this->faker->word,
            'name' => $this->faker->word,
            'type' => $this->faker->mimeType,
            'hash' => $this->faker->md5,
            'size' => $this->faker->numberBetween(100, 1000000),
            'meta' => [],
            'user_id' => User::factory(),
        ];
    }
}
