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
            'user_id' => User::factory(),
            'disk_id' => Disk::factory(),
            'name' => "{$this->faker->word}.jpg",
            'path' => function (array $attributes) {
                $storage = Disk::find($attributes['disk_id'])->storage();
                $file = UploadedFile::fake()->image($attributes['name']);
                $storage->putFile('/', $file);

                return $file->hashName();
            },
            'meta' => [],
        ];
    }
}
