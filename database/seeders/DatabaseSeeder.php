<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::SUPER_ADMIN,
        ]);

        // Admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
        ]);

        // Users
        $userRoles = [
            ['name' => 'LD User', 'email' => 'ld@example.com', 'role' => UserRole::LD],
            ['name' => 'DM User', 'email' => 'dm@example.com', 'role' => UserRole::DM],
            ['name' => 'DCC User', 'email' => 'dcc@example.com', 'role' => UserRole::DCC],
            ['name' => 'MD User', 'email' => 'md@example.com', 'role' => UserRole::MD],
            ['name' => 'ACC User', 'email' => 'acc@example.com', 'role' => UserRole::ACC],
            ['name' => 'IT User', 'email' => 'it@example.com', 'role' => UserRole::IT],
        ];

        foreach ($userRoles as $userData) {
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => bcrypt('password'),
                'role' => $userData['role'],
            ]);
        }
    }
}