<?php

namespace App\Filament\Admin\Resources;

use App\Enums\TemplateStatus;
use App\Filament\Admin\Resources\TemplateDocumentResource\Pages;
use App\Models\TemplateDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TemplateDocumentResource extends Resource
{
    protected static ?string $model = TemplateDocument::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Template Documents';
    protected static ?string $navigationGroup = 'Management';

    public static function canViewAny(): bool
    {
        return true;
    }
    public static function canEdit(Model $record): bool
    {
        return $record->canEdit();
    }
    public static function canCreate(): bool
    {
        return true;
    }
    public static function canDelete(Model $record): bool
    {
        return $record->canDelete();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    // ->disabled(fn($record) => $record && !$record->canEdit()),
                    ->disabled(fn($record) => $record !== null),
                Forms\Components\TextInput::make('version')
                    ->disabled()
                    ->default(1)
                    ->visible(fn($record) => $record !== null),

                Forms\Components\Select::make('status')
                    ->disabled()
                    ->options(TemplateStatus::class)
                    ->default(TemplateStatus::DRAFT)
                    ->visible(fn($record) => $record !== null),
            ])->columns(3),

            Forms\Components\Section::make('Upload Excel File')->schema([
                Forms\Components\FileUpload::make('excel_file')
                    ->label('Excel File')
                    ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->disk('public')
                    ->directory('templates')
                    ->downloadable()
                    ->required(fn($record) => $record === null)
                    ->disabled(fn($record) => $record && !$record->canEdit()),
            ])->visible(fn($record) => $record === null || $record->canEdit()),

            Forms\Components\Section::make('Template Editor')->schema([
                Forms\Components\Placeholder::make('luckysheet_editor')
                    ->label('')
                    ->content(function ($record) {
                        if (!$record || !$record->excel_file) {
                            return new HtmlString('<div><p class="text-gray-500">Save the form with an Excel file first</p></div>');
                        }

                        $filePath = $record->excel_file;
                        $fileUrl = asset('storage/' . $filePath);
                        $editorJsUrl = asset('js/luckysheet-editor.js?v=1.2');
                        $id = 'luckysheet_' . uniqid();
                        $wrapperId = 'wrapper_' . $id;

                        return new HtmlString(
                            <<<HTML
<div id="{$wrapperId}" wire:ignore>
<style>
#{$id} { scroll-margin: 0 !important; }
#{$id} * { scroll-margin: 0 !important; }
.form-field-btn { display: block; width: 100%; padding: 10px; margin-bottom: 10px; background: #374151; color: white; border: none; border-radius: 6px; cursor: move; text-align: left; font-size: 14px; }
.form-field-btn:hover { background: #4B5563; }
.menu-section { margin-bottom: 20px; }
.menu-title { color: #9CA3AF; font-size: 12px; font-weight: bold; margin-bottom: 10px; text-transform: uppercase; }
.fi-topbar {
  z-index: 1 !important; 
}
.preview-content {
  overflow-x: auto !important;
  overflow-y: auto !important;
  max-width: 100% !important;
}
.preview-content table {
  display: table !important;
  width: 100% !important;
}
</style>
<div class="no-tailwind">
<div id="{$id}" style="margin:10px 0;width:100%;height:600px;border:1px solid #ccc;position:relative;overflow:hidden;"></div>
</div>
<div class="flex gap-2 mt-4">
<button type="button" id="fullscreen_btn_{$id}" class="inline-flex items-center px-4 py-2 rounded-lg text-white" style="background-color:#4B5563;">Fullscreen</button>
<button type="button" id="preview_btn_{$id}" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500">Preview HTML</button>
<button type="button" id="reload_btn_{$id}" style="display:none;" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500">Reload Html</button>
<div id="status_{$id}" class="text-sm text-gray-600 flex items-center"></div>
</div>
<div id="preview_{$id}" style="display:none;" class="mt-6 space-y-4">
<h3 class="text-lg font-semibold">HTML Preview</h3>
<div id="preview_sheets_{$id}"></div>
</div>

<script>
(function() {
    let scriptLoaded = false;
    let retryCount = 0;
    const maxRetries = 20;
    
    function loadEditorScript() {
        if (scriptLoaded) return;
        
        const existingScript = document.querySelector('script[src="{$editorJsUrl}"]');
        if (!existingScript) {
            const script = document.createElement('script');
            script.src = '{$editorJsUrl}';
            script.onload = function() {
                scriptLoaded = true;
                console.log('Luckysheet editor script loaded');
                initEditor();
            };
            script.onerror = function() {
                console.error('Failed to load luckysheet-editor.js');
            };
            document.head.appendChild(script);
        } else {
            scriptLoaded = true;
            initEditor();
        }
    }
    
    function initEditor() {
        if (typeof initLuckysheetEditor === 'function') {
            console.log('Initializing Luckysheet editor...');
            initLuckysheetEditor('{$wrapperId}', {
                containerId: '{$id}',
                statusId: 'status_{$id}',
                fullscreenBtnId: 'fullscreen_btn_{$id}',
                previewBtnId: 'preview_btn_{$id}',
                reloadBtnId: 'reload_btn_{$id}',
                previewId: 'preview_{$id}',
                previewSheetsId: 'preview_sheets_{$id}',
                fileUrl: '{$fileUrl}'
            });
        } else {
            retryCount++;
            if (retryCount < maxRetries) {
                console.log('Waiting for initLuckysheetEditor... (attempt ' + retryCount + ')');
                setTimeout(initEditor, 200);
            } else {
                console.error('Failed to initialize: initLuckysheetEditor not found after ' + maxRetries + ' attempts');
            }
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadEditorScript);
    } else {
        loadEditorScript();
    }
})();
</script>
</div>
HTML
                        );
                    }),

                Forms\Components\Textarea::make('content')
                    ->label('Template Content (JSON)')
                    ->dehydrated(true)
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if (!$record) return;

                        if (is_array($state)) {
                            $component->state(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        } else {
                            $raw = $record->getRawOriginal('content');
                            if ($raw) {
                                $component->state($raw);
                            }
                        }
                    }),
            ])->visible(fn($record) => $record !== null && $record->canEdit()),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->select([
                    'id',
                    'name',
                    'version',
                    'status',
                    'expired_at',
                    'created_at',
                    'updated_at',
                ])
                ->orderBy('name')
                // ->orderBy('version', 'desc')
            )
            ->groups([
                Tables\Grouping\Group::make('name')
                    ->label('Template Name')
                    ->collapsible(),
            ])
            ->defaultGroup('name')
            ->defaultSort('version', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('version')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => $state->color())
                    ->icon(fn($state) => $state->icon())
                    ->sortable()
                    ->description(fn($record) => $record->expired_at ? $record->expired_at->format('d/m/Y H:i') : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            // ->defaultSort('name', 'desc')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\EditAction::make()
                        ->visible(fn($record) => $record->canEdit()),



                    Tables\Actions\DeleteAction::make()
                        ->visible(fn($record) => $record->canDelete()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if (!$record->canDelete()) {
                                    throw new \Exception("Cannot delete {$record->name} v{$record->version} (status: {$record->status->label()})");
                                }
                            }
                        }),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn ($record) => $record->canDelete());
    }



    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemplateDocuments::route('/'),
            'create' => Pages\CreateTemplateDocument::route('/create'),
            'view' => Pages\ViewTemplateDocument::route('/{record}'),
            'edit' => Pages\EditTemplateDocument::route('/{record}/edit'),
            'edit-pdf-layout' => Pages\EditPdfLayout::route('/{record}/edit-pdf-layout'),
            'settings' => Pages\Settings::route('/{record}/settings'),
            'setup-workflow' => Pages\SetupWorkflow::route('/{record}/setup-workflow'),
        ];
    }
}