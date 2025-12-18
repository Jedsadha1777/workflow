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
        
        // Parse content from JSON string to array
        if (isset($data['content']) && is_string($data['content'])) {
            $parsed = json_decode($data['content'], true);
            $data['content'] = $parsed ?: null;
        } else {
            // ถ้าไม่มี content ให้ดึงจาก template
            if (isset($data['template_document_id'])) {
                $template = \App\Models\TemplateDocument::find($data['template_document_id']);
                if ($template && $template->content) {
                    $data['content'] = $template->content;
                }
            }
        }
        
        // Parse form_data from JSON string to array
        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        } else {
            $data['form_data'] = [];
        }
        
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
                    'signature_cell' => $approver['signature_cell'] ?? null,
                ]);
                $stepOrder++;
            }
        }
    }
}