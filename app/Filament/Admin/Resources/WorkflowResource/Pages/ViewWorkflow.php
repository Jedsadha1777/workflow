<?php

namespace App\Filament\Admin\Resources\WorkflowResource\Pages;

use App\Filament\Admin\Resources\WorkflowResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewWorkflow extends ViewRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manage_versions')
                ->label('Manage Versions')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(fn () => ManageVersions::getUrl(['record' => $this->record])),
        ];
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
                            ->badge(),
                        Infolists\Components\TextEntry::make('latestVersion.status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'DRAFT' => 'gray',
                                'PUBLISHED' => 'success',
                                'ARCHIVED' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Workflow Steps')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('latestVersion.steps')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('step_order')
                                    ->label('Step'),
                                Infolists\Components\TextEntry::make('role.name')
                                    ->label('Role')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('department.name')
                                    ->label('Department')
                                    ->default('Same as document'),
                                Infolists\Components\TextEntry::make('stepType.name')
                                    ->label('Type'),
                                Infolists\Components\TextEntry::make('template_step_order')
                                    ->label('Template Step'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}
