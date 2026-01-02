<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_all_logs')
                ->label('View All Logs')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('info')
                ->url(fn() => static::getResource()::getUrl('logs')),
        ];
    }
}