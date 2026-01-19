<?php

namespace App\Filament\Admin\Resources\TemplateDocumentResource\Pages;
use Illuminate\Support\HtmlString;

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
                ])->columns(1),

                Forms\Components\Section::make('Calculation Scripts')->schema([
                    Forms\Components\Textarea::make('calculation_scripts')
                        ->label('Code')
                        ->rows(15)
                        ->placeholder('Write Script to calculate values.

Examples:

1. Basic Calculation
const price = getValue("Sheet1:A1");
setValue("Sheet1:B1", price * 1.07);

2. Multiple Operations
const a = getValue("Sheet1:A1");
const b = getValue("Sheet1:B1");
setValue("Sheet1:C1", a + b);

')
                        ->helperText( new HtmlString( nl2br('

Here is a **clean, corrected English version** with accurate technical wording and neutral tone.
No logic changes—wording only.

---

## 1. Basic Arithmetic

```
// Addition, subtraction, multiplication, division
setValue("Sheet1:D1", getValue("Sheet1:A1") + getValue("Sheet1:B1"));
setValue("Sheet1:E1", getValue("Sheet1:C1") * 1.07); // 7% VAT
```

```
// Exponentiation (right-associative)
setValue("Sheet1:B1", 2 ** 3 ** 2);      // = 512
setValue("Sheet1:C1", (2 ** 3) ** 2);    // = 64
```

---

## 2. Unary Operators

```
const price = parseFloat(getValue("Sheet1:A1")) || 0;
const discount = -price * 0.1;   // unary minus
setValue("Sheet1:B1", -discount); // negate again
```

```
// Multiple unary operators
const negative = --5; // = 5 (double negation)
```

---

## 3. Comparison Operators

```
// Numeric comparison
const age = parseFloat(getValue("Sheet1:A1"));
const isAdult = age >= 18; // true / false
```

```
const score = parseFloat(getValue("Sheet1:B1"));
const passed = score >= 60; // true / false
```

```
// Supported operators: > < >= <= == !=
const isEqual = getValue("Sheet1:A1") == getValue("Sheet1:B1");
const isGreater = getValue("Sheet1:C1") > 100;
```

---

## 4. Ternary Operator (`condition ? valueIfTrue : valueIfFalse`)

```
// Age check
const age = parseFloat(getValue("Sheet1:A1"));
const status = age >= 18 ? "adult" : "minor";
setValue("Sheet1:B1", status);
```

```
// Discount calculation
const total = parseFloat(getValue("Sheet1:Total"));
const discount = total > 1000 ? total * 0.1 : 0;
setValue("Sheet1:Discount", discount);
```

```
// Nested ternary
const score = parseFloat(getValue("Sheet1:Score"));
const grade = score >= 80 ? "A" : score >= 60 ? "B" : "C";
setValue("Sheet1:Grade", grade);
```

```
// Tiered pricing using comparison
const qty = parseFloat(getValue("Sheet1:Qty"));
const price = qty > 10 ? 90 : qty > 5 ? 95 : 100;
setValue("Sheet1:UnitPrice", price);
```

---

## 5. Operator Precedence

```
setValue("Sheet1:A1", 1 + 2 * 3);      // = 7
setValue("Sheet1:A2", 10 - 2 * 3);     // = 4
setValue("Sheet1:A3", 2 ** 3 * 4);     // = 32
setValue("Sheet1:A4", (1 + 2) * 3);    // = 9
```

```
// Ternary has the lowest precedence
const result = 5 > 3 ? 10 + 20 : 5 * 2; // = 30
```

---

## 6. Short-Circuit Evaluation with `||`

```
// If A1 has a value, the next getValue() calls are skipped
const value =
  parseFloat(getValue("Sheet1:A1")) ||
  parseFloat(getValue("Sheet1:B1")) ||
  0;
```

```
// Default value
const qty = parseFloat(getValue("Sheet1:Qty")) || 1;
```

---

## 7. Using Variables

```
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

---

## 8. Combined Usage Examples

```
// Membership discount calculation
const total = parseFloat(getValue("Sheet1:Total")) || 0;
const isMember = getValue("Sheet1:IsMember") == "Yes";
const discount = isMember ? (total > 1000 ? 0.15 : 0.10) : 0;

const final = total * (1 - discount);
setValue("Sheet1:FinalPrice", final);
```

```
// Grade calculation
const score = parseFloat(getValue("Sheet1:Score")) || 0;
const grade =
  score >= 80 ? "A" :
  score >= 70 ? "B" :
  score >= 60 ? "C" :
  score >= 50 ? "D" : "F";

const status = score >= 60 ? "Pass" : "Fail";
setValue("Sheet1:Grade", grade);
setValue("Sheet1:Status", status);
```

---

## 9. Supported Functions

```
parseFloat(value)              // Convert to number
getValue("Sheet:Cell")         // Read value from a cell
setValue("Sheet:Cell", value)  // Write value to a cell
```

---

## 10. Operator Precedence (Lowest → Highest)

1. Ternary: `? :`
2. Logical OR: `||`
3. Comparison: `> < >= <= == !=`
4. Additive: `+ -`
5. Multiplicative: `* /`
6. Exponentiation: `**`
7. Unary: `+ -`
8. Primary: literals, variables, function calls

---

## ❌ Not Supported

* `if / else`
* Loops
* Arrays
* Objects
* Math functions (`Math.floor`, `Math.ceil`, etc.)
* String operations (`split`, `join`, `substring`, etc.)
* Logical AND (`&&`)
* Logical NOT (`!`)
  '))),
                ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [

              Actions\Action::make('back')
                ->label('Back to PDF Layout')
                ->icon('heroicon-o-arrow-left')
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
            'calculation_scripts' => $data['calculation_scripts'],
        ]);

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Settings saved successfully')
            ->send();

        
    }
}