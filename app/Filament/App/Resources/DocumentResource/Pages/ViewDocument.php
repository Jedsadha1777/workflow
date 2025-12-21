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
            $schema[] = Infolists\Components\Section::make('Document Content')
                ->schema([
                    Infolists\Components\TextEntry::make('rendered_html')
                        ->label('')
                        ->state(function ($record) {
                            if (!$record->content) {
                                return '<p class="text-gray-500">No content available</p>';
                            }

                            $content = $record->content;
                            if (is_string($content)) {
                                $content = json_decode($content, true);
                            }

                            if (!$content || !isset($content['sheets'])) {
                                return '<p class="text-red-500">Invalid content format</p>';
                            }

                            $sheets = $content['sheets'];
                            $formData = $record->form_data ?? [];

                            // CSS
                            $html = '<style>
                            .document-viewer {
                                margin-bottom: 20px;
                            }
                            .zoom-controls {
                                display: flex;
                                gap: 8px;
                                align-items: center;
                                margin-bottom: 16px;
                            }
                            .zoom-btn {
                                width: 32px;
                                height: 32px;
                                border: 1px solid #d1d5db;
                                background: white;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 16px;
                                font-weight: bold;
                                color: #374151;
                                transition: all 0.2s;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            }
                            .zoom-btn:hover {
                                background: #f3f4f6;
                                border-color: #9ca3af;
                            }
                            .zoom-level {
                                font-size: 14px;
                                color: #6b7280;
                                min-width: 50px;
                                text-align: center;
                            }
                            .document-content-wrapper {
                                max-height: 600px;
                                overflow: auto;
                                border: 1px solid #e5e7eb;
                                border-radius: 6px;
                                padding: 16px;
                                background: #fafafa;
                            }
                            .document-zoom-wrapper {
                                transform-origin: top left;
                                transition: transform 0.2s;
                            }
                            .document-zoom-wrapper table {
                                border-collapse: collapse;
                                line-height: 1.3;
                            }
                            .document-zoom-wrapper td,
                            .document-zoom-wrapper th {
                                border: 1px solid #d1d5db;
                                padding: 8px;
                                line-height: 1.3;
                            }
                            </style>';
                            
                            $html .= '<div class="document-viewer">';
                            
                            // Zoom controls
                            $html .= '<div class="zoom-controls">';
                            $html .= '<button type="button" class="zoom-btn" onclick="documentZoomOut()" title="Zoom Out">−</button>';
                            $html .= '<span class="zoom-level" id="doc-zoom-level">100%</span>';
                            $html .= '<button type="button" class="zoom-btn" onclick="documentZoomIn()" title="Zoom In">+</button>';
                            $html .= '<button type="button" class="zoom-btn" onclick="documentZoomReset()" title="Reset Zoom" style="font-size: 18px;">⟲</button>';
                            $html .= '</div>';
                            
                            $html .= '<div class="document-content-wrapper">';
                            $html .= '<div id="document-zoom-wrapper" class="document-zoom-wrapper">';
                            $html .= '<div class="space-y-6">';
                            
                            foreach ($sheets as $sheet) {
                                $html .= '<div class="border rounded-lg p-4 bg-white">';
                                $html .= '<h4 class="font-semibold mb-3 text-gray-800">' . htmlspecialchars($sheet['name']) . '</h4>';
                                
                                $sheetHtml = $sheet['html'];
                                $sheetName = $sheet['name'];
                                
                                // แทนค่า form fields
                                if (!empty($formData) && isset($formData[$sheetName])) {
                                    foreach ($formData[$sheetName] as $cell => $value) {
                                        if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                                            $approver = \App\Models\User::find($value['approver_id']);
                                            $signatureHtml = $approver ? 
                                                '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                                                '<div style="font-weight:bold;color:#065f46;">✓ ' . htmlspecialchars($approver->name) . '</div>' .
                                                '<div style="font-size:11px;color:#6b7280;margin-top:4px;">Signed: ' . date('Y-m-d H:i', strtotime($value['signed_at'])) . '</div>' .
                                                '</div>' : 
                                                '<div style="border:2px dashed #d1d5db;padding:10px;text-align:center;color:#9ca3af;">[Signature Pending]</div>';
                                            
                                            $sheetHtml = preg_replace_callback(
                                                '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>(.*?)<\/td>/s',
                                                function($matches) use ($signatureHtml) {
                                                    return '<td' . $matches[1] . '>' . $signatureHtml . '</td>';
                                                },
                                                $sheetHtml
                                            );
                                        } else {
                                            $escapedValue = htmlspecialchars($value);
                                            $sheetHtml = preg_replace_callback(
                                                '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                                                function($matches) use ($escapedValue) {
                                                    return '<td' . $matches[1] . '><div style="padding:4px;"><strong>' . $escapedValue . '</strong></div></td>';
                                                },
                                                $sheetHtml
                                            );
                                        }
                                    }
                                }
                                
                                $html .= $sheetHtml;
                                $html .= '</div>';
                            }
                            
                            $html .= '</div></div></div></div>';
                            
                            // JavaScript
                            $html .= '<script>
                            let currentDocZoom = 1.0;
                            
                            function updateDocumentZoom(newZoom) {
                                currentDocZoom = Math.max(0.25, Math.min(2.0, newZoom));
                                
                                const wrapper = document.getElementById("document-zoom-wrapper");
                                const zoomLevel = document.getElementById("doc-zoom-level");
                                
                                if (wrapper) {
                                    const table = wrapper.querySelector("table");
                                    
                                    if (table) {
                                        // Reset scale
                                        wrapper.style.transform = "scale(1)";
                                        wrapper.style.width = "auto";
                                        wrapper.style.height = "auto";
                                        wrapper.style.marginRight = "";
                                        wrapper.style.marginBottom = "";
                                        void wrapper.offsetWidth; // force reflow
                                        
                                        // คำนวณ width จาก colgroup
                                        const colgroup = table.querySelector("colgroup");
                                        let actualWidth = 0;
                                        if (colgroup) {
                                            const cols = colgroup.querySelectorAll("col");
                                            cols.forEach(function(col) {
                                                const colWidth = col.style.width;
                                                actualWidth += parseFloat(colWidth) || 0;
                                            });
                                        }
                                        
                                        if (actualWidth === 0) {
                                            actualWidth = table.offsetWidth;
                                        }
                                        
                                        const actualHeight = table.offsetHeight;
                                        
                                        wrapper.style.width = actualWidth + "px";
                                        wrapper.style.height = actualHeight + "px";
                                        wrapper.style.transform = "scale(" + currentDocZoom + ")";
                                        
                                        if (currentDocZoom < 1) {
                                            const excessWidth = actualWidth * (1 - currentDocZoom);
                                            const excessHeight = actualHeight * (1 - currentDocZoom);
                                            wrapper.style.marginRight = "-" + excessWidth + "px";
                                            wrapper.style.marginBottom = "-" + excessHeight + "px";
                                        } else {
                                            wrapper.style.marginRight = "0";
                                            wrapper.style.marginBottom = "0";
                                        }
                                    } else {
                                        wrapper.style.transform = "scale(" + currentDocZoom + ")";
                                    }
                                    
                                    if (zoomLevel) {
                                        zoomLevel.textContent = Math.round(currentDocZoom * 100) + "%";
                                    }
                                }
                            }
                            
                            function documentZoomIn() {
                                updateDocumentZoom(currentDocZoom + 0.1);
                            }
                            
                            function documentZoomOut() {
                                updateDocumentZoom(currentDocZoom - 0.1);
                            }
                            
                            function documentZoomReset() {
                                updateDocumentZoom(1.0);
                            }
                            
                            // Initialize
                            setTimeout(function() {
                                updateDocumentZoom(1.0);
                            }, 100);
                            </script>';
                            
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
                    $currentApproval->update([
                        'status' => ApprovalStatus::APPROVED,
                        'approved_at' => now(),
                        'comment' => $data['comment'] ?? null,
                    ]);

                    if ($currentApproval->signature_cell) {
                        $parts = explode(':', $currentApproval->signature_cell);
                        if (count($parts) === 2) {
                            $sheet = $parts[0];
                            $cell = $parts[1];
                            $this->record->setSignature($sheet, $cell, $currentApproval->approver_id);
                            $this->record->save();
                        }
                    }

                    $nextStep = $this->record->current_step + 1;
                    $hasNextApprover = $this->record->approvers()
                        ->where('step_order', $nextStep)
                        ->exists();

                    if ($hasNextApprover) {
                        $this->record->update(['current_step' => $nextStep]);
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