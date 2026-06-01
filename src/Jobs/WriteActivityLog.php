<?php

namespace Jimoh\ActivityFeed\Jobs;

use Jimoh\ActivityFeed\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WriteActivityLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 3;
    public int $backoff = 10;

    public function __construct(protected array $payload)
    {
    }

    public function handle(): void
    {
        ActivityLogger::writeToDb($this->payload);
    }
}
