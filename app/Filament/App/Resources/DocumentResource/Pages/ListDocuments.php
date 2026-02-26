<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Filament\App\Resources\DocumentResource;
use App\Models\Document;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $activeTab = $this->activeTab ?? 'all';
        $user = auth()->user();

        if ($user->isAdmin() || !in_array($activeTab, ['pending_my_approval', 'approved_by_me'])) {
            return $query;
        }

        if ($activeTab === 'pending_my_approval') {
            $ids = DB::table('documents')
                ->select('documents.id')
                ->where('documents.status', DocumentStatus::PENDING->value)
                ->whereExists(function ($q) use ($user) {
                    $q->selectRaw('1')
                        ->from('document_approvers')
                        ->whereColumn('document_approvers.document_id', 'documents.id')
                        ->where('document_approvers.approver_id', $user->id)
                        ->where('document_approvers.status', ApprovalStatus::PENDING->value)
                        ->whereColumn('document_approvers.step_order', 'documents.current_step');
                })
                ->orderBy('documents.id', 'asc')
                ->limit(100)
                ->pluck('id');

            return Document::query()->whereIn('id', $ids);
        }

        if ($activeTab === 'approved_by_me') {
            $ids = DB::table('documents')
                ->select('documents.id')
                ->whereExists(function ($q) use ($user) {
                    $q->selectRaw('1')
                        ->from('document_approvers')
                        ->whereColumn('document_approvers.document_id', 'documents.id')
                        ->where('document_approvers.approver_id', $user->id)
                        ->whereIn('document_approvers.status', [
                            ApprovalStatus::APPROVED->value,
                            ApprovalStatus::REJECTED->value,
                        ]);
                })
                ->orderBy('documents.id', 'desc')
                ->limit(100)
                ->pluck('id');

            return Document::query()->whereIn('id', $ids);
        }

        return $query;
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return [
                'all' => Tab::make('All Documents')
                    ->modifyQueryUsing(fn(Builder $query) => $query->orderBy('documents.id', 'desc')),
                'draft' => Tab::make('Draft')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('documents.status', DocumentStatus::DRAFT->value)->orderBy('documents.id', 'desc')),
                'pending' => Tab::make('Pending')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('documents.status', DocumentStatus::PENDING->value)->orderBy('documents.id', 'desc')),
                'approved' => Tab::make('Approved')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('documents.status', DocumentStatus::APPROVED->value)->orderBy('documents.id', 'desc')),
                'rejected' => Tab::make('Rejected')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('documents.status', DocumentStatus::REJECTED->value)->orderBy('documents.id', 'desc')),
            ];
        }

        return [
            'all' => Tab::make('All Documents')
                ->modifyQueryUsing(function (Builder $query) use ($user) {
                    return $query->where(function ($q) use ($user) {
                        $q->where('documents.creator_id', $user->id)
                            ->orWhere(function ($q) use ($user) {
                                $q->where('documents.status', DocumentStatus::PENDING->value)
                                    ->whereExists(function ($q) use ($user) {
                                        $q->selectRaw('1')
                                            ->from('document_approvers')
                                            ->whereColumn('document_approvers.document_id', 'documents.id')
                                            ->where('document_approvers.approver_id', $user->id)
                                            ->whereColumn('document_approvers.step_order', 'documents.current_step');
                                    });
                            })
                            ->orWhereExists(function ($q) use ($user) {
                                $q->selectRaw('1')
                                    ->from('document_approvers')
                                    ->whereColumn('document_approvers.document_id', 'documents.id')
                                    ->where('document_approvers.approver_id', $user->id)
                                    ->whereIn('document_approvers.status', [
                                        ApprovalStatus::APPROVED->value,
                                        ApprovalStatus::REJECTED->value,
                                    ]);
                            });
                    })->orderBy('documents.id', 'desc');
                }),
            'my_documents' => Tab::make('My Documents')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('documents.creator_id', $user->id)->orderBy('documents.id', 'desc')),
            'pending_my_approval' => Tab::make('Pending My Approval')
                ->modifyQueryUsing(fn(Builder $query) => $query->orderBy('documents.id', 'asc')),
            'approved_by_me' => Tab::make('Processed by Me')
                ->modifyQueryUsing(fn(Builder $query) => $query->orderBy('documents.id', 'desc')),
        ];
    }
}
