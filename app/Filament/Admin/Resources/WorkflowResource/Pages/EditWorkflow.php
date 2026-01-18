<?php

namespace App\Filament\Admin\Resources\WorkflowResource\Pages;

use App\Filament\Admin\Resources\WorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWorkflow extends EditRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->canDelete()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!$this->record->canEdit()) {
            throw new \Exception('Cannot edit this workflow');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }
}
