<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'template_id',
        'department_id',
        'role_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateDocument::class, 'template_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class)->latestOfMany('version');
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class)->where('status', 'PUBLISHED')->latestOfMany('version');
    }

    public function draftVersion(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class)->where('status', 'DRAFT')->latestOfMany('version');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getNextVersionNumber(): int
    {
        $maxVersion = $this->versions()->max('version');
        return ($maxVersion ?? 0) + 1;
    }

    public function createNewVersion(): WorkflowVersion
    {
        return $this->versions()->create([
            'template_id' => $this->template_id,
            'version' => $this->getNextVersionNumber(),
            'status' => 'DRAFT',
        ]);
    }
}
