<?php

namespace App\Filament\App\Resources\TemplateDocumentResource\Pages;

use App\Filament\App\Resources\TemplateDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplateDocument extends CreateRecord
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
