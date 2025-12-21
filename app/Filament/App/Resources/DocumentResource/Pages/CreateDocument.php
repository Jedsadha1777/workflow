<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
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
        $data['status'] = DocumentStatus::DRAFT;
        
        // ดึง content จาก template
        if (isset($data['template_document_id'])) {
            $template = \App\Models\TemplateDocument::find($data['template_document_id']);
            if ($template && $template->content) {
                $data['content'] = $template->content;
            }
        }
        
        // Parse form_data
        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        } else {
            $data['form_data'] = [];
        }
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to setup approval page
        return static::getResource()::getUrl('setup-approval', ['record' => $this->record]);
    }
}