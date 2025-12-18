<?php

namespace App\Filament\App\Resources;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Filament\App\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\TemplateDocument;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('template_document_id')
                    ->label('Template')
                    ->relationship('template', 'name', fn($query) => $query->where('is_active', true))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        $set('form_data', []);
                        
                        // เก็บ template content ลง content field
                        if ($state) {
                            $template = \App\Models\TemplateDocument::find($state);
                            if ($template && $template->content) {
                                $set('content', json_encode($template->content));
                            }
                        }
                    })
                    ->disabled(fn($record) => $record !== null),

                Forms\Components\TextInput::make('title')
                    ->required()
                    ->disabled(fn($record) => $record && !$record->canBeEditedBy(auth()->user())),

                Forms\Components\Placeholder::make('template_form')
                    ->label('Template Form')
                    ->columnSpanFull()
                    ->content(function ($get, $record) {
                        $templateId = $get('template_document_id');
                        if (!$templateId) {
                            return new HtmlString('<p class="text-sm text-gray-500">Please select a template first</p>');
                        }

                        $template = \App\Models\TemplateDocument::find($templateId);
                        if (!$template) {
                            return new HtmlString('<p class="text-sm text-red-500">Template not found</p>');
                        }

                        if (!$template->content) {
                            return new HtmlString('<p class="text-sm text-red-500">Content is null</p>');
                        }

                        $content = $template->content;
                        if (is_string($content)) {
                            $content = json_decode($content, true);
                            if (!$content) {
                                return new HtmlString('<p class="text-sm text-red-500">Invalid JSON in content field</p>');
                            }
                        }

                        if (!isset($content['sheets'])) {
                            return new HtmlString('<p class="text-sm text-red-500">No sheets key</p>');
                        }

                        $sheets = $content['sheets'];
                        if (empty($sheets)) {
                            return new HtmlString('<p class="text-sm text-red-500">Sheets is empty</p>');
                        }

                        $formId = 'doc_form_' . ($record ? $record->id : 'new');
                        $html = '<div id="' . $formId . '" class="space-y-6" wire:ignore>';

                        foreach ($sheets as $sheet) {
                            $html .= '<div class="border rounded-lg p-4 bg-white">';
                            $html .= '<h4 class="font-semibold mb-3">' . htmlspecialchars($sheet['name']) . '</h4>';
                            $html .= '<div class="overflow-x-auto"><div class="template-content" data-processed="false">' . $sheet['html'] . '</div></div>';
                            $html .= '</div>';
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->visible(fn($get) => $get('template_document_id')),

                Forms\Components\Hidden::make('content')
                    ->dehydrated(true)
                    ->default(''),

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

                        Forms\Components\TextInput::make('signature_cell')
                            ->label('Signature Cell (e.g., Sheet1:A5)')
                            ->placeholder('Sheet1:A5')
                            ->helperText('Format: SheetName:CellReference'),

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

                Forms\Components\Hidden::make('form_data')
                    ->extraAttributes(['data-form-data' => 'true'])
                    ->dehydrated(true)
                    ->default('{}'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('template.name')
                    ->label('Template'),
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
                Tables\Filters\SelectFilter::make('template_document_id')
                    ->label('Template')
                    ->relationship('template', 'name'),
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