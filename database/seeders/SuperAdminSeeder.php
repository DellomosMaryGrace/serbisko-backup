<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'first_name'     => 'Super',
            'last_name'      => 'Admin',
            'middle_name'    => null,
            'extension_name' => null,
            'birthday'       => '2000-01-01', // Placeholder birthday
            'role'           => 'super_admin',
            'password'       => Hash::make('admin123'), // Change this immediately after login
        ]);
    }
}