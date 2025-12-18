<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Filament\App\Resources\DocumentResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        $user = auth()->user();
        $viewType = $this->record->getViewType($user);

        if ($viewType === 'none') {
            abort(403);
        }

        $schema = [
            Infolists\Components\TextEntry::make('title'),
            Infolists\Components\TextEntry::make('creator.name'),
            Infolists\Components\TextEntry::make('department.name')
                ->label('Department'),
            Infolists\Components\TextEntry::make('status')
                ->badge()
                ->color(fn ($state) => $state->color()),
        ];

        if ($viewType === 'full') {
            // แสดง rendered content ใน Section
            $schema[] = Infolists\Components\Section::make('Document Content')
                ->schema([
                    Infolists\Components\TextEntry::make('rendered_html')
                        ->label('')
                        ->state(function ($record) {
                            if (!$record->content) {
                                return '<p class="text-gray-500">No content available</p>';
                            }

                            // Decode content (ถ้าเป็น string ให้ decode ก่อน)
                            $content = $record->content;
                            if (is_string($content)) {
                                $content = json_decode($content, true);
                            }

                            if (!$content || !isset($content['sheets'])) {
                                return '<p class="text-red-500">Invalid content format</p>';
                            }

                            $sheets = $content['sheets'];
                            $formData = $record->form_data ?? [];

                            $html = '<div class="space-y-6">';
                            
                            // Debug info (แสดงในโหมด dev)
                            if (config('app.debug')) {
                                $html .= '<div class="bg-blue-50 border border-blue-200 rounded p-3 text-sm mb-4">';
                                $html .= '<strong>Debug Info:</strong><br>';
                                $html .= 'Sheets: ' . count($sheets) . '<br>';
                                $html .= 'Form Data: ' . (empty($formData) ? 'Empty' : json_encode($formData)) . '<br>';
                                $html .= '</div>';
                            }
                            
                            foreach ($sheets as $sheet) {
                                $html .= '<div class="border rounded-lg p-4 bg-white">';
                                $html .= '<h4 class="font-semibold mb-3 text-gray-800">' . htmlspecialchars($sheet['name']) . '</h4>';
                                
                                // ดึง HTML จาก sheet
                                $sheetHtml = $sheet['html'];
                                $sheetName = $sheet['name'];
                                
                                // แทนค่า form fields ด้วยข้อมูลที่กรอก (ถ้ามี)
                                if (!empty($formData) && isset($formData[$sheetName])) {
                                    foreach ($formData[$sheetName] as $cell => $value) {
                                        // ถ้าเป็น signature
                                        if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                                            $approver = \App\Models\User::find($value['approver_id']);
                                            $signatureHtml = $approver ? 
                                                '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                                                '<div style="font-weight:bold;color:#065f46;">✓ ' . htmlspecialchars($approver->name) . '</div>' .
                                                '<div style="font-size:11px;color:#6b7280;margin-top:4px;">Signed: ' . date('Y-m-d H:i', strtotime($value['signed_at'])) . '</div>' .
                                                '</div>' : 
                                                '<div style="border:2px dashed #d1d5db;padding:10px;text-align:center;color:#9ca3af;">[Signature Pending]</div>';
                                            
                                            // แทนที่ [signature ...] ใน td
                                            $sheetHtml = preg_replace_callback(
                                                '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>(.*?)<\/td>/s',
                                                function ($matches) use ($signatureHtml) {
                                                    return '<td' . $matches[1] . '>' . $signatureHtml . '</td>';
                                                },
                                                $sheetHtml
                                            );
                                        } else {
                                            // แทนที่ form fields อื่นๆ (text, email, etc.)
                                            $escapedValue = htmlspecialchars($value);
                                            
                                            // แทนที่ใน td ที่มี data-cell
                                            $sheetHtml = preg_replace_callback(
                                                '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>(.*?)<\/td>/s',
                                                function ($matches) use ($escapedValue) {
                                                    // แทนที่เนื้อหาทั้งหมดใน td
                                                    return '<td' . $matches[1] . '><div style="background:#fef3c7;padding:4px 8px;border-radius:4px;"><strong style="color:#92400e;">' . $escapedValue . '</strong></div></td>';
                                                },
                                                $sheetHtml
                                            );
                                        }
                                    }
                                }
                                
                                $html .= '<div class="overflow-x-auto" style="max-width:100%;">';
                                $html .= '<div style="display:inline-block;min-width:100%;">' . $sheetHtml . '</div>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }
                            
                            $html .= '</div>';
                            
                            return $html;
                        })
                        ->html()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull();
            
            $schema[] = Infolists\Components\RepeatableEntry::make('approvers')
                ->label('Approval Steps')
                ->schema([
                    Infolists\Components\TextEntry::make('step_order')
                        ->label('Step'),
                    Infolists\Components\TextEntry::make('approver.name')
                        ->label('Approver'),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->color(fn ($state) => $state->color()),
                    Infolists\Components\TextEntry::make('comment')
                        ->visible(fn ($record) => !empty($record->comment)),
                ])
                ->columnSpanFull();
        } else {
            // status_only - แสดงเฉพาะรายชื่อ approvers
            $schema[] = Infolists\Components\Section::make('Approvers')
                ->schema([
                    Infolists\Components\TextEntry::make('approver_list')
                        ->label('')
                        ->state(fn () => $this->record->approvers->pluck('approver.name')->join(', ')),
                ])
                ->columnSpanFull();
        }

        return $infolist->schema($schema);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $actions = [];

        $currentApproval = $this->record->approvers()
            ->where('approver_id', $user->id)
            ->where('status', ApprovalStatus::PENDING)
            ->where('step_order', $this->record->current_step)
            ->first();

        if ($currentApproval) {
            $actions[] = Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('Approve Document')
                ->modalDescription('Are you sure you want to approve this document?')
                ->modalSubmitActionLabel('Approve')
                ->form([
                    Forms\Components\Textarea::make('comment')
                        ->label('Comment (Optional)')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($currentApproval) {
                    // อัปเดต approval status
                    $currentApproval->update([
                        'status' => ApprovalStatus::APPROVED,
                        'approved_at' => now(),
                        'comment' => $data['comment'] ?? null,
                    ]);

                    // บันทึก signature ถ้ามี signature_cell
                    if ($currentApproval->signature_cell) {
                        $parts = explode(':', $currentApproval->signature_cell);
                        if (count($parts) === 2) {
                            $sheet = $parts[0];
                            $cell = $parts[1];
                            
                            $this->record->setSignature($sheet, $cell, $currentApproval->approver_id);
                            $this->record->save();
                        }
                    }

                    // ตรวจสอบว่ามี approver คนถัดไปหรือไม่
                    $nextStep = $this->record->current_step + 1;
                    $hasNextApprover = $this->record->approvers()
                        ->where('step_order', $nextStep)
                        ->exists();

                    if ($hasNextApprover) {
                        $this->record->update([
                            'current_step' => $nextStep,
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Document Approved')
                            ->body('The document has been approved and sent to the next approver.')
                            ->send();
                    } else {
                        $this->record->update([
                            'status' => DocumentStatus::APPROVED,
                            'approved_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Document Fully Approved')
                            ->body('The document has been approved by all approvers.')
                            ->send();
                    }
                    
                    return redirect($this->getResource()::getUrl('index'));
                });

            $actions[] = Actions\Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Reject Document')
                ->modalDescription('Are you sure you want to reject this document?')
                ->modalSubmitActionLabel('Reject')
                ->form([
                    Forms\Components\Textarea::make('comment')
                        ->label('Reason for Rejection')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($currentApproval) {
                    $currentApproval->update([
                        'status' => ApprovalStatus::REJECTED,
                        'rejected_at' => now(),
                        'comment' => $data['comment'],
                    ]);

                    $this->record->update([
                        'status' => DocumentStatus::REJECTED,
                        'current_step' => 0,
                    ]);
                    
                    Notification::make()
                        ->danger()
                        ->title('Document Rejected')
                        ->body('The document has been rejected and returned to the creator.')
                        ->send();
                    
                    return redirect($this->getResource()::getUrl('index'));
                });
        }
        
        $actions[] = Actions\Action::make('back')
            ->label('Return to List')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        return $actions;
    }
}