<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        User::truncate();

        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $departments = [
            ['name' => 'Administration', 'code' => 'ADMIN', 'description' => 'Administration Department'],
            ['name' => 'Logistics', 'code' => 'LD', 'description' => 'Logistics Department'],
            ['name' => 'Development', 'code' => 'DM', 'description' => 'Development Department'],
            ['name' => 'Control', 'code' => 'DCC', 'description' => 'Document Control Center'],
            ['name' => 'Management', 'code' => 'MD', 'description' => 'Management Department'],
            ['name' => 'Accounting', 'code' => 'ACC', 'description' => 'Accounting Department'],
            ['name' => 'IT', 'code' => 'IT', 'description' => 'Information Technology'],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }

        $adminDept = Department::where('code', 'ADMIN')->first();
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
            'department_id' => $adminDept->id,
        ]);

        $userRoles = [
            ['name' => 'LD User', 'email' => 'ld@example.com', 'role' => UserRole::LD, 'code' => 'LD'],
            ['name' => 'DM User', 'email' => 'dm@example.com', 'role' => UserRole::DM, 'code' => 'DM'],
            ['name' => 'DCC User', 'email' => 'dcc@example.com', 'role' => UserRole::DCC, 'code' => 'DCC'],
            ['name' => 'MD User', 'email' => 'md@example.com', 'role' => UserRole::MD, 'code' => 'MD'],
            ['name' => 'ACC User', 'email' => 'acc@example.com', 'role' => UserRole::ACC, 'code' => 'ACC'],
            ['name' => 'IT User', 'email' => 'it@example.com', 'role' => UserRole::IT, 'code' => 'IT'],
        ];

        foreach ($userRoles as $userData) {
            $dept = Department::where('code', $userData['code'])->first();
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => bcrypt('password'),
                'role' => $userData['role'],
                'department_id' => $dept->id,
            ]);
        }
    }
}
