<?php

namespace App\Filament\Admin\Resources\WorkflowResource\Pages;

use App\Filament\Admin\Resources\WorkflowResource;
use App\Models\Workflow;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkflow extends CreateRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $stepsData = $data['steps'] ?? [];
        unset($data['steps']);

        $workflow = Workflow::create($data);

        $version = $workflow->createNewVersion();

        foreach ($stepsData as $index => $stepData) {
            $version->steps()->create([
                'step_order' => $index + 1,
                'template_step_order' => $stepData['template_step_order'],
                'role_id' => $stepData['role_id'],
                'department_id' => $stepData['department_id'] ?? null,
                'step_type_id' => $stepData['step_type_id'],
            ]);
        }

        return $workflow;
    }

    protected function getRedirectUrl(): string
    {
        return ManageVersions::getUrl(['record' => $this->record]);
    }

    protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateAnotherFormAction()->visible(false);
    }
}
