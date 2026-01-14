<?php

namespace App\Filament\Admin\Resources\WorkflowStepTypeResource\Pages;

use App\Filament\Admin\Resources\WorkflowStepTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditWorkflowStepType extends EditRecord
{
    protected static string $resource = WorkflowStepTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->isInUse()) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot delete')
                            ->body('This step type is in use. Set is_active to false instead.')
                            ->persistent()
                            ->send();
                        
                        $action->cancel();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
