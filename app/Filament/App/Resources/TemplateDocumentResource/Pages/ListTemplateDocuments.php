<?php

namespace App\Filament\App\Resources\TemplateDocumentResource\Pages;

use App\Filament\App\Resources\TemplateDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTemplateDocuments extends ListRecords
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}