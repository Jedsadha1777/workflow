<?php

namespace App\Models;

use App\Enums\TemplateStatus;
use App\Services\TemplateFieldParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class TemplateDocument extends Model
{
    protected $fillable = [
        'name',
        'version',
        'status',
        'parent_id',
        'excel_file',
        'pdf_layout_html',
        'pdf_orientation',
        'content',
        'calculation_scripts',
        'expired_at',
        'expired_reason',
    ];

    protected $casts = [
        'status' => TemplateStatus::class,
        'expired_at' => 'datetime',
    ];

    protected function content(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!is_string($value)) {
                    return $value;
                }

                $decoded = json_decode($value, true);

                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }

                if (is_array($decoded)) {
                    return $decoded;
                }

                return $value;
            },
            set: fn($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_document_id');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(TemplateWorkflow::class)->orderBy('step_order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TemplateDocument::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateDocument::class, 'parent_id');
    }

    public function canEdit(): bool
    {
        return $this->status === TemplateStatus::DRAFT;
    }

    public function canDelete(): bool
    {
        return $this->status === TemplateStatus::DRAFT;
    }

      public function isUsedByPublishedWorkflow(): bool
    {
        return \App\Models\Workflow::where('template_id', $this->id)
            ->where('status', 'PUBLISHED')
            ->exists();
    }

    public function canArchive(): bool
    {
        return $this->status === TemplateStatus::PUBLISHED 
            && !$this->isExpired()
            && !$this->isUsedByPublishedWorkflow();
    }


    public function canPublish(): bool
    {
        return $this->status === TemplateStatus::DRAFT
            && $this->workflows()->exists();
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->lte(now());
    }

    public function canExpire(): bool
    {
        return $this->status === TemplateStatus::PUBLISHED 
            && $this->expired_at === null;
    }

    public function expire(string $reason, ?\DateTime $expiredAt = null): void
    {
        if (!$this->canExpire()) {
            throw new \Exception('Cannot expire this template');
        }

        $this->update([
            'status' => TemplateStatus::ARCHIVED,
            'expired_at' => $expiredAt ?? now(),
            'expired_reason' => $reason,
        ]);
    }

    public function publish(): void
    {
        if (!$this->canPublish()) {
            throw new \Exception('Cannot publish this template');
        }

        \DB::transaction(function () {
            if ($this->parent_id) {
                TemplateDocument::where('id', $this->parent_id)
                    ->update([
                        'status' => TemplateStatus::ARCHIVED,
                        'expired_at' => now(),
                        'expired_reason' => 'Superseded by version ' . $this->version,
                    ]);
            }

            $this->update(['status' => TemplateStatus::PUBLISHED]);
        });
    }

    public function archive(): void
    {
        if ($this->status !== TemplateStatus::PUBLISHED) {
            throw new \Exception('Only published templates can be archived');
        }

        $this->update(['status' => TemplateStatus::ARCHIVED]);
    }

    public function createNewVersion(): self
    {
        if ($this->status === TemplateStatus::DRAFT) {
            throw new \Exception('Cannot create version from draft template');
        }

        return \DB::transaction(function () {
            $newVersion = $this->replicate([
                'version',
                'status',
                'parent_id',
                'expired_at',
                'expired_reason',
            ]);

            $newVersion->version = $this->version + 1;
            $newVersion->status = TemplateStatus::DRAFT;
            $newVersion->parent_id = $this->id;
            $newVersion->save();

            foreach ($this->workflows as $workflow) {
                $newWorkflow = $workflow->replicate();
                $newWorkflow->template_document_id = $newVersion->id;
                $newWorkflow->save();
            }

            return $newVersion;
        });
    }

    public function validateForDivisions(): array
    {
        $divisions = \App\Models\Division::all();
        $warnings = [];

        foreach ($divisions as $dept) {
            foreach ($this->workflows as $workflow) {
                $roleLabel = $workflow->required_role ? $workflow->required_role->label() : 'Unknown Role';

                if ($workflow->same_division) {
                    $roleValue = $workflow->required_role ? $workflow->required_role->value : null;
                    if (!$roleValue) continue;

                    $count = \App\Models\User::where('role', $roleValue)
                        ->where('division_id', $dept->id)
                        ->count();

                    if ($count === 0) {
                        $warnings[] = "{$dept->name} has no {$roleLabel}";
                    } elseif ($count > 1) {
                        $warnings[] = "{$dept->name} has {$count} {$roleLabel}s";
                    }
                } else {
                    $roleValue = $workflow->required_role ? $workflow->required_role->value : null;
                    if (!$roleValue) continue;

                    $count = \App\Models\User::where('role', $roleValue)->count();
                    if ($count === 0) {
                        $warnings[] = "Company has no {$roleLabel}";
                    }
                }
            }
        }

        return $warnings;
    }


    public function getFormFields(): array
    {
        $parser = app(TemplateFieldParser::class);
        $sheets = $this->content['sheets'] ?? [];

        return $parser->parseAllSheets($sheets);
    }

    public function getSignatureFields(): array
    {
        $parser = app(TemplateFieldParser::class);
        return $parser->filterByType($this->getFormFields(), 'signature');
    }

    public function getDateFields(): array
    {
        $parser = app(TemplateFieldParser::class);
        return $parser->filterByType($this->getFormFields(), 'date');
    }

    public function getFieldsByType(string $type): array
    {
        $parser = app(TemplateFieldParser::class);

        if (!$parser->isValidType($type)) {
            return [];
        }

        return $parser->filterByType($this->getFormFields(), $type);
    }

    public static function getLatestPublished(string $name)
    {
        return self::where('name', $name)
            ->where('status', TemplateStatus::PUBLISHED)
            ->orderBy('version', 'desc')
            ->first();
    }
}