<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\DocumentStatus;
use App\Enums\DocumentActivityAction;
use App\Filament\App\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class SetupApproval extends Page
{
    protected static string $resource = DocumentResource::class;
    protected static string $view = 'filament.pages.setup-approval';

    public ?array $data = [];
    public ?Document $document = null;
    public array $workflowSteps = [];

    public function mount(int|string $record): void
    {
        $this->document = Document::findOrFail($record);

        if (!$this->document->template || !$this->document->template->workflows->count()) {
            Notification::make()
                ->danger()
                ->title('Template has no workflow')
                ->body('This template does not have a workflow configured.')
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

    protected function prepareWorkflowSteps(): array
    {
        $workflows = $this->document->template->workflows()->orderBy('step_order')->get();
        $steps = [];
        $userDepartmentId = auth()->user()->department_id;

        foreach ($workflows as $workflow) {
            if (!$workflow->required_role) {
                Notification::make()
                    ->danger()
                    ->title('Invalid workflow configuration')
                    ->body('Workflow has invalid role configuration. Please contact administrator.')
                    ->persistent()
                    ->send();

                $this->redirect(DocumentResource::getUrl('index'));
                return [];
            }

            $candidates = $workflow->findCandidates($userDepartmentId);

            if ($candidates->count() === 0) {
                $deptText = $workflow->same_department ? 'in your department' : 'in the company';

                Notification::make()
                    ->danger()
                    ->title('Unable to create document')
                    ->body("There is no {$workflow->required_role->label()} {$deptText}")
                    ->persistent()
                    ->send();

                $this->redirect(DocumentResource::getUrl('index'));
                return [];
            }

            $steps[] = [
                'step_order' => $workflow->step_order,
                'required_role' => $workflow->required_role,
                'same_department' => $workflow->same_department,
                'signature_cell' => $workflow->signature_cell,
                'approved_date_cell' => $workflow->approved_date_cell,
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
            $deptText = $step['same_department'] ? '(Same Department)' : '(Company-wide)';

            if ($step['is_auto']) {
                $schema[] = Forms\Components\TextInput::make($key)
                    ->label("Step {$step['step_order']}: {$step['required_role']->label()} {$deptText}")
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
                    ->label("Step {$step['step_order']}: {$step['required_role']->label()} {$deptText}")
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
                'required_role' => $step['required_role']->value,
                'same_department' => $step['same_department'],
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
                'required_role' => $step['required_role']->value,
                'same_department' => $step['same_department'],
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
        ]);

        DocumentActivityLog::log($this->document, DocumentActivityAction::SUBMITTED, null, [
            'old_status' => $oldStatus->value,
            'new_status' => DocumentStatus::PENDING->value,
        ]);

        $firstApprover = $this->document->approvers()->orderBy('step_order')->first();
        if ($firstApprover && $firstApprover->approver) {
            \Mail::to($firstApprover->approver->email)
                ->queue(new \App\Mail\DocumentSubmitted($this->document));
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
