<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateWorkflow extends Model
{
    protected $fillable = [
        'template_document_id',
        'step_order',
        'step_name',
        'signature_cell',
        'approved_date_cell',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateDocument::class, 'template_document_id');
    }
}
