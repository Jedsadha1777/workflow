<?php

namespace App\Filament\Admin\Resources\WorkflowResource\Pages;

use App\Filament\Admin\Resources\WorkflowResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewWorkflow extends ViewRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->record->canDelete()) {
            $actions[] = Actions\DeleteAction::make();
        }

        if ($this->record->canEdit()) {
            $actions[] = Actions\EditAction::make();
        }

        if ($this->record->canPublish()) {
            $actions[] = Actions\Action::make('publish')
                ->label('Publish Workflow')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish Workflow')
                ->modalDescription('Once published, this workflow cannot be edited. Other published versions will be archived. Are you sure?')
                ->action(function () {
                    $this->record->publish();

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Workflow published successfully')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        if ($this->record->canArchive()) {
            $actions[] = Actions\Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->archive();

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Workflow archived')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        if ($this->record->status !== 'DRAFT') {
            $actions[] = Actions\Action::make('new_version')
                ->label('Create New Version')
                ->icon('heroicon-o-plus-circle')
                ->color('info')
                ->action(function () {
                    $newVersion = $this->record->createNewVersion();

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('New version created')
                        ->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $newVersion]));
                });
        }

        return $actions;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Workflow Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('template.name')
                            ->label('Template'),
                        Infolists\Components\TextEntry::make('department.name')
                            ->label('Department'),
                        Infolists\Components\TextEntry::make('role.name')
                            ->label('Role')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('version')
                            ->formatStateUsing(fn ($state) => "v{$state}")
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'DRAFT' => 'gray',
                                'PUBLISHED' => 'success',
                                'ARCHIVED' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('documents_count')
                            ->label('Documents Created')
                            ->state(fn ($record) => $record->documents()->count()),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Workflow Steps')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('steps')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('step_order')
                                    ->label('Step'),
                                Infolists\Components\TextEntry::make('role.name')
                                    ->label('Approver Role')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('department.name')
                                    ->label('Approver Dept')
                                    ->default('Any department'),
                                Infolists\Components\TextEntry::make('template_step_order')
                                    ->label('Template Step'),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn ($record) => $record->steps->count() > 0),

                Infolists\Components\Section::make('Version History')
                    ->schema([
                        Infolists\Components\TextEntry::make('version_history_display')
                            ->label('')
                            ->state(function ($record) {
                                $versions = $record->version_history;

                                $html = '<div class="space-y-2">';
                                foreach ($versions as $ver) {
                                    $isCurrent = $ver->id === $record->id;
                                    $class = $isCurrent ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200';

                                    $statusBg = match ($ver->status) {
                                        'DRAFT' => '#e5e7eb',
                                        'PUBLISHED' => '#d1fae5',
                                        'ARCHIVED' => '#fef3c7',
                                        default => '#e5e7eb',
                                    };

                                    $html .= "<div class='flex items-center justify-between p-3 border rounded {$class}'>";
                                    $html .= "<div class='flex items-center gap-3'>";
                                    $html .= "<span class='font-medium'>v{$ver->version}</span>";
                                    $html .= "<span class='text-xs px-2 py-1 rounded' style='background: {$statusBg}'>{$ver->status}</span>";
                                    $html .= "<span class='text-sm text-gray-500'>{$ver->documents()->count()} docs</span>";
                                    if ($isCurrent) {
                                        $html .= "<span class='text-xs text-blue-600 font-medium'>(Current)</span>";
                                    }
                                    $html .= "</div>";
                                    $html .= "<div class='text-sm text-gray-500'>{$ver->created_at->format('d/m/Y H:i')}</div>";
                                    $html .= "</div>";
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->html(),
                    ])
                    ->visible(fn ($record) => $record->version_history->count() > 1),
            ]);
    }
}
