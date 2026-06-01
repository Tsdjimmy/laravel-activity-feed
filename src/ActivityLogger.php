<?php

namespace Jimoh\ActivityFeed;

use Jimoh\ActivityFeed\Cachers\ActivityCacher;
use Jimoh\ActivityFeed\Jobs\WriteActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    protected ?Model $subject       = null;
    protected ?Model $causer        = null;
    protected array  $properties    = [];
    protected array  $tags          = [];
    protected string $logName;
    protected ?string $event        = null;
    protected bool $resolveCauser   = true;

    public function __construct()
    {
        $this->logName = config('activity-feed.default_log_name', 'default');
    }

    public function performedOn(Model $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function causedBy(Model $causer): static
    {
        $this->causer        = $causer;
        $this->resolveCauser = false;

        return $this;
    }

    public function withProperties(array $properties): static
    {
        $this->properties = array_merge($this->properties, $properties);

        return $this;
    }

    public function withProperty(string $key, mixed $value): static
    {
        $this->properties[$key] = $value;

        return $this;
    }

    public function withTag(string $tag): static
    {
        $this->tags[] = $tag;

        return $this;
    }

    public function inLog(string $logName): static
    {
        $this->logName = $logName;

        return $this;
    }

    public function event(string $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function log(string $description): void
    {
        $causer = $this->resolveCauser ? $this->resolveAuthCauser() : $this->causer;

        $payload = [
            'log_name'         => $this->logName,
            'description'      => $description,
            'subject_type'     => $this->subject?->getMorphClass(),
            'subject_id'       => $this->subject?->getKey(),
            'subject_snapshot' => $this->resolveSnapshot(),
            'causer_type'      => $causer?->getMorphClass(),
            'causer_id'        => $causer?->getKey(),
            'properties'       => empty($this->properties) ? null : $this->properties,
            'event'            => $this->event,
            'ip_address'       => $this->resolveIp(),
            'user_agent'       => $this->resolveUserAgent(),
            'tags'             => empty($this->tags) ? null : array_values($this->tags),
        ];

        if (config('activity-feed.queue', false)) {
            WriteActivityLog::dispatch($payload)
                ->onConnection(config('activity-feed.queue_connection', 'default'))
                ->onQueue(config('activity-feed.queue_name', 'default'));
        } else {
            $this->writeToDb($payload);
        }

        // Invalidate cache for the subject after writing
        if ($this->subject !== null) {
            app(ActivityCacher::class)->forget($this->subject);
        }

        $this->reset();
    }

    public static function writeToDb(array $payload): void
    {
        $modelClass = config('activity-feed.model', \Jimoh\ActivityFeed\Models\Activity::class);
        $modelClass::create($payload);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected function resolveAuthCauser(): ?Model
    {
        $user = Auth::user();

        return ($user instanceof Model) ? $user : null;
    }

    protected function resolveSnapshot(): ?array
    {
        if (! config('activity-feed.capture_snapshot', false) || $this->subject === null) {
            return null;
        }

        return $this->subject->toArray();
    }

    protected function resolveIp(): ?string
    {
        if (! config('activity-feed.capture_ip', true)) {
            return null;
        }

        return Request::ip();
    }

    protected function resolveUserAgent(): ?string
    {
        if (! config('activity-feed.capture_user_agent', true)) {
            return null;
        }

        return Request::userAgent();
    }

    protected function reset(): void
    {
        $this->subject       = null;
        $this->causer        = null;
        $this->properties    = [];
        $this->tags          = [];
        $this->logName       = config('activity-feed.default_log_name', 'default');
        $this->event         = null;
        $this->resolveCauser = true;
    }
}
