<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DocumentStatus;
use App\Filament\Admin\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\TemplateDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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
                        $set('form_data', '{}');
                    })
                    ->disabled(fn($record) => $record !== null),

                Forms\Components\TextInput::make('title')
                    ->required(),

                Forms\Components\Select::make('creator_id')
                    ->label('Creator')
                    ->relationship('creator', 'name')
                    ->required()
                    ->searchable()
                    ->default(auth()->id()),

                Forms\Components\Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(auth()->user()->department_id ?? null),

                Forms\Components\Select::make('status')
                    ->options(DocumentStatus::class)
                    ->default(DocumentStatus::DRAFT)
                    ->required(),

                Forms\Components\Placeholder::make('template_form')
                    ->label('Template Form')
                    ->columnSpanFull()
                    ->content(function ($get, $record) {
                        $templateId = $record ? $record->template_document_id : $get('template_document_id');
                        if (!$templateId) {
                            return new HtmlString('<p class="text-sm text-gray-500">Please select a template first</p>');
                        }

                        $template = TemplateDocument::find($templateId);
                        if (!$template || !$template->content) {
                            return new HtmlString('<p class="text-sm text-red-500">Template not found</p>');
                        }

                        $content = is_string($template->content) ? json_decode($template->content, true) : $template->content;
                        if (!$content || !isset($content['sheets']) || empty($content['sheets'])) {
                            return new HtmlString('<p class="text-sm text-red-500">Invalid template content</p>');
                        }

                        $formId = 'doc_form_' . ($record ? $record->id : 'new') . '_' . uniqid();
                        $existingFormData = [];
                        if ($record && $record->form_data) {
                            $existingFormData = is_array($record->form_data) ? $record->form_data : json_decode($record->form_data, true);
                        }

                        $calculationScripts = $template->calculation_scripts ?? '';

                        $html = '<style>
                            .zoom-controls {
                                margin-left: auto;
                                display: flex;
                                gap: 8px;
                                align-items: center;
                            }
                            .zoom-btn {
                                width: 32px;
                                height: 32px;
                                border: 1px solid #d1d5db;
                                background: white;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 16px;
                                font-weight: bold;
                                color: #374151;
                                transition: all 0.2s;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            }
                            .zoom-btn:hover {
                                background: #f3f4f6;
                                border-color: #9ca3af;
                            }
                            .zoom-level {
                                font-size: 14px;
                                color: #6b7280;
                                min-width: 50px;
                                text-align: center;
                            }
                            .preview-zoom-wrapper {
                                transform-origin: top left;
                                transition: transform 0.2s;
                            }
                            [x-cloak] { display: none !important; }
                        </style>';

                        $html .= '<div id="' . $formId . '" wire:ignore x-data="templateFormHandler(\'' . $formId . '\', ' . htmlspecialchars(json_encode($existingFormData), ENT_QUOTES) . ', ' . htmlspecialchars(json_encode($calculationScripts), ENT_QUOTES) . ')" x-cloak>';

                        $sheetIndex = 0;
                        foreach ($content['sheets'] as $sheet) {
                            $sheetId = $formId . '_sheet_' . $sheetIndex;

                            $html .= '<div class="mb-6">';
                            $html .= '<div class="flex items-center justify-between mb-2">';
                            $html .= '<h3 class="text-lg font-semibold">' . htmlspecialchars($sheet['name']) . '</h3>';
                            $html .= '<div class="zoom-controls">';
                            $html .= '<button type="button" class="zoom-btn" @click="zoomOut(\'' . $sheetId . '\')">−</button>';
                            $html .= '<span class="zoom-level" x-text="(zoomLevels[\'' . $sheetId . '\'] || 1) * 100 + \'%\'"></span>';
                            $html .= '<button type="button" class="zoom-btn" @click="zoomIn(\'' . $sheetId . '\')">+</button>';
                            $html .= '<button type="button" class="zoom-btn" @click="resetZoom(\'' . $sheetId . '\')">⟲</button>';
                            $html .= '</div>';
                            $html .= '</div>';

                            $html .= '<div style="overflow: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; background: white;">';
                            $html .= '<div id="' . $sheetId . '" class="preview-zoom-wrapper">';
                            $html .= '<div class="template-content" data-processed="false">' . $sheet['html'] . '</div>';
                            $html .= '</div></div>';

                            $html .= '</div>';

                            $sheetIndex++;
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->visible(fn($get) => $get('template_document_id')),

                Forms\Components\Hidden::make('content')
                    ->dehydrated(false)
                    ->default(''),

                Forms\Components\Hidden::make('form_data')
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
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