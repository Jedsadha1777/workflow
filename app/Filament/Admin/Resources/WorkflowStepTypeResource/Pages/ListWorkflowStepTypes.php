<?php

namespace App\Filament\Admin\Resources\WorkflowStepTypeResource\Pages;

use App\Filament\Admin\Resources\WorkflowStepTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowStepTypes extends ListRecords
{
    protected static string $resource = WorkflowStepTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
