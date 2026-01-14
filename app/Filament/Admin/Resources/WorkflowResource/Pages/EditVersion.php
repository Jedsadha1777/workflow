<?php

namespace App\Filament\Admin\Resources\WorkflowResource\Pages;

use App\Filament\Admin\Resources\WorkflowResource;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\WorkflowStep;
use App\Models\Role;
use App\Models\Department;
use App\Models\WorkflowStepType;
use App\Models\TemplateDocument;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class EditVersion extends Page implements HasForms
{
    use InteractsWithRecord;
    use InteractsWithForms;

    protected static string $resource = WorkflowResource::class;
    protected static string $view = 'filament.admin.resources.workflow-resource.pages.edit-version';

    public WorkflowVersion $version;
    public ?array $data = [];
    public array $templateSteps = [];

    public function mount(int|string $record, int|string $version): void
    {
        $this->record = $this->resolveRecord($record);
        $this->version = WorkflowVersion::findOrFail($version);

        if ($this->version->workflow_id !== $this->record->id) {
            abort(404);
        }

        // PUBLISHED หรือ ARCHIVED → redirect ไปหน้า view only
        if (!$this->version->canEdit()) {
            Notification::make()
                ->warning()
                ->title('Read Only')
                ->body("Version {$this->version->version} is {$this->version->status}. Cannot edit.")
                ->persistent()
                ->send();
        }

        $this->loadTemplateSteps();

        $this->form->fill([
            'steps' => $this->version->steps()
                ->orderBy('step_order')
                ->get()
                ->map(fn ($step) => [
                    'template_step_order' => $step->template_step_order,
                    'role_id' => $step->role_id,
                    'department_id' => $step->department_id,
                    'step_type_id' => $step->step_type_id,
                ])
                ->toArray(),
        ]);
    }

    protected function loadTemplateSteps(): void
    {
        $this->templateSteps = [];
        
        $template = $this->record->template;
        
        if (!$template) {
            return;
        }

        $this->templateSteps = $template->workflows()
            ->orderBy('step_order')
            ->get()
            ->map(fn ($w) => [
                'step_order' => $w->step_order,
                'step_name' => $w->step_name,
                'signature_cell' => $w->signature_cell,
                'approved_date_cell' => $w->approved_date_cell,
            ])
            ->toArray();
    }

    public function getTitle(): string
    {
        $mode = $this->version->canEdit() ? 'Edit' : 'View';
        return "{$mode}: {$this->record->name} v{$this->version->version} ({$this->version->status})";
    }

    public function getSubheading(): ?string
    {
        $template = $this->record->template;
        $dept = $this->record->department;
        $role = $this->record->role;
        
        return "Template: " . ($template?->name ?? 'N/A') 
            . " | Department: " . ($dept?->name ?? 'N/A')
            . " | Role: " . ($role?->name ?? 'N/A');
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\Action::make('back')
                ->label('Back to Versions')
                ->url(ManageVersions::getUrl(['record' => $this->record]))
                ->color('gray'),
        ];

        // DRAFT เท่านั้นถึงจะมีปุ่ม Save
        if ($this->version->canEdit()) {
            $actions[] = Actions\Action::make('save')
                ->label('Save')
                ->action('save')
                ->color('primary');
        }

        // DRAFT + มี steps ถึงจะ Publish ได้
        if ($this->version->canPublish()) {
            $actions[] = Actions\Action::make('publish')
                ->label('Publish')
                ->action('publish')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish Version')
                ->modalDescription('Once published, this version cannot be edited. Are you sure?');
        }

        // PUBLISHED/ARCHIVED → แสดงปุ่ม Clone
        if (!$this->version->canEdit()) {
            $actions[] = Actions\Action::make('clone')
                ->label('Clone to New Version')
                ->action('cloneVersion')
                ->color('info')
                ->icon('heroicon-o-document-duplicate');
        }

        return $actions;
    }

    public function form(Form $form): Form
    {
        $canEdit = $this->version->canEdit();

        return $form
            ->schema([
                Forms\Components\Section::make('Version Status')
                    ->schema([
                        Forms\Components\Placeholder::make('status_info')
                            ->label('')
                            ->content(function () {
                                $status = $this->version->status;
                                $color = match ($status) {
                                    'DRAFT' => 'gray',
                                    'PUBLISHED' => 'green',
                                    'ARCHIVED' => 'yellow',
                                    default => 'gray',
                                };
                                $message = match ($status) {
                                    'DRAFT' => 'This version is a draft. You can edit and publish it.',
                                    'PUBLISHED' => 'This version is published and CANNOT be edited. Clone to create a new version.',
                                    'ARCHIVED' => 'This version is archived and CANNOT be edited. Clone to create a new version.',
                                    default => '',
                                };
                                return new HtmlString("<span class=\"inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-{$color}-100 text-{$color}-800\">{$status}</span> <span class=\"ml-2 text-sm text-gray-600\">{$message}</span>");
                            }),
                    ])
                    ->visible(!$canEdit),

                Forms\Components\Section::make('Template Signature/Date Positions')
                    ->description('ตำแหน่งลายเซ็น/วันที่ จาก Template')
                    ->schema([
                        Forms\Components\Placeholder::make('template_steps_info')
                            ->label('')
                            ->content(function () {
                                if (empty($this->templateSteps)) {
                                    return new HtmlString('<p class="text-amber-600">Template ยังไม่ได้กำหนดตำแหน่ง</p>');
                                }

                                $html = '<table class="w-full text-sm border">';
                                $html .= '<thead class="bg-gray-100"><tr><th class="text-left p-2 border">Step</th><th class="text-left p-2 border">ชื่อ</th><th class="text-left p-2 border">Signature Cell</th><th class="text-left p-2 border">Date Cell</th></tr></thead>';
                                $html .= '<tbody>';
                                
                                foreach ($this->templateSteps as $step) {
                                    $html .= '<tr>';
                                    $html .= '<td class="p-2 border">' . $step['step_order'] . '</td>';
                                    $html .= '<td class="p-2 border">' . ($step['step_name'] ?? '-') . '</td>';
                                    $html .= '<td class="p-2 border font-mono text-xs bg-blue-50">' . ($step['signature_cell'] ?? '-') . '</td>';
                                    $html .= '<td class="p-2 border font-mono text-xs bg-green-50">' . ($step['approved_date_cell'] ?? '-') . '</td>';
                                    $html .= '</tr>';
                                }
                                
                                $html .= '</tbody></table>';
                                
                                return new HtmlString($html);
                            }),
                    ]),

                Forms\Components\Section::make('Workflow Steps')
                    ->description($canEdit ? 'กำหนดผู้อนุมัติแต่ละ step' : '❌ READ ONLY - Version นี้ไม่สามารถแก้ไขได้')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('template_step_order')
                                    ->label('Template Step')
                                    ->options(function () {
                                        $options = [];
                                        foreach ($this->templateSteps as $step) {
                                            $label = "Step {$step['step_order']}";
                                            if ($step['step_name']) {
                                                $label .= ": {$step['step_name']}";
                                            }
                                            $label .= " ({$step['signature_cell']})";
                                            $options[$step['step_order']] = $label;
                                        }
                                        return $options;
                                    })
                                    ->required()
                                    ->native(false)
                                    ->disabled(!$canEdit),
                                Forms\Components\Select::make('role_id')
                                    ->label('Approver Role')
                                    ->options(Role::where('is_active', true)->where('is_admin', false)->pluck('name', 'id'))
                                    ->required()
                                    ->native(false)
                                    ->disabled(!$canEdit),
                                Forms\Components\Select::make('department_id')
                                    ->label('Approver Dept')
                                    ->options(Department::where('is_active', true)->pluck('name', 'id'))
                                    ->placeholder('แผนกเดียวกับเอกสาร')
                                    ->native(false)
                                    ->disabled(!$canEdit),
                                Forms\Components\Select::make('step_type_id')
                                    ->label('Step Type')
                                    ->options(WorkflowStepType::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->native(false)
                                    ->disabled(!$canEdit),
                            ])
                            ->columns(4)
                            ->itemLabel(fn (array $state): ?string => $this->getStepLabel($state))
                            ->addActionLabel('Add Step')
                            ->reorderable($canEdit)
                            ->deletable($canEdit)
                            ->addable($canEdit)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getStepLabel(array $state): ?string
    {
        $stepType = WorkflowStepType::find($state['step_type_id'] ?? null);
        $role = Role::find($state['role_id'] ?? null);
        $dept = Department::find($state['department_id'] ?? null);
        $templateStep = $state['template_step_order'] ?? null;

        if (!$stepType || !$role) {
            return null;
        }

        $label = "{$stepType->name} by {$role->name}";
        if ($dept) {
            $label .= " ({$dept->name})";
        }
        if ($templateStep) {
            $ts = collect($this->templateSteps)->firstWhere('step_order', $templateStep);
            if ($ts && $ts['signature_cell']) {
                $label .= " → {$ts['signature_cell']}";
            }
        }

        return $label;
    }

    public function save(): void
    {
        // Double check - ห้าม save ถ้าไม่ใช่ DRAFT
        if (!$this->version->canEdit()) {
            Notification::make()
                ->danger()
                ->title('Cannot Edit')
                ->body('This version is ' . $this->version->status . ' and cannot be edited.')
                ->send();
            return;
        }

        $data = $this->form->getState();

        $this->version->steps()->delete();

        foreach ($data['steps'] as $index => $stepData) {
            $this->version->steps()->create([
                'step_order' => $index + 1,
                'template_step_order' => $stepData['template_step_order'],
                'role_id' => $stepData['role_id'],
                'department_id' => $stepData['department_id'] ?? null,
                'step_type_id' => $stepData['step_type_id'],
            ]);
        }

        Notification::make()
            ->success()
            ->title('Saved')
            ->body('Workflow version saved.')
            ->send();
    }

    public function publish(): void
    {
        // Double check
        if (!$this->version->canPublish()) {
            Notification::make()
                ->danger()
                ->title('Cannot Publish')
                ->body('This version cannot be published.')
                ->send();
            return;
        }

        $this->save();

        if ($this->version->publish()) {
            Notification::make()
                ->success()
                ->title('Published')
                ->body("Version {$this->version->version} is now PUBLISHED.")
                ->send();

            $this->redirect(ManageVersions::getUrl(['record' => $this->record]));
        }
    }

    public function cloneVersion(): void
    {
        $newVersion = $this->version->cloneToNewVersion();

        Notification::make()
            ->success()
            ->title('Cloned')
            ->body("Created new draft version {$newVersion->version}.")
            ->send();

        $this->redirect(static::getUrl([
            'record' => $this->record,
            'version' => $newVersion,
        ]));
    }
}
