<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Filament\Widgets\ChartWidget;

class DocumentStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Document Status Distribution';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $draft = Document::where('status', DocumentStatus::DRAFT)->count();
        $prepare = Document::where('status', DocumentStatus::PREPARE)->count();
        $pendingChecking = Document::where('status', DocumentStatus::PENDING_CHECKING)->count();
        $checking = Document::where('status', DocumentStatus::CHECKING)->count();
        $pending = Document::where('status', DocumentStatus::PENDING)->count();
        $approved = Document::where('status', DocumentStatus::APPROVED)->count();
        $rejected = Document::where('status', DocumentStatus::REJECTED)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Documents',
                    'data' => [$draft, $prepare, $pendingChecking, $checking, $pending, $approved, $rejected],
                    'backgroundColor' => [
                         '#94a3b8', // Draft
                         '#3b82f6', // Prepare
                         '#f59e0b', // Pending Checking
                         '#8b5cf6', // Checking
                         '#fbbf24', // Pending 
                         '#22c55e', // Approved
                         '#ef4444', // Rejected
                    ],
                ],
            ],
             'labels' => ['Draft', 'Prepare', 'Pending Checking', 'Checking', 'Pending', 'Approved', 'Rejected']
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
