<?php

namespace App\Models;

use App\Enums\StepType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_id',
        'step_order',
        'role_id',
        'division_id',
        'template_step_order',
        'step_type',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'template_step_order' => 'integer',
            'step_type' => StepType::class,
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function findCandidates(): Collection
    {
        $query = User::where('role_id', $this->role_id)
            ->where('is_active', true);

        if ($this->division_id) {
            $query->where('division_id', $this->division_id);
        }

        return $query->get();
    }

    public function getTemplateWorkflow(): ?TemplateWorkflow
    {
        if (!$this->template_step_order || !$this->workflow?->template_id) {
            return null;
        }

        return TemplateWorkflow::where('template_document_id', $this->workflow->template_id)
            ->where('step_order', $this->template_step_order)
            ->first();
    }
}