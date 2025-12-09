<?php

namespace App\Filament\App\Resources;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Filament\App\Resources\DocumentResource\Pages;
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
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->disabled(fn($record) => $record && !$record->canBeEditedBy(auth()->user())),

                Forms\Components\Textarea::make('content')
                    ->required()
                    ->rows(5)
                    ->disabled(fn($record) => $record && !$record->canBeEditedBy(auth()->user())),

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
                            ->searchable()
                            ->disabled(function ($record, $get) {
                                if (!$record) {
                                    return false;
                                }

                                $user = auth()->user();

                                if ($user->isAdmin()) {
                                    return false;
                                }

                                return !$record->canBeChangedBy($user);
                            }),

                        Forms\Components\Hidden::make('step_order')
                            ->default(fn($get, $livewire) => $livewire->data['approvers'] ? count($livewire->data['approvers']) : 1),
                    ])
                    ->orderColumn('step_order')
                    ->defaultItems(0)
                    ->disabled(fn($record) => $record && $record->status !== DocumentStatus::DRAFT && !auth()->user()->isAdmin())
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('creator_id')
                    ->default(auth()->id()),

                Forms\Components\Hidden::make('department_id')
                    ->default(auth()->user()->department_id),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creator'),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => $state->color()),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(DocumentStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->canBeEditedBy(auth()->user())),
                Tables\Actions\Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Document $record) {
                        $record->update([
                            'status' => DocumentStatus::PENDING,
                            'submitted_at' => now(),
                            'current_step' => 1,
                        ]);
                    })
                    ->visible(fn($record) => $record->status === DocumentStatus::DRAFT && $record->creator_id === auth()->id()),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                if ($user->isAdmin()) {
                    return $query;
                }

                return $query->where(function ($q) use ($user) {
                    $q->where('creator_id', $user->id)
                        ->orWhere(function ($q) use ($user) {
                            $q->where('department_id', $user->department_id)
                                ->where('status', DocumentStatus::DRAFT);
                        })
                        ->orWhereHas('approvers', function ($q) use ($user) {
                            $q->where('approver_id', $user->id)
                                ->where('status', ApprovalStatus::PENDING)
                                ->whereColumn('step_order', 'documents.current_step');
                        });
                });
            });
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
