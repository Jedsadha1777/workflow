<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
use App\Filament\Admin\Resources\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\CalculationService;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!isset($data['status'])) {
            $data['status'] = DocumentStatus::DRAFT;
        }
        
        if (isset($data['template_document_id'])) {
            $template = \App\Models\TemplateDocument::find($data['template_document_id']);
            if ($template && $template->content) {
                $data['content'] = $template->content;
            }

             if ($template && $template->calculation_scripts) {
                $formData = isset($data['form_data']) && is_string($data['form_data']) 
                    ? json_decode($data['form_data'], true) 
                    : ($data['form_data'] ?? []);
                
                $data['form_data'] = CalculationService::executeCalculations(
                    $formData, 
                    $template->calculation_scripts
                );
            }
        }
        
        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        } else {
            $data['form_data'] = [];
        }
        
        return $data;
    }
}