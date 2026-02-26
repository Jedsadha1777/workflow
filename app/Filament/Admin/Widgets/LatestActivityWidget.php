<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\DocumentResource;
use App\Models\DocumentActivityLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestActivityWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = '1/2';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DocumentActivityLog::query()
                    ->with(['document', 'actor'])
                    ->whereIn('action', ['submitted', 'prepared', 'checked', 'approved', 'rejected', 'recalled'])
                    ->orderBy('performed_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn($state) => $state->color())
                    ->icon(fn($state) => $state->icon()),

                Tables\Columns\TextColumn::make('document_title')
                    ->label('Document')
                    ->searchable()
                    ->url(fn($record) => $record->document 
                        ? DocumentResource::getUrl('view', ['record' => $record->document_id])
                        : null)
                    ->color('primary')
                    ->limit(50),

                Tables\Columns\TextColumn::make('actor_name')
                    ->label('By')
                    ->searchable(),

                Tables\Columns\TextColumn::make('old_status')
                    ->label('From')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? ucfirst($state) : '-')
                    ->visible(fn($record) => !empty($record->old_status)),

                Tables\Columns\TextColumn::make('new_status')
                    ->label('To')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? ucfirst($state) : '-')
                    ->visible(fn($record) => !empty($record->new_status)),

                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Time')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->heading('Latest Activity')
            ->paginated(false);
    }
}
