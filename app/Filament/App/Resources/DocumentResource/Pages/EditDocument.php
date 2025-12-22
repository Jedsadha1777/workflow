<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Filament\App\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->canBeEditedBy(auth()->user())),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Parse form_data
        if (isset($data['form_data']) && is_string($data['form_data'])) {
            $parsed = json_decode($data['form_data'], true);
            $data['form_data'] = $parsed ?: [];
        }
        
        // ไม่แตะ content (ใช้จาก database เดิม)
        unset($data['content']);
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to setup approval page
        return static::getResource()::getUrl('setup-approval', ['record' => $this->record]);
    }
}