<?php

namespace Jimoh\ActivityFeed\Cachers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ActivityCacher
{
    protected const TAG_GROUP = 'activity-feed';

    /**
     * Fetch or cache a paginated forSubject() result set.
     *
     * @param  Builder  $query
     * @param  string   $subjectType
     * @param  int|string $subjectId
     * @param  string   $logName
     * @param  int      $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Collection
     */
    public function rememberSubject(
        Builder $query,
        string $subjectType,
        int|string $subjectId,
        string $logName = 'default',
        int $page = 1,
        int $perPage = 15
    ): mixed {
        $key = $this->subjectKey($subjectType, $subjectId, $logName, $page);

        return $this->remember($key, fn () => $query->paginate($perPage, page: $page));
    }

    /**
     * Fetch or cache a paginated causedBy() result set.
     */
    public function rememberCauser(
        Builder $query,
        string $causerType,
        int|string $causerId,
        int $page = 1,
        int $perPage = 15
    ): mixed {
        $key = $this->causerKey($causerType, $causerId, $page);

        return $this->remember($key, fn () => $query->paginate($perPage, page: $page));
    }

    /**
     * Invalidate all cache entries for a given subject model.
     */
    public function forget(Model $subject): void
    {
        if (! config('activity-feed.cache', false)) {
            return;
        }

        $type = $subject->getMorphClass();
        $id   = $subject->getKey();

        if ($this->driverSupportsTags()) {
            Cache::store($this->store())->tags([self::TAG_GROUP, $this->subjectTag($type, $id)])->flush();

            return;
        }

        // Key-registry fallback for drivers that do not support tags (file, database)
        $registry = $this->getRegistry($type, $id);
        foreach ($registry as $key) {
            Cache::store($this->store())->forget($key);
        }
        $this->clearRegistry($type, $id);
    }

    /**
     * Manual invalidation by subject model — public API.
     */
    public function forgetSubject(Model $subject): void
    {
        $this->forget($subject);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected function remember(string $key, callable $callback): mixed
    {
        $ttl   = (int) config('activity-feed.cache_ttl', 300);

        if ($this->driverSupportsTags()) {
            // Tags allow wildcard-style invalidation without a key registry
            return Cache::store($this->store())
                ->tags([self::TAG_GROUP])
                ->remember($key, $ttl, $callback);
        }

        // Tagless drivers: write key to registry then cache normally
        $this->registerKey($key);

        return Cache::store($this->store())->remember($key, $ttl, $callback);
    }

    protected function subjectKey(string $type, int|string $id, string $logName, int $page): string
    {
        $safeType = str_replace('\\', '_', $type);

        return "activity_feed:subject:{$safeType}:{$id}:{$logName}:{$page}";
    }

    protected function causerKey(string $type, int|string $id, int $page): string
    {
        $safeType = str_replace('\\', '_', $type);

        return "activity_feed:causer:{$safeType}:{$id}:{$page}";
    }

    protected function subjectTag(string $type, int|string $id): string
    {
        $safeType = str_replace('\\', '_', $type);

        return "activity_feed:subject:{$safeType}:{$id}";
    }

    protected function store(): ?string
    {
        return config('activity-feed.cache_store') ?: null;
    }

    protected function driverSupportsTags(): bool
    {
        try {
            $store = Cache::store($this->store())->getStore();

            return $store instanceof \Illuminate\Cache\TaggableStore;
        } catch (\Exception) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Key-registry helpers (tagless drivers)
    // -------------------------------------------------------------------------

    protected function registryKey(string $type, int|string $id): string
    {
        $safeType = str_replace('\\', '_', $type);

        return "activity_feed:registry:{$safeType}:{$id}";
    }

    protected function getRegistry(string $type, int|string $id): array
    {
        return Cache::store($this->store())->get($this->registryKey($type, $id), []);
    }

    protected function registerKey(string $key): void
    {
        // Extract subject type and id from the key for scoped registry entries.
        // Key format: activity_feed:subject:{type}:{id}:{logName}:{page}
        if (! preg_match('#^activity_feed:subject:([^:]+):([^:]+):#', $key, $m)) {
            return;
        }

        $regKey   = $this->registryKey($m[1], $m[2]);
        $registry = Cache::store($this->store())->get($regKey, []);

        if (! in_array($key, $registry, true)) {
            $registry[] = $key;
            // Keep the registry alive at least as long as cached entries
            $ttl = (int) config('activity-feed.cache_ttl', 300) + 60;
            Cache::store($this->store())->put($regKey, $registry, $ttl);
        }
    }

    protected function clearRegistry(string $type, int|string $id): void
    {
        Cache::store($this->store())->forget($this->registryKey($type, $id));
    }
}
