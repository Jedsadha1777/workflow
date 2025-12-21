<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
use App\Filament\App\Resources\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;
    protected static bool $canCreateAnother = false;

    // ใช้ Livewire hook ที่ทำงานก่อน state update
    public function updatedData($value, $key): void
    {
        // เช็คว่ามีการเพิ่ม approver
        if ($key === 'approvers' && !$this->record && !empty($this->data['approvers'])) {
            \Log::info('Add approver detected, saving draft...');
            $this->saveDraftBeforeApprovers();
        }
    }

    protected function saveDraftBeforeApprovers(): void
    {
        try {
            $data = $this->form->getState();
            
            \Log::info('Form data:', $data);
            
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
            
            $document = static::getModel()::create($data);
            
            \Log::info('Draft created:', ['id' => $document->id]);
            
            Notification::make()
                ->success()
                ->title('Draft Saved')
                ->body('Document saved as draft')
                ->send();
            
            // Redirect
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $document]));
            
        } catch (\Exception $e) {
            \Log::error('Save draft failed:', ['error' => $e->getMessage()]);
            
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Failed to save draft: ' . $e->getMessage())
                ->send();
        }
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