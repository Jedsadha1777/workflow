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
        $pending = Document::where('status', DocumentStatus::PENDING)->count();
        $approved = Document::where('status', DocumentStatus::APPROVED)->count();
        $rejected = Document::where('status', DocumentStatus::REJECTED)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Documents',
                    'data' => [$draft, $pending, $approved, $rejected],
                    'backgroundColor' => [
                        '#94a3b8',
                        '#fbbf24',
                        '#22c55e',
                        '#ef4444',
                    ],
                ],
            ],
            'labels' => ['Draft', 'Pending', 'Approved', 'Rejected'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
