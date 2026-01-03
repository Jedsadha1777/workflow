<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class TemplateWorkflow extends Model
{
    protected $fillable = [
        'template_document_id',
        'step_order',
        'required_role',
        'same_department',
        'signature_cell',
        'approved_date_cell',
    ];

    protected function casts(): array
    {
        return [
            'same_department' => 'boolean',
        ];
    }

    protected function requiredRole(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return null;
                }

                try {
                    return UserRole::from($value);
                } catch (\ValueError $e) {
                    return null;
                }
            },
            set: fn($value) => $value instanceof UserRole ? $value->value : $value
        );
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateDocument::class, 'template_document_id');
    }

    public function findCandidates(int $departmentId): \Illuminate\Support\Collection
    {
        $roleValue = $this->required_role instanceof UserRole
            ? $this->required_role->value
            : $this->attributes['required_role'];

        $query = User::where('role', $roleValue);

        if ($this->same_department) {
            $query->where('department_id', $departmentId);
        }

        return $query->get();
    }
}
