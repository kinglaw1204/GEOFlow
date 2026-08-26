<?php

namespace App\Console\Commands;

use App\Models\ArticleDistribution;
use App\Models\DistributionChannel;
use App\Services\GeoFlow\DistributionOrchestrator;
use Illuminate\Console\Command;

class RedistributeWebsiteArticlesCommand extends Command
{
    protected $signature = 'geoflow:redistribute-website-articles {channel : WordPress REST distribution channel id} {--yes : Queue the overwrite after showing its scope}';

    protected $description = 'Preview or queue cleaned-body overwrites for previously distributed website articles';

    public function handle(DistributionOrchestrator $orchestrator): int
    {
        $channel = DistributionChannel::query()->whereKey((int) $this->argument('channel'))
            ->where('status', DistributionChannel::STATUS_ACTIVE)->where('type', 'wordpress_rest')->first();
        if (! $channel) {
            $this->components->error('The specified active WordPress REST channel was not found.');
            return self::FAILURE;
        }
        $count = ArticleDistribution::query()->where('distribution_channel_id', (int) $channel->id)
            ->where('action', '!=', 'delete')->where('status', '!=', 'sending')
            ->whereHas('article', fn ($query) => $query->whereIn('status', ['published', 'private']))->count();
        $this->line(sprintf('channel=%d name=%s eligible_articles=%d', $channel->id, $channel->name, $count));
        if (! $this->option('yes')) {
            $this->components->warn('Preview only. Re-run with --yes to queue overwrite updates.');
            return self::SUCCESS;
        }
        $queued = $orchestrator->enqueueChannelContentRefresh($channel);
        $this->components->info('Website article overwrite queued: '.$queued);
        return self::SUCCESS;
    }
}
