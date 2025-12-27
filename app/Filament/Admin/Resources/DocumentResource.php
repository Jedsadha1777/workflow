<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DocumentStatus;
use App\Filament\Admin\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')->required(),
                
                Forms\Components\Textarea::make('content')
                    ->required()
                    ->rows(5),

                Forms\Components\Select::make('creator_id')
                    ->label('Creator')
                    ->relationship('creator', 'name')
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('status')
                    ->options(DocumentStatus::class)
                    ->required(),

                Forms\Components\Repeater::make('approvers')
                    ->relationship('approvers')
                    ->schema([
                        Forms\Components\Select::make('approver_id')
                            ->label('Approver')
                            ->options(function () {
                                return User::whereIn('role', \App\Enums\UserRole::userRoles())
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable(),
                        
                        Forms\Components\Hidden::make('step_order')
                            ->default(fn ($get, $livewire) => $livewire->data['approvers'] ? count($livewire->data['approvers']) : 1),
                    ])
                    ->orderColumn('step_order')
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creator')
                    ->searchable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state->color()),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(DocumentStatus::class),
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),
                Tables\Filters\SelectFilter::make('template_document_id')
                    ->label('Template')
                    ->relationship('template', 'name'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
            'view' => Pages\ViewDocument::route('/{record}'),
        ];
    }
}