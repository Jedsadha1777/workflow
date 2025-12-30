<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Enums\ApprovalStatus;
use App\Filament\Admin\Resources\DocumentResource;
use Filament\Actions;
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
                Infolists\Components\TextEntry::make('submitted_at')
                    ->label('Submitted At')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn($record) => $record->submitted_at !== null),
                Infolists\Components\TextEntry::make('approved_at')
                    ->label('Approved At')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn($record) => $record->approved_at !== null),
                Infolists\Components\ViewEntry::make('content')
                    ->label('')
                    ->view('filament.pages.view-document-content')
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
                        Infolists\Components\TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->approved_at !== null),
                        Infolists\Components\TextEntry::make('rejected_at')
                            ->label('Rejected At')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->rejected_at !== null),
                        Infolists\Components\TextEntry::make('comment')
                            ->visible(fn ($record) => !empty($record->comment)),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Actions\Action::make('export_pdf')
            ->label('Export PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->url(fn() => route('documents.export-pdf', ['document' => $this->record]))
            ->openUrlInNewTab();

        $actions[] = Actions\Action::make('export_excel')
            ->label('Export Excel')
            ->icon('heroicon-o-table-cells')
            ->color('success')
            ->url(fn() => route('documents.export-excel', ['document' => $this->record]))
            ->openUrlInNewTab();

        $actions[] = Actions\Action::make('back')
            ->label('Return to List')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        return $actions;
    }
}