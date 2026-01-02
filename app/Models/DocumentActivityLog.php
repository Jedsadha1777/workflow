<?php

namespace App\Models;

use App\Enums\DocumentActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'document_title',
        'actor_id',
        'actor_name',
        'actor_email',
        'actor_role',
        'action',
        'old_status',
        'new_status',
        'step_order',
        'comment',
        'ip_address',
        'metadata',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => DocumentActivityAction::class,
            'metadata' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function log(
        Document $document,
        DocumentActivityAction $action,
        ?User $actor = null,
        array $extra = []
    ): self {
        $actor = $actor ?? auth()->user();
        
        return self::create([
            'document_id' => $document->id,
            'document_title' => $document->title,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'System',
            'actor_email' => $actor?->email,
            'actor_role' => $actor?->role?->value ?? 'system',
            'action' => $action,
            'old_status' => $extra['old_status'] ?? null,
            'new_status' => $extra['new_status'] ?? null,
            'step_order' => $extra['step_order'] ?? null,
            'comment' => $extra['comment'] ?? null,
            'ip_address' => request()->ip(),
            'metadata' => $extra['metadata'] ?? null,
            'performed_at' => now(),
        ]);
    }
}