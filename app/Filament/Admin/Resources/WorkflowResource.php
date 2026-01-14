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
        $latestVersion = $record->latestVersion;
        if (!$latestVersion) {
            return true;
        }
        return $latestVersion->status === 'DRAFT';
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
                            ->disabled(fn (?Model $record) => $record && !static::canEdit($record)),
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
                            ->disabled(fn (?Model $record) => $record && !static::canEdit($record)),
                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->options(Department::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('แผนกที่ใช้ workflow นี้')
                            ->disabled(fn (?Model $record) => $record && !static::canEdit($record)),
                        Forms\Components\Select::make('role_id')
                            ->label('Role')
                            ->options(Role::where('is_active', true)->where('is_admin', false)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('ตำแหน่งที่ใช้ workflow นี้ (ผู้สร้างเอกสาร)')
                            ->disabled(fn (?Model $record) => $record && !static::canEdit($record)),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(5),

                Forms\Components\Section::make('Template Signature/Date Positions')
                    ->description('ตำแหน่งลายเซ็นและวันที่ที่กำหนดไว้ใน Template')
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
                                    return new HtmlString('<p class="text-amber-600">Template นี้ยังไม่ได้กำหนดตำแหน่งลายเซ็น/วันที่ กรุณาไปตั้งค่าที่ Template → Setup Workflow ก่อน</p>');
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
                    ->description(fn (?Model $record) => $record && !static::canEdit($record) 
                        ? 'Version นี้ถูก Publish แล้ว ไม่สามารถแก้ไขได้ ต้อง Clone เป็น Version ใหม่'
                        : 'กำหนดว่าแต่ละ step ใครเป็นผู้อนุมัติ')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
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
                                    ->placeholder('แผนกเดียวกับเอกสาร')
                                    ->native(false),
                                Forms\Components\Select::make('step_type_id')
                                    ->label('Step Type')
                                    ->options(WorkflowStepType::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->native(false),
                            ])
                            ->columns(4)
                            ->reorderable(fn (?Model $record) => !$record || static::canEdit($record))
                            ->addable(fn (?Model $record) => !$record || static::canEdit($record))
                            ->deletable(fn (?Model $record) => !$record || static::canEdit($record))
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('latestVersion.version')
                    ->label('Latest')
                    ->formatStateUsing(fn ($state) => $state ? "v{$state}" : '-'),
                Tables\Columns\TextColumn::make('latestVersion.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'DRAFT' => 'gray',
                        'PUBLISHED' => 'success',
                        'ARCHIVED' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->options(Department::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Role')
                    ->options(Role::where('is_admin', false)->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_versions')
                    ->label('Versions')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (Workflow $record) => Pages\ManageVersions::getUrl(['record' => $record])),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Workflow $record) => static::canEdit($record)),
                Tables\Actions\ViewAction::make()
                    ->visible(fn (Workflow $record) => !static::canEdit($record)),
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
            'index' => Pages\ListWorkflows::route('/'),
            'create' => Pages\CreateWorkflow::route('/create'),
            'edit' => Pages\EditWorkflow::route('/{record}/edit'),
            'view' => Pages\ViewWorkflow::route('/{record}'),
            'manage-versions' => Pages\ManageVersions::route('/{record}/versions'),
            'edit-version' => Pages\EditVersion::route('/{record}/versions/{version}/edit'),
        ];
    }
}
