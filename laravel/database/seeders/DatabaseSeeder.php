<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        \App\Models\Currency::factory()->createMany(
            [
                [
                    'name' => 'coin',
                    'code' => 'COIN',
                ],
                [
                    'name' => 'rupiah',
                    'code' => 'IDR',
                ]
            ]
        );

        \App\Models\Leveling::factory()->createMany(
            [
                [
                    'level_name' => 'Kota',
                ],
                [
                    'level_name' => 'Provinsi',
                ],
                [
                    'level_name' => 'Pulau',
                ],
                [
                    'level_name' => 'Negara',
                ],
            ]
        );
    }

    // public function run()
    // {
    //     // \App\Models\User::factory(10)->create();
    //     \App\Models\Order::factory(10)->create();
    // }
}
