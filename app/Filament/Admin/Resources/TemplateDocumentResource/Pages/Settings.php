<?php

namespace App\Filament\Admin\Resources\TemplateDocumentResource\Pages;

use App\Filament\Admin\Resources\TemplateDocumentResource;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Actions;

class Settings extends Page
{
    use InteractsWithRecord;
    
    protected static string $resource = TemplateDocumentResource::class;

    protected static string $view = 'filament.admin.resources.template-document-resource.pages.settings';

    public ?array $data = [];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        $this->form->fill([
            'name' => $this->record->name,
            'is_active' => $this->record->is_active,
            'calculation_scripts' => $this->record->calculation_scripts,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])->columns(2),

                Forms\Components\Section::make('Calculation Scripts')->schema([
                    Forms\Components\Textarea::make('calculation_scripts')
                        ->label('JavaScript Code')
                        ->rows(15)
                        ->placeholder('Write JavaScript to calculate values.

Examples:

1. Basic Calculation
const price = getValue("Sheet1:A1");
setValue("Sheet1:B1", price * 1.07);

2. Multiple Operations
const a = getValue("Sheet1:A1");
const b = getValue("Sheet1:B1");
setValue("Sheet1:C1", a + b);

3. Conditional Logic
const status = getValue("Sheet1:A1");
const result = status === "approved" || status === "pending";
setValue("Sheet1:B1", result);')
                        ->helperText('
**Supported Features:**
✓ Arithmetic: +, -, *, /, %, (parentheses)
✓ Logical OR: ||
✓ Variables: const/let/var
✓ Comparisons: ===, !==, >, <, >=, <=
✓ Functions: getValue(), setValue(), parseFloat()

**Example:**
```javascript
const price = parseFloat(getValue("Sheet1:A1")) || 0;
const qty = parseFloat(getValue("Sheet1:B1")) || 0;
const discount = parseFloat(getValue("Sheet1:C1")) || 0;

const subtotal = price * qty;
const afterDiscount = subtotal - discount;
const vat = afterDiscount * 0.07;
const total = afterDiscount + vat;

setValue("Sheet1:D1", subtotal);
setValue("Sheet1:E1", afterDiscount);
setValue("Sheet1:F1", vat);
setValue("Sheet1:G1", total);
```

**❌ Not Supported:**
if/else, ternary, loops, arrays, objects, Math functions, string operations
                        '),
                ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [

            
            Actions\Action::make('back')
                ->label('Back to PDF Layout')
                ->url(fn() => static::getResource()::getUrl('edit-pdf-layout', ['record' => $this->record]))
                ->color('gray'),

            Actions\Action::make('next')
                ->label('Next: Setup Workflow')
                ->icon('heroicon-o-arrow-right')
                ->color('success')
                ->action(function () {
                    $this->save();
                    $this->redirect(static::getResource()::getUrl('setup-workflow', ['record' => $this->record]));
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        $this->record->update([
            'name' => $data['name'],
            'is_active' => $data['is_active'],
            'calculation_scripts' => $data['calculation_scripts'],
        ]);

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Settings saved successfully')
            ->send();

        
    }
}