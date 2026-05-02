<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::factory()
            ->count(30)
            ->state(fn () => ['phone' => fake()->unique()->e164PhoneNumber()])
            ->create();
    }
}
