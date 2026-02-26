<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
use App\Enums\DocumentActivityAction;
use App\Filament\Admin\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\User;
use App\Models\Workflow;
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
                ->body('There is no published workflow for this template and division. Please contact administrator.')
                ->persistent()
                ->send();
            
            $this->redirect(DocumentResource::getUrl('index'));
            return;
        }

        $this->workflowSteps = $this->prepareWorkflowSteps();
    }

    protected function findWorkflow(): ?Workflow
    {
        $templateId = $this->document->template_document_id;
        $divisionId = $this->document->division_id ?? auth()->user()->division_id;
        $roleId = $this->document->creator?->role_id ?? auth()->user()->role_id;

        return Workflow::where('division_id', $divisionId)
            ->where('role_id', $roleId)
            ->where('template_id', $templateId)
            ->where('status', 'PUBLISHED')
            ->with(['steps.role', 'steps.division'])
            ->latest('version')
            ->first();
    }

    protected function prepareWorkflowSteps(): array
    {
        $steps = [];

        foreach ($this->workflow->steps as $step) {
            $candidates = $step->findCandidates();

            if ($candidates->count() === 0) {
                $deptName = $step->division ? $step->division->name : 'any division';
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
                'division' => $step->division,
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
            $deptName = $step['division'] ? "({$step['division']->name})" : '(Any Div)';
            $roleName = $step['role']->name ?? 'Unknown';
            $label = "Step {$step['step_order']}: {$roleName} {$deptName}";

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
                'division_id' => $step['division']?->id,
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