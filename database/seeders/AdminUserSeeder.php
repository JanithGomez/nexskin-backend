<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@nexskin.lk'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Nxanith@1234'),
                'role' => 'admin',
            ]
        );
    }
}