<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
use App\Filament\App\Resources\DocumentResource;
use App\Services\CalculationService;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        \Log::info('=== mutateFormDataBeforeCreate START ===');

        $data['creator_id'] = auth()->id();
        $data['division_id'] = auth()->user()->division_id;
        $data['status'] = DocumentStatus::DRAFT;

        if (isset($data['template_document_id'])) {
            $template = \App\Models\TemplateDocument::select([
                'id',
                'content',
                'calculation_scripts'
            ])->find($data['template_document_id']);

            

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

                \Log::info('After calculation', ['formData' => $data['form_data']]);
            }
        }

        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        } else if (!isset($data['form_data'])) {
            $data['form_data'] = [];
        }


        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('setup-approval', ['record' => $this->record]);
    }
}
