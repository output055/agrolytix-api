<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create default admin if not exists
        User::firstOrCreate(
            ['email' => 'admin@agrolytix.local'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('admin1234'),
                'role'     => 'Admin',
                'status'   => 'active',
            ]
        );

        $this->call([
            RetailProductSeeder::class,
            WholesaleProductSeeder::class,
        ]);
    }
}

