<?php

namespace App\Filament\Admin\Resources;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Filament\Admin\Resources\DocumentResource\Pages;
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

    public static function canCreate(): bool
    {
        return false;  // ห้าม Admin สร้าง document
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('document_info')
                    ->label('')
                    ->content(function ($record) {
                        if (!$record) {
                            return new HtmlString('');
                        }
                        
                        $templateName = $record->template?->name ?? 'N/A';
                        $creatorName = $record->creator?->name ?? 'N/A';
                        $createdAt = $record->created_at?->format('d/m/Y H:i') ?? 'N/A';
                        
                        return new HtmlString('
                            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                                    <div>
                                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">เอกสารต้นฉบับ</div>
                                        <div style="font-weight: 600; color: #111827;">' . htmlspecialchars($templateName) . '</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">สร้างโดย</div>
                                        <div style="font-weight: 600; color: #111827;">' . htmlspecialchars($creatorName) . '</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">สร้างเมื่อ</div>
                                        <div style="font-weight: 600; color: #111827;">' . htmlspecialchars($createdAt) . '</div>
                                    </div>
                                </div>
                            </div>
                        ');
                    })
                    ->columnSpanFull()
                    ->visible(fn($record) => $record !== null),

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

                        if (!empty($calculationScripts)) {
                            $html .= '<script id="calc-script-' . $formId . '">
window.runCalculations_' . $formId . ' = function() {
    try {
        ' . $calculationScripts . '
    } catch (e) {
        console.error("Calculation error:", e);
    }
};
</script>';
                        }

                        $html .= '<div id="' . $formId . '" wire:ignore x-data="templateFormHandler(\''
                            . $formId . '\', '
                            . htmlspecialchars(json_encode($existingFormData), ENT_QUOTES)
                            . ')" x-cloak>';

                        $sheetIndex = 0;
                        foreach ($content['sheets'] as $sheet) {
                            $sheetId = $formId . '_sheet_' . $sheetIndex;

                            $html .= '<div class="bg-white mb-4">';

                            $html .= '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
                            $html .= '<h4 class="font-semibold" style="margin: 0;">' . htmlspecialchars($sheet['name']) . '</h4>';

                            $html .= '<div class="zoom-controls" data-sheet-id="' . $sheetId . '">';
                            $html .= '<button type="button" class="zoom-btn" data-zoom-action="out">−</button>';
                            $html .= '<span class="zoom-level" id="zoom-level-' . $sheetId . '">100%</span>';
                            $html .= '<button type="button" class="zoom-btn" data-zoom-action="in">+</button>';
                            $html .= '<button type="button" class="zoom-btn" data-zoom-action="reset" style="font-size: 18px;">⟲</button>';
                            $html .= '</div>';
                            $html .= '</div>';

                            $html .= '<div style="max-height: auto; overflow: auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; background: #fff;">';
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

                Forms\Components\Section::make('Approval Management')
                    ->schema([
                        Forms\Components\Repeater::make('approvers')
                            ->relationship('approvers')
                            ->schema([
                                Forms\Components\TextInput::make('step_order')
                                    ->label('Step')
                                    ->disabled()
                                    ->default(fn ($get, $livewire) => count($livewire->data['approvers'] ?? []) + 1),
                                
                                Forms\Components\Select::make('approver_id')
                                    ->label('Approver')
                                    ->relationship('approver', 'name')
                                    ->required()
                                    ->searchable(),
                                
                                Forms\Components\Select::make('status')
                                    ->options(ApprovalStatus::class)
                                    ->required()
                                    ->default(ApprovalStatus::PENDING)
                                    ->live(),
                                
                                Forms\Components\DateTimePicker::make('approved_at')
                                    ->label('Date')
                                    ->helperText('Date for approval/rejection based on status'),
                                
                                Forms\Components\Textarea::make('comment')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->reorderable(false)
                            ->addActionLabel('Add Approver')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record !== null)
                    ->collapsible(),
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
                Tables\Columns\TextColumn::make('approval_progress')
                    ->label('Approval Progress')
                    ->html()
                    ->sortable(false)
                    ->getStateUsing(function ($record) {
                        if ($record->status === DocumentStatus::DRAFT) {
                            return '<span class="text-gray-500">Not submitted</span>';
                        }
                        
                        if ($record->status === DocumentStatus::REJECTED) {
                            $rejectedApprover = $record->approvers()
                                ->where('status', ApprovalStatus::REJECTED->value)
                                ->first();
                            if ($rejectedApprover) {
                                return '<span class="text-red-600">Rejected by: ' . 
                                    htmlspecialchars($rejectedApprover->approver->name) . 
                                    '</span>';
                            }
                            return '<span class="text-red-600">Rejected</span>';
                        }
                        
                        $totalApprovers = $record->approvers()->count();
                        $approvedCount = $record->approvers()
                            ->where('status', ApprovalStatus::APPROVED->value)
                            ->count();
                        
                        if ($totalApprovers === 0) {
                            return '<span class="text-gray-500">No approvers</span>';
                        }
                        
                        $currentStep = $record->current_step ?? 1;
                        $currentApprover = $record->approvers()
                            ->where('step_order', $currentStep)
                            ->first();
                        
                        $progress = '<div style="font-size: 12px;">';
                        $progress .= '<div style="margin-bottom: 4px;">';
                        $progress .= '<span style="font-weight: 600;">' . $approvedCount . '/' . $totalApprovers . ' approved</span>';
                        $progress .= '</div>';
                        
                        if ($record->status === DocumentStatus::PENDING && $currentApprover) {
                            $progress .= '<div style="color: #d97706;">';
                            $progress .= '→ Waiting: ' . htmlspecialchars($currentApprover->approver->name);
                            $progress .= '</div>';
                        }
                        
                        $progress .= '</div>';
                        
                        return $progress;
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i'),
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
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'view' => Pages\ViewDocument::route('/{record}'),
        ];
    }
}