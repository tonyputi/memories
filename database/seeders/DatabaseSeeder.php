<?php

namespace Database\Seeders;

use App\Models\Disk;
use App\Models\Medium;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Filippo Sallemi',
            'email' => 'filippo@sallemi.it',
        ]);

        $disk['local'] = Disk::factory()
            ->for($user)
            ->local()
            ->create();

        $disk['s3'] = Disk::factory()
            ->for($user)
            ->s3()
            ->create();

        // Medium::factory(10)
        //     ->for($disk['local'])
        //     ->create();

        // Medium::factory(10)
        //     ->for($disk['s3'])
        //     ->create();
    }
}
