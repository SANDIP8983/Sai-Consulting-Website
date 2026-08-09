<?php

namespace App\Console\Commands;

use App\Models\CustomerNotificationEvent;
use Illuminate\Console\Command;

class PruneCustomerNotificationLogs extends Command
{
    protected $signature = 'notifications:prune {--days=} {--pretend}';

    protected $description = 'Prune customer notification audit events older than the retention period';

    public function handle(): int
    {
        $days = $this->option('days') === null ? config('customer-notifications.retention_days') : (int) $this->option('days');
        $query = CustomerNotificationEvent::query()->where('occurred_at', '<', now()->subDays(max(1, $days)));
        $count = $query->count();
        if (! $this->option('pretend')) {
            $query->eachById(function ($event): void {
                $event->deliveries()->delete();
                $event->delete();
            });
        }
        $this->info(($this->option('pretend') ? 'Would prune ' : 'Pruned ').$count.' notification event(s).');

        return self::SUCCESS;
    }
}
