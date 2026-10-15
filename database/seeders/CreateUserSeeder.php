<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CreateUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::factory(1)->create();

        \App\Models\User::factory()->create([
            'name' => 'Mejba',
            'email' => 'mejbaalom@gmail.com',
            'password' => bcrypt('12345'),
            'role' => 'Admin'
        ]);
    }
}
