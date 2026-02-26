<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {

        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        User::truncate();

        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $adminDept = Division::where('code', 'ADMIN')->first();
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'division_id' => $adminDept->id,
        ]);

    }
}
