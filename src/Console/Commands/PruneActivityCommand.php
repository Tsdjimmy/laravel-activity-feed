<?php

namespace Jimoh\ActivityFeed\Console\Commands;

use Illuminate\Console\Command;

class PruneActivityCommand extends Command
{
    protected $signature = 'activity:prune {--days= : Number of days to retain (overrides config)}';

    protected $description = 'Prune old activity log records';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('activity-feed.prune_days', 90));

        if ($days <= 0) {
            $this->error('The --days option must be a positive integer.');

            return self::FAILURE;
        }

        $modelClass = config('activity-feed.model', \Jimoh\ActivityFeed\Models\Activity::class);
        $cutoff     = now()->subDays($days);
        $total      = 0;

        $modelClass::query()
            ->where('created_at', '<', $cutoff)
            ->chunkById(1000, function ($records) use ($modelClass, &$total) {
                $ids = $records->pluck('id');
                $modelClass::whereIn('id', $ids)->delete();
                $total += $ids->count();
            });

        $this->info("Pruned {$total} activity log record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
