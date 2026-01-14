<?php

namespace Database\Seeders;

use App\Models\WorkflowStepType;
use Illuminate\Database\Seeder;

class WorkflowStepTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Prepare', 'code' => 'prepare'],
            ['name' => 'Checking', 'code' => 'checking'],
            ['name' => 'Approve', 'code' => 'approve'],
        ];

        foreach ($types as $type) {
            WorkflowStepType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
