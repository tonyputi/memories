<?php

namespace Database\Factories;

use App\Enums\DiskDriver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Disk>
 */
class DiskFactory extends Factory
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
            'name' => $this->faker->word,
            'driver' => DiskDriver::Local->value,
            'config' => function (array $attributes) {
                return [
                    'root' => storage_path("app/public/{$attributes['user_id']}"),
                    'url' => sprintf('%s/storage/%s', config('app.url'), $attributes['user_id']),
                    'visibility' => 'public',
                    'throw' => false,
                    'report' => false,
                ];
            },
        ];
    }
}
