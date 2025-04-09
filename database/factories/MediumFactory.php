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
            'hash' => fn (array $attributes) => pathinfo($attributes['name'], PATHINFO_FILENAME),
            'meta' => function (array $attributes) {
                $storage = Disk::find($attributes['disk_id'])->storage();

                return [
                    'taken_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
                    'width' => $this->faker->numberBetween(100, 1000),
                    'height' => $this->faker->numberBetween(100, 1000),
                    'orientation' => $this->faker->numberBetween(0, 1),
                    'mimetype' => $storage->mimeType($attributes['path']),
                    'camera' => [
                        'make' => $this->faker->word,
                        'model' => $this->faker->word,
                    ],
                    'gps' => [
                        'lat' => $this->faker->latitude,
                        'lng' => $this->faker->longitude,
                    ],
                ];
            },
        ];
    }
}
