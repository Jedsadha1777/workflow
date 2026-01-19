<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
use App\Enums\DocumentActivityAction;
use App\Enums\StepType;
use App\Filament\App\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\User;
use App\Models\Workflow;
use App\Mail\DocumentCheckingRequest;
use App\Mail\DocumentSubmitted;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Mail;

class SetupApproval extends Page
{
    protected static string $resource = DocumentResource::class;
    protected static string $view = 'filament.pages.setup-approval';

    public ?array $data = [];
    public ?Document $document = null;
    public ?Workflow $workflow = null;
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

        $this->workflow = $this->findWorkflow();

        if (!$this->workflow) {
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

        $initialData = [];

        $savedApprovers = $this->document->approvers()->get()->keyBy('step_order');

        foreach ($this->workflowSteps as $step) {
            $key = "approver_{$step['step_order']}";

            if ($step['is_auto']) {
                $initialData[$key] = $step['selected_approver']->name . ' (' . $step['selected_approver']->email . ')';
            } elseif ($savedApprovers->has($step['step_order'])) {
                $initialData[$key] = $savedApprovers->get($step['step_order'])->approver_id;
            }
        }

        $this->form->fill($initialData);
    }

    protected function findWorkflow(): ?Workflow
    {
        $templateId = $this->document->template_document_id;
        $departmentId = auth()->user()->department_id;
        $roleId = auth()->user()->role_id;

        return Workflow::where('department_id', $departmentId)
            ->where('role_id', $roleId)
            ->where('template_id', $templateId)
            ->where('status', 'PUBLISHED')
            ->with(['steps.role', 'steps.department'])
            ->latest('version')
            ->first();
    }

    protected function prepareWorkflowSteps(): array
    {
        $steps = [];

        foreach ($this->workflow->steps as $step) {
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
                'step_type' => $step->step_type,
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
            $stepTypeName = $step['step_type']?->label() ?? 'Step';
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

    public function documentInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->document)
            ->schema([
                Infolists\Components\Section::make('Document Info')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->label('Title'),
                        Infolists\Components\TextEntry::make('template.name')
                            ->label('Template'),
                        Infolists\Components\TextEntry::make('template.version')
                            ->label('Version')
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => $state->color()),
                    ])
                    ->columns(4),
            ]);
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
                'step_type' => $step['step_type']?->value,
                'signature_cell' => $step['signature_cell'],
                'approved_date_cell' => $step['approved_date_cell'],
                'approver_id' => $approver->id,
                'approver_name' => $approver->name,
                'approver_email' => $approver->email,
                'status' => \App\Enums\ApprovalStatus::PENDING,
            ]);
        }

        Notification::make()
            ->success()
            ->title('Approvers Saved')
            ->send();
    }

    public function submit(): void
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
                'step_type' => $step['step_type']?->value,
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
            'workflow_id' => $this->workflow->id,
        ]);

        DocumentActivityLog::log($this->document, DocumentActivityAction::SUBMITTED, null, [
            'old_status' => $oldStatus->value,
            'new_status' => DocumentStatus::PENDING->value,
            'workflow_id' => $this->workflow->id,
            'current_step' => 1,
        ]);

        $firstApprover = $this->document->approvers()->orderBy('step_order')->first();
        if ($firstApprover && $firstApprover->approver && $firstApprover->step_type?->shouldSendEmail()) {
           
            if ($firstApprover->step_type === StepType::CHECKING) {
                Mail::to($firstApprover->approver->email)
                    ->queue(new DocumentCheckingRequest($this->document));
            } else {
                Mail::to($firstApprover->approver->email)
                    ->queue(new DocumentSubmitted($this->document));
            }
        }

        Notification::make()
            ->success()
            ->title('Document Submitted')
            ->body('Document has been submitted for approval')
            ->send();

        $this->redirect(DocumentResource::getUrl('index'));
    }

    public function back(): void
    {
        $this->redirect(DocumentResource::getUrl('edit', ['record' => $this->document]));
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to Edit')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action('back'),

            \Filament\Actions\Action::make('save')
                ->label('Save Approvers')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),

            \Filament\Actions\Action::make('submit')
                ->label('Submit Document')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Submit Document')
                ->modalDescription('Are you sure you want to submit this document for approval?')
                ->modalSubmitActionLabel('Yes, Submit')
                ->action('submit'),
        ];
    }
}