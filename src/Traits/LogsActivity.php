<?php

namespace Jimoh\ActivityFeed\Traits;

use Jimoh\ActivityFeed\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->logModelEvent('created'));
        static::updated(fn (Model $model) => $model->logModelEvent('updated'));
        static::deleted(fn (Model $model) => $model->logModelEvent('deleted'));
    }

    protected function logModelEvent(string $event): void
    {
        $logger = app(ActivityLogger::class)
            ->performedOn($this)
            ->event($event);

        if ($event === 'updated') {
            $logger = $logger->withProperties($this->buildDiffProperties());
        }

        $logger->log($event);
    }

    /**
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    protected function buildDiffProperties(): array
    {
        $old = [];
        $new = [];

        foreach ($this->getDirty() as $attribute => $newValue) {
            if ($this->shouldIgnoreAttribute($attribute)) {
                continue;
            }

            if (! $this->shouldLogAttribute($attribute)) {
                continue;
            }

            $old[$attribute] = $this->getOriginal($attribute);
            $new[$attribute] = $newValue;
        }

        return ['old' => $old, 'new' => $new];
    }

    protected function shouldIgnoreAttribute(string $attribute): bool
    {
        $defaults = ['created_at', 'updated_at', 'deleted_at'];
        $custom   = property_exists($this, 'ignoreAttributes') ? $this->ignoreAttributes : [];

        return in_array($attribute, array_merge($defaults, $custom), true);
    }

    protected function shouldLogAttribute(string $attribute): bool
    {
        $whitelist = property_exists($this, 'logAttributes') ? $this->logAttributes : [];

        if (empty($whitelist)) {
            return true;
        }

        return in_array($attribute, $whitelist, true);
    }
}
