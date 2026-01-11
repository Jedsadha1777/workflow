<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class ViewLogs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DocumentResource::class;
    protected static string $view = 'filament.admin.resources.document-resource.pages.view-logs';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\DocumentActivityLog::query()
                    ->with(['document', 'actor'])
                    ->orderBy('performed_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn($state) => $state->color())
                    ->icon(fn($state) => $state->icon())
                    ->label('Action'),
                   
                Tables\Columns\TextColumn::make('actor_name')
                    ->label('Performed By')
                    ->searchable(),
                  
                Tables\Columns\TextColumn::make('actor_role')
                    ->label('Role')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('document_title')
                    ->label('Document')
                    ->searchable()
                    ->url(fn($record) => $record->document 
                        ? DocumentResource::getUrl('view', ['record' => $record->document_id])
                        : null)
                    ->color('primary'),
                  
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
                Tables\Columns\TextColumn::make('comment')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->comment)
                    ->wrap()
                    ->visible(fn($record) => !empty($record->comment)),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Date & Time')
                    ->dateTime('d/m/Y H:i:s'),
                   
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'created' => 'Created',
                        'edited' => 'Edited',
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'recalled' => 'Recalled',
                        'deleted'   => 'Deleted',
                    ]),
                Tables\Filters\SelectFilter::make('actor_id')
                    ->label('User')
                    ->relationship('actor', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('performed_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q) => $q->whereDate('performed_at', '>=', $data['from']))
                            ->when($data['to'], fn($q) => $q->whereDate('performed_at', '<=', $data['to']));
                    }),
            ])
            ->defaultSort('performed_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to Documents')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn() => DocumentResource::getUrl('index')),
        ];
    }

    public function getTitle(): string
    {

        return 'All Document Activity Logs';
    }
}
