<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Filament\App\Resources\DocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creator_id'] = auth()->id();
        $data['department_id'] = auth()->user()->department_id;
        
        unset($data['approvers']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $approversData = $this->form->getRawState()['approvers'] ?? [];
        
        foreach ($approversData as $index => $approver) {
            if (isset($approver['approver_id'])) {
                $this->record->approvers()->create([
                    'approver_id' => $approver['approver_id'],
                    'step_order' => $index + 1,
                ]);
            }
        }
    }
}