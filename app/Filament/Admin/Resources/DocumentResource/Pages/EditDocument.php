<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\CalculationService;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        }

        if ($this->record && $this->record->template_document_id) {
            $template = \App\Models\TemplateDocument::find($this->record->template_document_id);
            
            if ($template && $template->calculation_scripts) {
                $data['form_data'] = CalculationService::executeCalculations(
                    $data['form_data'], 
                    $template->calculation_scripts
                );
            }
        }
        
        unset($data['content']);
        
        return $data;
    }
}