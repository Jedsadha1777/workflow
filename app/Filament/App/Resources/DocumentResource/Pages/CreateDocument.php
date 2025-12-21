<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
use App\Filament\App\Resources\DocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;
    protected static bool $canCreateAnother = false;

    // Hook ที่ทำงานก่อน Repeater เพิ่ม item
    public function beforeValidate(): void
    {
        // ถ้ามี approvers และยังไม่มี record ให้สร้าง draft ก่อน
        if (!$this->record && !empty($this->data['approvers'])) {
            $this->saveDraftBeforeApprovers();
        }
    }

    protected function saveDraftBeforeApprovers(): void
    {
        $data = $this->form->getState();
        
        $data['creator_id'] = auth()->id();
        $data['department_id'] = auth()->user()->department_id;
        $data['status'] = DocumentStatus::DRAFT;
        
        if (isset($data['template_document_id'])) {
            $template = \App\Models\TemplateDocument::find($data['template_document_id']);
            if ($template && $template->content) {
                $data['content'] = $template->content;
            }
        }
        
        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        } else {
            $data['form_data'] = [];
        }
        
        unset($data['approvers']);
        
        // สร้าง record
        $this->record = static::getModel()::create($data);
        
        // Redirect to edit page
        $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creator_id'] = auth()->id();
        $data['department_id'] = auth()->user()->department_id;
        $data['status'] = DocumentStatus::DRAFT;
        
        if (isset($data['template_document_id'])) {
            $template = \App\Models\TemplateDocument::find($data['template_document_id']);
            if ($template && $template->content) {
                $data['content'] = $template->content;
            }
        }
        
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