<?php

namespace Database\Factories;

use App\Models\Disk;
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
                'taken_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
                'width' => $this->faker->numberBetween(100, 1000),
                'height' => $this->faker->numberBetween(100, 1000),
                'orientation' => $this->faker->numberBetween(1, 8),
                'mimetype' => $this->faker->mimeType,
                'camera' => [
                    'make' => $this->faker->word,
                    'model' => $this->faker->word,
                ],
                'gps' => [
                    'lat' => $this->faker->latitude,
                    'lng' => $this->faker->longitude,
                ],
            ],
        ];
    }
}
