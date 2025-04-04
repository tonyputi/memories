<?php

namespace Database\Factories;

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
            'config' => function (array $attributes) {
                return [
                    'driver' => 'local',
                    'root' => storage_path("app/public/{$attributes['user_id']}"),
                    'url' => env('APP_URL').'/storage',
                    'visibility' => 'public',
                    'throw' => false,
                    'report' => false,
                ];
            },
        ];
    }
}
