<?php

namespace App\Filament\Admin\Resources\TemplateDocumentResource\Pages;

use App\Enums\TemplateStatus;
use App\Filament\Admin\Resources\TemplateDocumentResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTemplateDocument extends ViewRecord
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->record->canEdit()) {
            $actions[] = Actions\EditAction::make();
        }

        if ($this->record->canPublish()) {
            $actions[] = Actions\Action::make('publish')
                ->label('Publish Template')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish Template')
                ->modalDescription(fn() => $this->getPublishWarnings())
                ->action(fn() => $this->record->publish())
                ->successNotification(
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Template published successfully')
                );
        }

        if ($this->record->status === TemplateStatus::PUBLISHED) {
            $actions[] = Actions\Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn() => $this->record->archive())
                ->successNotification(
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Template archived')
                );
        }

        if ($this->record->status !== TemplateStatus::DRAFT) {
            $actions[] = Actions\Action::make('new_version')
                ->label('Create New Version')
                ->icon('heroicon-o-plus-circle')
                ->color('info')
                ->action(function () {
                    $newVersion = $this->record->createNewVersion();
                    return redirect()->route('filament.admin.resources.template-documents.edit', ['record' => $newVersion]);
                })
                ->successNotification(
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('New version created')
                );
        }

        if ($this->record->canDelete()) {
            $actions[] = Actions\DeleteAction::make();
        }

        return $actions;
    }

    protected function getPublishWarnings(): string
    {
        $warnings = $this->record->validateForDepartments();
        
        if (empty($warnings)) {
            return 'Template is ready to publish. All departments have required roles.';
        }

        $html = "⚠️ <strong>Warnings:</strong>\n\n";
        foreach ($warnings as $warning) {
            $html .= "• {$warning}\n";
        }
        $html .= "\nDo you want to publish anyway?";

        return $html;
    }
 

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Template Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('version')
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => $state->color())
                            ->icon(fn($state) => $state->icon()),
                        Infolists\Components\TextEntry::make('documents_count')
                            ->label('Documents Created')
                            ->state(fn($record) => $record->documents()->count()),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Workflow')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('workflows')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('step_order')
                                    ->label('Step'),
                                Infolists\Components\TextEntry::make('required_role')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\IconEntry::make('same_department')
                                    ->label('Same Dept')
                                    ->boolean(),
                                Infolists\Components\TextEntry::make('signature_cell')
                                    ->label('Signature'),
                                Infolists\Components\TextEntry::make('approved_date_cell')
                                    ->label('Date'),
                            ])
                            ->columns(5),
                    ])
                    ->visible(fn($record) => $record->workflows->count() > 0),

                Infolists\Components\Section::make('Version History')
                    ->schema([
                        Infolists\Components\TextEntry::make('version_history')
                            ->label('')
                            ->state(function ($record) {
                                $versions = \App\Models\TemplateDocument::where('name', $record->name)
                                    ->orderBy('version', 'desc')
                                    ->get();

                                $html = '<div class="space-y-2">';
                                foreach ($versions as $ver) {
                                    $isCurrent = $ver->id === $record->id;
                                    $class = $isCurrent ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200';
                                    
                                    $html .= "<div class='flex items-center justify-between p-3 border rounded {$class}'>";
                                    $html .= "<div class='flex items-center gap-3'>";
                                    $html .= "<span class='font-medium'>v{$ver->version}</span>";
                                    $html .= "<span class='text-xs px-2 py-1 rounded' style='background: " . match($ver->status) {
                                        \App\Enums\TemplateStatus::DRAFT => '#e5e7eb',
                                        \App\Enums\TemplateStatus::PUBLISHED => '#d1fae5',
                                        \App\Enums\TemplateStatus::ARCHIVED => '#fef3c7',
                                    } . "'>{$ver->status->label()}</span>";
                                    $html .= "<span class='text-sm text-gray-500'>{$ver->documents()->count()} docs</span>";
                                    if ($isCurrent) {
                                        $html .= "<span class='text-xs text-blue-600 font-medium'>(Current)</span>";
                                    }
                                    $html .= "</div>";
                                    $html .= "<div class='text-sm text-gray-500'>{$ver->created_at->format('d/m/Y H:i')}</div>";
                                    $html .= "</div>";
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->html(),
                    ])
                    ->visible(fn($record) => \App\Models\TemplateDocument::where('name', $record->name)->count() > 1),
            ]);
    }
}
