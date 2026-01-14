<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_version_id',
        'step_order',
        'role_id',
        'department_id',
        'step_type_id',
        'template_step_order',
    ];

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function stepType(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepType::class, 'step_type_id');
    }

    public function getTemplateWorkflow(): ?TemplateWorkflow
    {
        if (!$this->template_step_order) {
            return null;
        }

        return $this->workflowVersion->template->workflows()
            ->where('step_order', $this->template_step_order)
            ->first();
    }

    public function getSignatureCell(): ?string
    {
        return $this->getTemplateWorkflow()?->signature_cell;
    }

    public function getApprovedDateCell(): ?string
    {
        return $this->getTemplateWorkflow()?->approved_date_cell;
    }

    public function shouldSendEmail(): bool
    {
        return $this->stepType?->send_email ?? true;
    }

    public function findCandidates(): Collection
    {
        $query = User::where('role_id', $this->role_id)
            ->whereHas('role', function ($q) {
                $q->where('is_active', true);
            });

        if ($this->department_id) {
            $query->where('department_id', $this->department_id);
        }

        return $query->get();
    }

    public function getStepLabel(): string
    {
        $typeName = $this->stepType->name ?? 'Step';
        $roleName = $this->role->name ?? 'Unknown';
        $deptName = $this->department ? " ({$this->department->name})" : '';
        
        return "{$typeName} by {$roleName}{$deptName}";
    }
}
