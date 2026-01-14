<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'code' => 'admin', 'is_admin' => true],
            ['name' => 'LD', 'code' => 'LD', 'is_admin' => false],
            ['name' => 'DM', 'code' => 'DM', 'is_admin' => false],
            ['name' => 'DCC', 'code' => 'DCC', 'is_admin' => false],
            ['name' => 'MD', 'code' => 'MD', 'is_admin' => false],
            ['name' => 'ACC', 'code' => 'ACC', 'is_admin' => false],
            ['name' => 'IT', 'code' => 'IT', 'is_admin' => false],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}
