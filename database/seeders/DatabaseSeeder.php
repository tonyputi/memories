<?php

namespace Database\Seeders;

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

        Medium::factory(10)->create([
            'user_id' => $user->getKey(),
            'disk' => 's3'
        ]);
    }
}
