<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkflowResource\Pages;
use App\Models\Workflow;
use App\Models\Department;
use App\Models\Role;
use App\Models\TemplateDocument;
use App\Models\WorkflowStepType;
use App\Enums\TemplateStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

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
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn ($record) => $record && !$record->canEdit()),
                        Forms\Components\Select::make('template_id')
                            ->label('Template')
                            ->options(
                                TemplateDocument::where('status', TemplateStatus::PUBLISHED)
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn ($t) => [$t->id => "{$t->name} (v{$t->version})"])
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('steps', []))
                            ->disabled(fn ($record) => $record && !$record->canEdit()),
                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->options(Department::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record && !$record->canEdit()),
                        Forms\Components\Select::make('role_id')
                            ->label('Role')
                            ->options(Role::where('is_active', true)->where('is_admin', false)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record && !$record->canEdit()),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                        Forms\Components\TextInput::make('version')
                            ->disabled()
                            ->default(1)
                            ->visible(fn ($record) => $record !== null),
                        Forms\Components\TextInput::make('status')
                            ->disabled()
                            ->default('DRAFT')
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Template Signature/Date Positions')
                    ->description('ตำแหน่งลายเซ็น/วันที่ ที่กำหนดไว้ใน Template')
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
                    ->description('กำหนดว่าแต่ละ step ใครเป็นผู้อนุมัติ')
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
                                    ->label('Approver Role')
                                    ->options(Role::where('is_active', true)->where('is_admin', false)->pluck('name', 'id'))
                                    ->required()
                                    ->native(false),
                                Forms\Components\Select::make('department_id')
                                    ->label('Approver Dept')
                                    ->options(Department::where('is_active', true)->pluck('name', 'id'))
                                    ->placeholder('Same as document')
                                    ->native(false),
                                Forms\Components\Select::make('step_type_id')
                                    ->label('Step Type')
                                    ->options(WorkflowStepType::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->native(false),
                            ])
                            ->columns(4)
                            ->orderColumn('step_order')
                            ->reorderable()
                            ->addActionLabel('Add Step')
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record && !$record->canEdit())
                            ->reorderable(fn ($record) => !$record || $record->canEdit())
                            ->addable(fn ($record) => !$record || $record->canEdit())
                            ->deletable(fn ($record) => !$record || $record->canEdit()),
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn ($record) => $record->canEdit()),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn ($record) => $record->canDelete()),
                ]),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflows::route('/'),
            'create' => Pages\CreateWorkflow::route('/create'),
            'view' => Pages\ViewWorkflow::route('/{record}'),
            'edit' => Pages\EditWorkflow::route('/{record}/edit'),
        ];
    }
}
