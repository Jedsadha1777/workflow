<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkflowStepTypeResource\Pages;
use App\Models\WorkflowStepType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkflowStepTypeResource extends Resource
{
    protected static ?string $model = WorkflowStepType::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Step Types';
    protected static ?string $navigationGroup = 'Workflow';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('รหัส เช่น prepare, checking, approve')
                            ->disabled(fn ($record) => $record && $record->isInUse()),
                        Forms\Components\Toggle::make('send_email')
                            ->label('Send Email Notification')
                            ->helperText('Send email to approver when step is reached')
                            ->default(true),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('send_email')
                    ->label('Email')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('workflow_steps_count')
                    ->counts('workflowSteps')
                    ->label('Usage'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowStepTypes::route('/'),
            'create' => Pages\CreateWorkflowStepType::route('/create'),
            'edit' => Pages\EditWorkflowStepType::route('/{record}/edit'),
        ];
    }
}
