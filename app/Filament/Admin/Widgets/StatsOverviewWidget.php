<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalDocuments = Document::count();
        $pendingDocuments = Document::where('status', DocumentStatus::PENDING)->count();
        $approvedToday = Document::where('status', DocumentStatus::APPROVED)
            ->whereDate('updated_at', today())
            ->count();
        $rejectedThisMonth = Document::where('status', DocumentStatus::REJECTED)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        return [
            Stat::make('Total Documents', $totalDocuments)
                ->description('All documents in system')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('primary'),

            Stat::make('Pending Approval', $pendingDocuments)
                ->description('Waiting for approval')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Approved Today', $approvedToday)
                ->description('Approved documents today')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Rejected This Month', $rejectedThisMonth)
                ->description('Rejected in ' . now()->format('F'))
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}
