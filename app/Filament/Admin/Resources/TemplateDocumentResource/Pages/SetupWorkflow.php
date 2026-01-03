<?php

namespace App\Filament\Admin\Resources\TemplateDocumentResource\Pages;

use App\Enums\UserRole;
use App\Enums\TemplateStatus;
use App\Filament\Admin\Resources\TemplateDocumentResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Actions;

class SetupWorkflow extends Page
{
    use InteractsWithRecord;
    
    protected static string $resource = TemplateDocumentResource::class;
    protected static string $view = 'filament.admin.resources.template-document-resource.pages.setup-workflow';

    public ?array $data = [];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        if (!$this->record->canEdit()) {
            abort(403, 'Cannot edit this template');
        }

        $this->form->fill([
            'workflows' => $this->record->workflows()
                ->orderBy('step_order')
                ->get()
                ->map(fn ($workflow) => [
                    'required_role' => $workflow->required_role,
                    'same_department' => $workflow->same_department,
                    'signature_cell' => $workflow->signature_cell,
                    'approved_date_cell' => $workflow->approved_date_cell,
                ])
                ->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        $signatureFields = $this->getAvailableFields('signature');
        $dateFields = $this->getAvailableFields('date');

        return $form
            ->schema([
                Forms\Components\Section::make('Workflow Steps')
                    ->description('Define approval workflow for this template')
                    ->schema([
                        Forms\Components\Repeater::make('workflows')
                            ->schema([
                                Forms\Components\Select::make('required_role')
                                    ->label('Required Role')
                                   ->options(collect(UserRole::cases())->mapWithKeys(
                                        fn ($role) => [$role->value => $role->label()]
                                    ))
                                    ->required()
                                    ->native(false),                                    

                                Forms\Components\Checkbox::make('same_department')
                                    ->label('Same Department')
                                     ->helperText('Approver must be in the same department as document creator'),

                                Forms\Components\Select::make('signature_cell')
                                    ->label('Signature Cell')
                                    ->options($signatureFields)
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Select signature field from template'),

                                Forms\Components\Select::make('approved_date_cell')
                                    ->label('Approved Date Cell')
                                    ->options($dateFields)
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Select date field from template'),
                            ])
                            ->columns(4)
                            ->reorderable()
                            ->addActionLabel('Add Step')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getAvailableFields(string $type): array
    {
        if (!$this->record->content) {
            return [];
        }

        $fields = $this->record->getFieldsByType($type);

        $options = [];
        foreach ($fields as $field) {
            $cellRef = $field['sheet'] . ':' . $field['cell'];
            $label = $field['name'] . ' (' . $cellRef . ')';
            $options[$cellRef] = $label;
        }

        return $options;
    }

    protected function getHeaderActions(): array
     {
         return [
             Actions\Action::make('back')
                 ->label('Back to Settings')
                 ->icon('heroicon-o-arrow-left')
                 ->color('gray')
                 ->url(fn() => static::getResource()::getUrl('settings', ['record' => $this->record])),
            
            Actions\Action::make('back_to_list')
                ->label('Back to List')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn() => static::getResource()::getUrl('index')),

            Actions\Action::make('publish')
                ->label('Publish Template')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish Template')
                ->modalDescription('Are you sure you want to publish this template?')
                ->action(function () {
                    $this->save();
                    $this->record->publish();
                    
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Template published successfully')
                        ->send();
                    
                    $this->redirect(static::getResource()::getUrl('index'));
                })
                ->visible(fn() => $this->record->status === TemplateStatus::DRAFT),
         ];
     }


    

    public function save(): void
    {
        $data = $this->form->getState();

        $this->record->workflows()->delete();

        if (!empty($data['workflows'])) {
            foreach ($data['workflows'] as $index => $workflowData) {
                $this->record->workflows()->create([
                    'step_order' => $index + 1,
                    'required_role' => $workflowData['required_role'],
                    'same_department' => $workflowData['same_department'] ?? false,
                    'signature_cell' => $workflowData['signature_cell'] ?? null,
                    'approved_date_cell' => $workflowData['approved_date_cell'] ?? null,
                ]);
            }
        }

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Workflow saved successfully')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Workflow')
                ->action('save')
                ->color('primary'),
        ];
    }
}