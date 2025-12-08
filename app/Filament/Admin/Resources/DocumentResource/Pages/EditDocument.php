<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['approvers'] = $this->record->approvers()
            ->orderBy('step_order')
            ->get()
            ->map(fn ($approver) => [
                'approver_id' => $approver->approver_id,
            ])
            ->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['approvers']);
        
        return $data;
    }

    protected function afterSave(): void
    {
        $approversData = $this->form->getRawState()['approvers'] ?? [];
        
        $this->record->approvers()->delete();
        
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