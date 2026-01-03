<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Filament\App\Resources\DocumentResource;
use App\Services\CalculationService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->canBeEditedBy(auth()->user())),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {

        \Log::info('=== mutateFormDataBeforeSave START ===');


        // Parse form_data
        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        }

        // ใช้ template จาก record (เพราะ field ถูก disabled)
        if ($this->record && $this->record->template_document_id) {
            $template = \App\Models\TemplateDocument::select([
                'id',
                'calculation_scripts'
            ])->find($this->record->template_document_id);


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

    protected function getRedirectUrl(): string
    {
        // Redirect to setup approval page
        return static::getResource()::getUrl('setup-approval', ['record' => $this->record]);
    }
}
