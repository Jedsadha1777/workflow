<?php

namespace App\Filament\App\Resources\TemplateDocumentResource\Pages;

use App\Filament\App\Resources\TemplateDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTemplateDocument extends EditRecord
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}