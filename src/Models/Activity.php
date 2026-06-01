<?php

namespace Jimoh\ActivityFeed\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class Activity extends Model
{
    protected $table = 'activity_log';

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'subject_snapshot',
        'causer_type',
        'causer_id',
        'properties',
        'event',
        'ip_address',
        'user_agent',
        'tags',
    ];

    protected $casts = [
        'subject_snapshot' => 'array',
        'properties'       => 'array',
        'tags'             => 'array',
    ];

    public function subject(): MorphTo
    {
        $withTrashed = config('activity-feed.subject_returns_soft_deleted', true);

        if ($withTrashed) {
            return $this->morphTo()->withTrashed();
        }

        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Query scopes
    // -------------------------------------------------------------------------

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function scopeCausedBy(Builder $query, Model $causer): Builder
    {
        return $query
            ->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey());
    }

    public function scopeInLog(Builder $query, string $logName): Builder
    {
        return $query->where('log_name', $logName);
    }

    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Diff helper
    // -------------------------------------------------------------------------

    /**
     * Returns a field-keyed diff: ['field' => ['old' => x, 'new' => y], ...]
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getDiff(): array
    {
        $properties = $this->properties ?? [];
        $old = $properties['old'] ?? [];
        $new = $properties['new'] ?? [];

        $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
        $diff = [];

        foreach ($fields as $field) {
            $diff[$field] = [
                'old' => $old[$field] ?? null,
                'new' => $new[$field] ?? null,
            ];
        }

        return $diff;
    }
}
