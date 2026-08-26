<?php

namespace App\Console\Commands;

use App\Services\GeoFlow\Analytics\WebsiteAnalyticsSyncService;
use Illuminate\Console\Command;
use Throwable;

class GeoFlowSyncWebsiteAnalyticsCommand extends Command
{
    protected $signature = 'geoflow:sync-website-analytics {--stream=all : all, visits or leads} {--reset : Restart the selected cursor}';

    protected $description = 'Incrementally synchronize official website visits and leads into Growth Center';

    public function handle(WebsiteAnalyticsSyncService $sync): int
    {
        try {
            $counts = $sync->sync((string) $this->option('stream'), (bool) $this->option('reset'));
            foreach ($counts as $stream => $count) {
                $this->info(sprintf('Website analytics synced: stream=%s, received=%d', $stream, $count));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
