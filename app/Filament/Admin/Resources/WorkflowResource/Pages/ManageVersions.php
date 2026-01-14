<?php

namespace App\Filament\Admin\Resources\WorkflowResource\Pages;

use App\Filament\Admin\Resources\WorkflowResource;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\TemplateDocument;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;

class ManageVersions extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = WorkflowResource::class;
    protected static string $view = 'filament.admin.resources.workflow-resource.pages.manage-versions';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return "Versions: {$this->record->name}";
    }

    public function getSubheading(): ?string
    {
        $template = $this->record->template;
        $dept = $this->record->department;
        $role = $this->record->role;
        
        return "Template: " . ($template?->name ?? 'N/A') 
            . " | Department: " . ($dept?->name ?? 'N/A')
            . " | Role: " . ($role?->name ?? 'N/A');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_version')
                ->label('New Version')
                ->icon('heroicon-o-plus')
                ->action(function () {
                    $version = $this->record->createNewVersion();
                    
                    Notification::make()
                        ->success()
                        ->title('Version created')
                        ->body("Version {$version->version} created successfully.")
                        ->send();

                    return redirect(EditVersion::getUrl([
                        'record' => $this->record->id,
                        'version' => $version->id,
                    ]));
                }),
            Actions\Action::make('back')
                ->label('Back to List')
                ->url(WorkflowResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(WorkflowVersion::where('workflow_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->formatStateUsing(fn ($state) => "v{$state}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('template.name')
                    ->label('Template')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'PUBLISHED' => 'success',
                        'ARCHIVED' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('steps_count')
                    ->counts('steps')
                    ->label('Steps'),
                Tables\Columns\TextColumn::make('documents_count')
                    ->counts('documents')
                    ->label('Documents'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_steps')
                    ->label('Edit Steps')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (WorkflowVersion $record) => EditVersion::getUrl([
                        'record' => $this->record->id,
                        'version' => $record->id,
                    ]))
                    ->visible(fn (WorkflowVersion $record) => $record->canEdit()),
                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Publish Version')
                    ->modalDescription('This will archive other published versions for the same template. Are you sure?')
                    ->action(function (WorkflowVersion $record) {
                        if ($record->publish()) {
                            Notification::make()
                                ->success()
                                ->title('Published')
                                ->body("Version {$record->version} is now published.")
                                ->send();
                        }
                    })
                    ->visible(fn (WorkflowVersion $record) => $record->canPublish()),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (WorkflowVersion $record) {
                        if ($record->archive()) {
                            Notification::make()
                                ->success()
                                ->title('Archived')
                                ->body("Version {$record->version} has been archived.")
                                ->send();
                        }
                    })
                    ->visible(fn (WorkflowVersion $record) => $record->canArchive()),
                Tables\Actions\Action::make('clone')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (WorkflowVersion $record) {
                        $newVersion = $record->cloneToNewVersion();
                        
                        Notification::make()
                            ->success()
                            ->title('Cloned')
                            ->body("Created new version {$newVersion->version} from version {$record->version}.")
                            ->send();

                        return redirect(EditVersion::getUrl([
                            'record' => $this->record->id,
                            'version' => $newVersion->id,
                        ]));
                    }),
                Tables\Actions\Action::make('view_steps')
                    ->label('View Steps')
                    ->icon('heroicon-o-eye')
                    ->url(fn (WorkflowVersion $record) => EditVersion::getUrl([
                        'record' => $this->record->id,
                        'version' => $record->id,
                    ]))
                    ->visible(fn (WorkflowVersion $record) => !$record->canEdit()),
            ])
            ->defaultSort('version', 'desc');
    }
}
