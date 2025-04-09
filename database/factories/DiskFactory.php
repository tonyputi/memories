<?php

namespace Database\Factories;

use App\Enums\DiskDriver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            // 'driver' => $this->faker->randomElement(array_map(fn($e) => $e->value, DiskDriver::cases())),
            'driver' => $this->faker->randomElement(DiskDriver::cases()),
            'name' => fn (array $attributes) => Str::title("{$attributes['driver']->value} disk: {$this->faker->word}"),
            'config' => function (array $attributes) {
                $driver = $attributes['driver']->value;
                $config = config("filesystems.disks.{$driver}");
                unset($config['driver']);

                return $config;
            },
        ];
    }

    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'driver' => DiskDriver::Local,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function s3(): static
    {
        return $this->state(fn (array $attributes) => [
            'driver' => DiskDriver::S3,
        ]);
    }
}
