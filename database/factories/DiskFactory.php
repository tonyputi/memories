<?php

namespace Database\Factories;

use App\Enums\DiskDriver;
use App\Models\Disk;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Disk>
 */
class DiskFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    // public function configure(): static
    // {
    //     return $this->afterCreating(function (Disk $disk) {
    //         $disk->config = [
    //             'root' => config('filesystems.disks.public.root'),
    //             'url' => config('filesystems.disks.public.url'),
    //             'visibility' => 'public',
    //             'throw' => false,
    //             'report' => false,
    //         ];

    //         $disk->save();
    //     });
    // }

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
            'config' => [
                'root' => config('filesystems.disks.public.root'),
                'url' => config('filesystems.disks.public.url'),
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ],
        ];
    }
}
