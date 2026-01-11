<?php

namespace App\Filament\Admin\Resources\TemplateDocumentResource\Pages;

use App\Filament\Admin\Resources\TemplateDocumentResource;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Actions;

class EditPdfLayout extends Page
{
    use InteractsWithRecord;
    
    protected static string $resource = TemplateDocumentResource::class;

    protected static string $view = 'filament.admin.resources.template-document-resource.pages.edit-pdf-layout';

    public ?array $data = [];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        $this->form->fill([
            'pdf_layout_html' => $this->record->pdf_layout_html ?? $this->record->content,
            'pdf_orientation' => $this->record->pdf_orientation ?? 'portrait',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('pdf_orientation')
                    ->label('Paper Orientation')
                    ->options([
                        'portrait' => 'Portrait (A4: 210mm x 297mm)',
                        'landscape' => 'Landscape (A4: 297mm x 210mm)',
                    ])
                    ->default('portrait')
                    ->live()
                    ->native(false),

                Forms\Components\Hidden::make('pdf_layout_html'),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [

             Actions\Action::make('back')
                ->label('Back to Edit')
                ->icon('heroicon-o-arrow-left')
                ->url(fn() => static::getResource()::getUrl('edit', ['record' => $this->record]))
                ->color('gray'),


            Actions\Action::make('save')
                ->label('Save PDF Layout')
                ->action('save'),
            
            Actions\Action::make('next')
                ->label('Next: Settings')
                ->icon('heroicon-o-arrow-right')
                ->color('success')
                ->url(fn() => static::getResource()::getUrl('settings', ['record' => $this->record])),
            
           
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        $this->record->update([
            'pdf_layout_html' => $data['pdf_layout_html'],
            'pdf_orientation' => $data['pdf_orientation'],
            'content' => $data['pdf_layout_html'],
        ]);

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('PDF Layout saved successfully')
            ->send();
    }
}