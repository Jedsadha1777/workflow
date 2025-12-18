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
                    ->afterStateUpdated(fn($state, Forms\Set $set) => $set('form_data', []))
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

                        $formId = 'doc_form_' . uniqid();
                        $html = '<div id="' . $formId . '" class="space-y-6">';

                        foreach ($sheets as $sheet) {
                            $html .= '<div class="border rounded-lg p-4 bg-white">';
                            $html .= '<h4 class="font-semibold mb-3">' . htmlspecialchars($sheet['name']) . '</h4>';
                            $html .= '<div class="overflow-x-auto"><div class="template-content">' . $sheet['html'] . '</div></div>';
                            $html .= '</div>';
                        }

                        $html .= '</div>';

                        $html .= "<script>
(function() {
    console.log('Script started');
    
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector('script[src=\"' + src + '\"]')) {
                console.log('Script already loaded:', src);
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    loadScript('" . asset('js/form-field-renderer.js') . "').then(() => {
        console.log('form-field-renderer.js loaded');
        
        setTimeout(() => {
            const form = document.getElementById('{$formId}');
            console.log('Form element:', form);
            
            if (!form) {
                console.error('Form not found');
                return;
            }
            
            if (typeof renderFormFields !== 'function') {
                console.error('renderFormFields is not a function');
                return;
            }

            form.querySelectorAll('.template-content').forEach(content => {
                console.log('Processing content, original length:', content.innerHTML.length);
                content.innerHTML = renderFormFields(content.innerHTML);
                console.log('After renderFormFields, length:', content.innerHTML.length);
            });

            form.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('change', () => {
                    const data = {};
                    
                    form.querySelectorAll('input, select, textarea').forEach(f => {
                        const cellRef = f.closest('td')?.getAttribute('data-cell');
                        if (cellRef && f.value) {
                            const [sheet, cell] = cellRef.split(':');
                            if (!data[sheet]) data[sheet] = {};
                            data[sheet][cell] = f.type === 'checkbox' ? f.checked : f.value;
                        }
                    });
                    
                    console.log('Form data:', data);
                    
                    const textarea = document.querySelector('textarea[data-form-data=\"true\"]');
                    if (textarea) {
                        textarea.value = JSON.stringify(data);
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            });
        }, 200);
    }).catch(err => {
        console.error('Failed to load form-field-renderer.js:', err);
    });
})();
</script>";

                        return new HtmlString($html);
                    })
                    ->visible(fn($get) => $get('template_document_id')),

                Forms\Components\Hidden::make('content')
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

                Forms\Components\Textarea::make('form_data')
                    ->extraAttributes(['data-form-data' => 'true'])
                    ->hidden()
                    ->dehydrated(true)
                    ->default([]),
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
