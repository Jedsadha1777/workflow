<?php

namespace App\Filament\Admin\Resources\WorkflowStepTypeResource\Pages;

use App\Filament\Admin\Resources\WorkflowStepTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowStepType extends CreateRecord
{
    protected static string $resource = WorkflowStepTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
