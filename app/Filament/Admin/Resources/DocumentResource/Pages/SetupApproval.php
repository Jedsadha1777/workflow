<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
use App\Enums\DocumentActivityAction;
use App\Filament\App\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\User;
use App\Models\WorkflowVersion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class SetupApproval extends Page
{
    protected static string $resource = DocumentResource::class;
    protected static string $view = 'filament.pages.setup-approval';
    
    public ?array $data = [];
    public ?Document $document = null;
    public ?WorkflowVersion $workflowVersion = null;
    public array $workflowSteps = [];

    public function mount(int|string $record): void
    {
        $this->document = Document::findOrFail($record);
        
        if (!$this->document->template) {
            Notification::make()
                ->danger()
                ->title('No template')
                ->body('This document has no template assigned.')
                ->persistent()
                ->send();
            
            $this->redirect(DocumentResource::getUrl('index'));
            return;
        }

        $this->workflowVersion = $this->findWorkflowVersion();

        if (!$this->workflowVersion) {
            Notification::make()
                ->danger()
                ->title('No workflow configured')
                ->body('There is no published workflow for this template and department. Please contact administrator.')
                ->persistent()
                ->send();
            
            $this->redirect(DocumentResource::getUrl('index'));
            return;
        }

        $this->workflowSteps = $this->prepareWorkflowSteps();
    }

    protected function findWorkflowVersion(): ?WorkflowVersion
    {
        $templateId = $this->document->template_document_id;
        $departmentId = auth()->user()->department_id;

        return WorkflowVersion::whereHas('workflow', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId)
                    ->where('is_active', true);
            })
            ->where('template_id', $templateId)
            ->where('status', 'PUBLISHED')
            ->with(['steps.role', 'steps.department', 'steps.stepType'])
            ->latest('version')
            ->first();
    }

    protected function prepareWorkflowSteps(): array
    {
        $steps = [];

        foreach ($this->workflowVersion->steps as $step) {
            $candidates = $step->findCandidates();

            if ($candidates->count() === 0) {
                $deptName = $step->department ? $step->department->name : 'any department';
                $roleName = $step->role->name ?? 'Unknown Role';
                
                Notification::make()
                    ->danger()
                    ->title('Unable to create document')
                    ->body("There is no {$roleName} in {$deptName}")
                    ->persistent()
                    ->send();
                
                $this->redirect(DocumentResource::getUrl('index'));
                return [];
            }

            $templateWorkflow = $step->getTemplateWorkflow();

            $steps[] = [
                'step_order' => $step->step_order,
                'role' => $step->role,
                'department' => $step->department,
                'step_type' => $step->stepType,
                'signature_cell' => $templateWorkflow?->signature_cell,
                'approved_date_cell' => $templateWorkflow?->approved_date_cell,
                'candidates' => $candidates,
                'is_auto' => $candidates->count() === 1,
                'selected_approver' => $candidates->count() === 1 ? $candidates->first() : null,
            ];
        }

        return $steps;
    }

    public function form(Form $form): Form
    {
        $schema = [];

        foreach ($this->workflowSteps as $step) {
            $key = "approver_{$step['step_order']}";
            $deptName = $step['department'] ? "({$step['department']->name})" : '(Any Dept)';
            $stepTypeName = $step['step_type']->name ?? 'Step';
            $roleName = $step['role']->name ?? 'Unknown';
            $label = "{$stepTypeName} by {$roleName} {$deptName}";

            if ($step['is_auto']) {
                $schema[] = Forms\Components\TextInput::make($key)
                    ->label($label)
                    ->default($step['selected_approver']->name . ' (' . $step['selected_approver']->email . ')')
                    ->disabled()
                    ->dehydrated(false)
                    ->suffixIcon('heroicon-o-check-circle')
                    ->suffixIconColor('success');
            } else {
                $options = $step['candidates']->mapWithKeys(function ($user) {
                    return [$user->id => $user->name . ' (' . $user->email . ')'];
                })->toArray();

                $schema[] = Forms\Components\Select::make($key)
                    ->label($label)
                    ->options($options)
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->placeholder('Select approver');
            }
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Approval Workflow')
                    ->description('Please select approver for each step')
                    ->schema($schema)
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->document->approvers()->delete();

        foreach ($this->workflowSteps as $step) {
            $key = "approver_{$step['step_order']}";
            
            $approverId = $step['is_auto'] 
                ? $step['selected_approver']->id 
                : $data[$key];

            $approver = User::find($approverId);
            
            if (!$approver) {
                Notification::make()
                    ->danger()
                    ->title('Invalid approver selected')
                    ->send();
                return;
            }

            $this->document->approvers()->create([
                'step_order' => $step['step_order'],
                'role_id' => $step['role']->id,
                'department_id' => $step['department']?->id,
                'step_type_id' => $step['step_type']->id,
                'signature_cell' => $step['signature_cell'],
                'approved_date_cell' => $step['approved_date_cell'],
                'approver_id' => $approver->id,
                'approver_name' => $approver->name,
                'approver_email' => $approver->email,
                'status' => \App\Enums\ApprovalStatus::PENDING,
            ]);
        }

        $oldStatus = $this->document->status;
        $this->document->update([
            'status' => DocumentStatus::PENDING,
            'submitted_at' => now(),
            'workflow_version_id' => $this->workflowVersion->id,
        ]);

        DocumentActivityLog::log($this->document, DocumentActivityAction::SUBMITTED, null, [
            'old_status' => $oldStatus->value,
            'new_status' => DocumentStatus::PENDING->value,
            'workflow_version_id' => $this->workflowVersion->id,
        ]);

        Notification::make()
            ->success()
            ->title('Document Submitted')
            ->body('Your document has been submitted for approval')
            ->send();

        $this->redirect(DocumentResource::getUrl('index'));
    }

    public function cancel(): void
    {
        $this->redirect(DocumentResource::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('edit_document')
                ->label('Edit Document')
                ->icon('heroicon-o-pencil')
                ->color('gray')
                ->url(fn () => DocumentResource::getUrl('edit', ['record' => $this->document])),
            
            \Filament\Actions\Action::make('save')
                ->label('Submit for Approval')
                ->action('save')
                ->color('success'),
            
            \Filament\Actions\Action::make('cancel')
                ->label('Cancel')
                ->action('cancel')
                ->color('gray'),
        ];
    }
}
