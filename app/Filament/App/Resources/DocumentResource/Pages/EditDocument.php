<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Filament\App\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->canBeEditedBy(auth()->user())),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['approvers'] = $this->record->approvers()
            ->orderBy('step_order')
            ->get()
            ->map(fn ($approver) => [
                'approver_id' => $approver->approver_id,
                'signature_cell' => $approver->signature_cell,
            ])
            ->toArray();

        // โหลด form_data
        if ($this->record->form_data) {
            $data['form_data'] = json_encode($this->record->form_data);
        } else {
            $data['form_data'] = '{}';
        }

        // โหลด content เพื่อแสดง (แต่จะไม่ส่งกลับ)
        if ($this->record->content) {
            $data['content'] = json_encode($this->record->content);
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Parse form_data จาก JSON string
        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        }
        
        // ไม่แตะ content (ใช้จาก database เดิม)
        unset($data['content']);
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
                    'signature_cell' => $approver['signature_cell'] ?? null,
                ]);
                $stepOrder++;
            }
        }
    }
}