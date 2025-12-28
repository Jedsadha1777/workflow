<?php

namespace App\Filament\Admin\Resources\TemplateDocumentResource\Pages;

use App\Filament\Admin\Resources\TemplateDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTemplateDocument extends EditRecord
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('next')
                ->label('Next: Edit PDF Layout')
                ->icon('heroicon-o-arrow-right')
                ->color('success')
                ->url(fn() => static::getResource()::getUrl('edit-pdf-layout', ['record' => $this->record]))
                ->visible(fn() => $this->record->content !== null),
            
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit-pdf-layout', ['record' => $this->record]);
    }
}