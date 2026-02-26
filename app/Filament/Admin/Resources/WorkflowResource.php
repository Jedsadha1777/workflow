<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkflowResource\Pages;
use App\Models\Workflow;
use App\Models\Division;
use App\Models\Role;
use App\Models\TemplateDocument;
use App\Enums\TemplateStatus;
use App\Enums\StepType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Model;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Workflows';
    protected static ?string $navigationGroup = 'Workflow';
    protected static ?int $navigationSort = 2;

    public static function canEdit(Model $record): bool
    {
        return $record->canEdit();
    }

    public static function canDelete(Model $record): bool
    {
        return $record->canDelete();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Workflow Information')
                    ->description('กำหนดว่า workflow นี้ใช้กับ Template ไหน, แผนกไหน, ตำแหน่งไหน')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?Model $record) => $record && !$record->canEdit()),
                        Forms\Components\Select::make('template_id')
                            ->label('Template')
                            ->options(
                                TemplateDocument::where('status', TemplateStatus::PUBLISHED)
                                    ->get()
                                    ->mapWithKeys(fn ($t) => [$t->id => "{$t->name} (v{$t->version})"])
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('steps', []))
                            ->disabled(fn (?Model $record) => $record && !$record->canEdit()),
                        Forms\Components\Select::make('division_id')
                            ->label('Division')
                            ->options(Division::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('แผนกที่ใช้ workflow นี้')
                            ->disabled(fn (?Model $record) => $record && !$record->canEdit()),
                        Forms\Components\Select::make('role_id')
                            ->label('Role')
                            ->options(Role::where('is_active', true)->where('is_admin', false)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('ตำแหน่งที่ใช้ workflow นี้ (ผู้สร้างเอกสาร)')
                            ->disabled(fn (?Model $record) => $record && !$record->canEdit()),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Template Signature/Date Positions')
                    ->description('ตำแหน่งลายเซ็นและวันที่ที่กำหนดไว้ใน Template (อ่านอย่างเดียว)')
                    ->schema([
                        Forms\Components\Placeholder::make('template_steps_display')
                            ->label('')
                            ->content(function (Get $get) {
                                $templateId = $get('template_id');
                                if (!$templateId) {
                                    return new HtmlString('<p class="text-gray-500">เลือก Template ก่อน</p>');
                                }

                                $template = TemplateDocument::find($templateId);
                                if (!$template) {
                                    return new HtmlString('<p class="text-red-500">ไม่พบ Template</p>');
                                }

                                $templateWorkflows = $template->workflows()->orderBy('step_order')->get();
                                if ($templateWorkflows->isEmpty()) {
                                    return new HtmlString('<p class="text-amber-600">Template นี้ยังไม่ได้กำหนดตำแหน่งลายเซ็น/วันที่</p>');
                                }

                                $html = '<table class="w-full text-sm border">';
                                $html .= '<thead class="bg-gray-100"><tr>';
                                $html .= '<th class="text-left p-2 border">Step</th>';
                                $html .= '<th class="text-left p-2 border">ชื่อ</th>';
                                $html .= '<th class="text-left p-2 border">Signature Cell</th>';
                                $html .= '<th class="text-left p-2 border">Date Cell</th>';
                                $html .= '</tr></thead><tbody>';

                                foreach ($templateWorkflows as $tw) {
                                    $html .= '<tr>';
                                    $html .= '<td class="p-2 border">' . $tw->step_order . '</td>';
                                    $html .= '<td class="p-2 border">' . ($tw->step_name ?? '-') . '</td>';
                                    $html .= '<td class="p-2 border font-mono text-xs bg-blue-50">' . ($tw->signature_cell ?? '-') . '</td>';
                                    $html .= '<td class="p-2 border font-mono text-xs bg-green-50">' . ($tw->approved_date_cell ?? '-') . '</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table>';
                                return new HtmlString($html);
                            }),
                    ])
                    ->visible(fn (Get $get) => $get('template_id') !== null),

                Forms\Components\Section::make('Workflow Steps')
                    ->description(fn (?Model $record) => $record && !$record->canEdit() 
                        ? 'Version นี้ถูก Publish แล้ว ไม่สามารถแก้ไขได้ ต้อง Clone เป็น Version ใหม่'
                        : 'กำหนดว่าแต่ละ step ใครเป็นผู้อนุมัติ')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('template_step_order')
                                    ->label('Template Step')
                                    ->options(function (Get $get) {
                                        $templateId = $get('../../template_id');
                                        if (!$templateId) {
                                            return [];
                                        }

                                        $template = TemplateDocument::find($templateId);
                                        if (!$template) {
                                            return [];
                                        }

                                        return $template->workflows()
                                            ->orderBy('step_order')
                                            ->get()
                                            ->mapWithKeys(function ($tw) {
                                                $label = "Step {$tw->step_order}";
                                                if ($tw->step_name) {
                                                    $label .= ": {$tw->step_name}";
                                                }
                                                $label .= " ({$tw->signature_cell})";
                                                return [$tw->step_order => $label];
                                            });
                                    })
                                    ->required()
                                    ->native(false),
                                Forms\Components\Select::make('role_id')
                                    ->label('Role')
                                    ->options(Role::where('is_active', true)->where('is_admin', false)->pluck('name', 'id'))
                                    ->required()
                                    ->native(false),
                                Forms\Components\Select::make('division_id')
                                    ->label('Div')
                                    ->options(Division::where('is_active', true)->pluck('name', 'id'))
                                    ->placeholder('แผนกเดียวกับเอกสาร')
                                    ->native(false),
                                Forms\Components\Select::make('step_type')
                                    ->label('Step Type')
                                    ->options(StepType::class)
                                    ->required()
                                    ->native(false)
                                    ->default(StepType::APPROVE),
                            ])
                            ->columns(4)
                            ->orderColumn('step_order')
                            ->reorderable(fn (?Model $record) => !$record || $record->canEdit())
                            ->addable(fn (?Model $record) => !$record || $record->canEdit())
                            ->deletable(fn (?Model $record) => !$record || $record->canEdit())
                            ->addActionLabel('Add Step')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get) => $get('template_id') !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('template.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->template?->isExpired() ? 'Template หมดอายุ' : null)
                    ->color(fn ($record) => $record->template?->isExpired() ? 'danger' : null),
                Tables\Columns\TextColumn::make('division.name')
                    ->label('Division')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->formatStateUsing(fn ($state) => "v{$state}")
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'DRAFT' => 'gray',
                        'PUBLISHED' => 'success',
                        'ARCHIVED' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('steps_count')
                    ->counts('steps')
                    ->label('Steps'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('division_id')
                    ->label('Division')
                    ->options(Division::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Role')
                    ->options(Role::where('is_admin', false)->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'PUBLISHED' => 'Published',
                        'ARCHIVED' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Workflow $record) => $record->canEdit()),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Workflow $record) => $record->canDelete()),
                ]),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflows::route('/'),
            'create' => Pages\CreateWorkflow::route('/create'),
            'edit' => Pages\EditWorkflow::route('/{record}/edit'),
            'view' => Pages\ViewWorkflow::route('/{record}'),
        ];
    }
}