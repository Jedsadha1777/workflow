<?php

namespace App\Filament\Admin\Resources\TemplateDocumentResource\Pages;

use App\Filament\Admin\Resources\TemplateDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTemplateDocument extends EditRecord
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function getHeaderActions(): array
    {
       


    


        $actions[] = Actions\ViewAction::make();

        $actions[] = 
            
            Actions\Action::make('next')
                ->label('Next: Edit PDF Layout')
                ->icon('heroicon-o-arrow-right')
                ->color('success')
                ->url(fn() => static::getResource()::getUrl('edit-pdf-layout', ['record' => $this->record]))
                ->visible(fn() => $this->record->content !== null && $this->record->canEdit())
        ;

       
        

        return $actions;
    }

    protected function getPublishWarnings(): string
    {
        $warnings = $this->record->validateForDepartments();

        if (empty($warnings)) {
            return 'Template is ready to publish. All departments have required roles.';
        }

        $html = "⚠️ <strong>Warnings:</strong>\n\n";
        foreach ($warnings as $warning) {
            $html .= "• {$warning}\n";
        }
        $html .= "\nDo you want to publish anyway?";

        return $html;
    }


    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!$this->record->canEdit()) {
            throw new \Exception('Cannot edit this template');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit-pdf-layout', ['record' => $this->record]);
    }
}
