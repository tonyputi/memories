<?php

namespace Database\Factories;

use App\Models\Disk;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

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
            'name' => "{$this->faker->word}.jpg",
            'path' => function (array $attributes) {
                $file = UploadedFile::fake()->image($attributes['name']);
                Disk::find($attributes['disk_id'])->storage()->putFile('/', $file);

                return $file->hashName();
            },
            'meta' => [
                'gps' => [
                    'altitude' => $this->faker->numberBetween(0, 1000),
                    'latitude' => $this->faker->latitude,
                    'longitude' => $this->faker->longitude,
                ],
            ],
        ];
    }
}
