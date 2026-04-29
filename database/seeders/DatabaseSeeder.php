<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create the System/HQ Business
        $business = Business::firstOrCreate(
            ['name' => 'Agrolytix HQ'],
            [
                'email'               => 'system@agrolytix.com',
                'subscription_status' => 'active',
                'subscription_plan'   => 'Pro',
                'subscription_ends_at' => now()->addYears(10),
            ]
        );

        // Create the Super Admin
        User::firstOrCreate(
            ['email' => 'superadmin@agrolytix.com'],
            [
                'name'        => 'Super Admin',
                'password'    => Hash::make('superadmin1234'),
                'role'        => User::ROLE_SUPER_ADMIN,
                'status'      => 'active',
                'business_id' => $business->id,
            ]
        );

        // Create default business admin
        User::firstOrCreate(
            ['email' => 'admin@agrolytix.local'],
            [
                'name'        => 'Business Administrator',
                'password'    => Hash::make('admin1234'),
                'role'        => User::ROLE_ADMIN,
                'status'      => 'active',
                'business_id' => $business->id,
            ]
        );

        $this->call([
            RetailProductSeeder::class,
            WholesaleProductSeeder::class,
        ]);
    }
}

