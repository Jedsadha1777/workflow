<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['approvers']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $approversData = $this->form->getRawState()['approvers'] ?? [];
        
        $stepOrder = 1;
        foreach ($approversData as $approver) {
            if (is_array($approver) && isset($approver['approver_id']) && !empty($approver['approver_id'])) {
                $this->record->approvers()->create([
                    'approver_id' => $approver['approver_id'],
                    'step_order' => $stepOrder,
                ]);
                $stepOrder++;
            }
        }
    }
}