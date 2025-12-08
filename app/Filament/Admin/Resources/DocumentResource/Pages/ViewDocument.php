<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\TextEntry::make('title'),
                Infolists\Components\TextEntry::make('creator.name'),
                Infolists\Components\TextEntry::make('department.name')
                    ->label('Department'),
                Infolists\Components\TextEntry::make('status')
                    ->badge()
                    ->color(fn ($state) => $state->color()),
                Infolists\Components\TextEntry::make('content')
                    ->columnSpanFull(),
                Infolists\Components\RepeatableEntry::make('approvers')
                    ->label('Approval Steps')
                    ->schema([
                        Infolists\Components\TextEntry::make('step_order')
                            ->label('Step'),
                        Infolists\Components\TextEntry::make('approver.name')
                            ->label('Approver'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => $state->color()),
                        Infolists\Components\TextEntry::make('comment')
                            ->visible(fn ($record) => !empty($record->comment)),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}