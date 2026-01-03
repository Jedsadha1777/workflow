<?php

namespace App\Filament\Admin\Resources\TemplateDocumentResource\Pages;

use App\Filament\Admin\Resources\TemplateDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplateDocument extends CreateRecord
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['version'] = 1;
        $data['status'] = \App\Enums\TemplateStatus::DRAFT;
        $data['is_active'] = true;
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
    
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
        ];
    }
    
    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->submit(null)
            ->action('create');
    }
}