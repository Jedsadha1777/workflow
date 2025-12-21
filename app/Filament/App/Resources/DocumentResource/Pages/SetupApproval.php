<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Filament\App\Resources\DocumentResource;
use App\Models\Document;
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

    public function mount(int|string $record): void
    {
        $this->document = Document::findOrFail($record);
        
        $this->form->fill([
            'approvers' => $this->document->approvers()
                ->orderBy('step_order')
                ->get()
                ->map(fn ($approver) => [
                    'approver_id' => $approver->approver_id,
                    'signature_cell' => $approver->signature_cell,
                    'approved_date_cell' => $approver->approved_date_cell,
                ])
                ->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Repeater::make('approvers')
                    ->label('Approval Workflow')
                    ->schema([
                        Forms\Components\Select::make('approver_id')
                            ->label('Approver')
                            ->options(function () {
                                return User::whereIn('role', \App\Enums\UserRole::userRoles())
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('signature_cell')
                            ->label('Signature Cell')
                            ->placeholder('e.g., Sheet1:A5')
                            ->helperText('Cell where signature will be stamped')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('approved_date_cell')
                            ->label('Approved Date Cell')
                            ->placeholder('e.g., Sheet1:B5')
                            ->helperText('Cell where approval date will be stamped')
                            ->columnSpan(1),
                    ])
                    ->columns(4)
                    ->reorderable()
                    ->orderColumn('step_order')
                    ->addActionLabel('Add Approver')
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function documentInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->document)
            ->schema([
                Infolists\Components\Section::make('Document Preview')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->label('Title'),
                        Infolists\Components\TextEntry::make('template.name')
                            ->label('Template'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(3),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        $this->document->approvers()->delete();
        
        $stepOrder = 1;
        foreach ($data['approvers'] ?? [] as $approver) {
            if (isset($approver['approver_id']) && !empty($approver['approver_id'])) {
                $this->document->approvers()->create([
                    'approver_id' => $approver['approver_id'],
                    'step_order' => $stepOrder,
                    'signature_cell' => $approver['signature_cell'] ?? null,
                    'approved_date_cell' => $approver['approved_date_cell'] ?? null,
                ]);
                $stepOrder++;
            }
        }
        
        Notification::make()
            ->success()
            ->title('Approval Workflow Saved')
            ->body('You can now submit the document for approval')
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
            \Filament\Actions\Action::make('save')
                ->label('Save Approval Workflow')
                ->action('save')
                ->color('success'),
            \Filament\Actions\Action::make('cancel')
                ->label('Cancel')
                ->action('cancel')
                ->color('gray'),
        ];
    }
}