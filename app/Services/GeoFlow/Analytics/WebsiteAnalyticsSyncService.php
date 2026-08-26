<?php

namespace App\Services\GeoFlow\Analytics;

use App\Models\LeadSubmission;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebsiteAnalyticsSyncService
{
    private const STREAMS = ['visits', 'leads'];

    /** @return array<string, int> */
    public function sync(string $selectedStream = 'all', bool $reset = false): array
    {
        $this->assertConfigured();
        $streams = $selectedStream === 'all' ? self::STREAMS : [$selectedStream];
        if (array_diff($streams, self::STREAMS) !== []) {
            throw new RuntimeException('Unknown website analytics stream: '.$selectedStream);
        }

        $counts = [];
        foreach ($streams as $stream) {
            $counts[$stream] = $this->syncStream($stream, $reset);
        }

        return $counts;
    }

    private function syncStream(string $stream, bool $reset): int
    {
        $sourceKey = (string) config('geoflow.website_analytics.site_key');
        if ($reset) {
            DB::table('analytics_sync_cursors')->where(['source_key' => $sourceKey, 'stream' => $stream])->delete();
        }

        $cursor = DB::table('analytics_sync_cursors')->where(['source_key' => $sourceKey, 'stream' => $stream])->value('cursor');
        $total = 0;

        try {
            do {
                $payload = $this->fetch($stream, is_string($cursor) ? $cursor : null);
                $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
                DB::transaction(function () use ($stream, $items, $sourceKey): void {
                    foreach ($items as $item) {
                        if (is_array($item)) {
                            $stream === 'visits' ? $this->upsertVisit($sourceKey, $item) : $this->upsertLead($sourceKey, $item);
                        }
                    }
                });
                $total += count($items);
                $next = trim((string) ($payload['nextCursor'] ?? ''));
                $hasMore = (bool) ($payload['hasMore'] ?? false);
                if ($hasMore && ($next === '' || $next === $cursor)) {
                    throw new RuntimeException('Website analytics cursor did not advance.');
                }
                $cursor = $next !== '' ? $next : $cursor;
                $this->saveCursor($sourceKey, $stream, $cursor, $total, null);
            } while ($hasMore);
        } catch (\Throwable $exception) {
            $this->saveCursor($sourceKey, $stream, $cursor, $total, $exception->getMessage());
            throw $exception;
        }

        return $total;
    }

    /** @return array<string, mixed> */
    private function fetch(string $stream, ?string $cursor): array
    {
        $query = ['limit' => (int) config('geoflow.website_analytics.batch_limit', 200)];
        if ($cursor !== null && $cursor !== '') {
            $query['cursor'] = $cursor;
        }
        $path = '/api/geoflow/analytics/'.$stream;
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp."\n".$path.'?'.$queryString, (string) config('geoflow.website_analytics.secret'));
        $url = (string) config('geoflow.website_analytics.base_url').$path.'?'.$queryString;
        $response = $this->http()->withHeaders([
            'X-GEOFlow-Timestamp' => $timestamp,
            'X-GEOFlow-Signature' => $signature,
        ])->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Website analytics %s request failed with HTTP %d.', $stream, $response->status()));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Website analytics returned invalid JSON.');
        }

        return $payload;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout((int) config('geoflow.website_analytics.timeout_seconds', 30))->retry(2, 300, throw: false);
    }

    /** @param array<string, mixed> $item */
    private function upsertVisit(string $siteKey, array $item): void
    {
        $eventId = trim((string) ($item['eventId'] ?? ''));
        if ($eventId === '') {
            return;
        }
        DB::table('view_logs')->updateOrInsert(
            ['site_key' => $siteKey, 'source_event_id' => $eventId],
            [
                'article_id' => null,
                'source' => 'channel',
                'method' => 'GET',
                'path' => mb_substr((string) ($item['path'] ?? ''), 0, 2048),
                'route_name' => null,
                'status_code' => 200,
                'ip_address' => mb_substr((string) ($item['ipAddress'] ?? ''), 0, 64),
                'user_agent' => (string) ($item['userAgent'] ?? ''),
                'referer' => mb_substr((string) ($item['referrer'] ?? ''), 0, 2048),
                'visitor_id' => mb_substr((string) ($item['visitorId'] ?? ''), 0, 128),
                'session_id' => mb_substr((string) ($item['sessionId'] ?? ''), 0, 128),
                'device_type' => mb_substr((string) ($item['deviceType'] ?? ''), 0, 32),
                'source_type' => mb_substr((string) ($item['sourceType'] ?? ''), 0, 64),
                'page_title' => mb_substr((string) ($item['pageTitle'] ?? ''), 0, 512),
                'created_at' => $this->date($item['createdAt'] ?? null),
            ],
        );
    }

    /** @param array<string, mixed> $item */
    private function upsertLead(string $sourceSystem, array $item): void
    {
        $externalId = trim((string) ($item['eventId'] ?? ''));
        if ($externalId === '') {
            return;
        }
        $updatedAt = $this->date($item['updatedAt'] ?? null);
        DB::table('lead_submissions')->updateOrInsert(
            ['source_system' => $sourceSystem, 'external_id' => $externalId],
            [
                'lead_form_id' => null,
                'status' => $this->leadStatus((string) ($item['followStatus'] ?? 'new')),
                'payload' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source_url' => mb_substr((string) ($item['sourcePage'] ?? ''), 0, 500),
                'ip_address' => mb_substr((string) ($item['ipAddress'] ?? ''), 0, 45),
                'user_agent' => (string) ($item['userAgent'] ?? ''),
                'note' => (string) ($item['followNote'] ?? ''),
                'handled_at' => in_array((string) ($item['followStatus'] ?? ''), ['new', ''], true) ? null : $updatedAt,
                'external_updated_at' => $updatedAt,
                'created_at' => $this->date($item['createdAt'] ?? null),
                'updated_at' => $updatedAt,
            ],
        );
    }

    private function leadStatus(string $status): string
    {
        return match ($status) {
            'contacted' => LeadSubmission::STATUS_CONTACTED,
            'following', 'visited', 'qualified' => LeadSubmission::STATUS_QUALIFIED,
            'won', 'converted' => LeadSubmission::STATUS_CONVERTED,
            'closed', 'invalid' => LeadSubmission::STATUS_INVALID,
            default => LeadSubmission::STATUS_NEW,
        };
    }

    private function date(mixed $value): Carbon
    {
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return now();
        }
    }

    private function saveCursor(string $sourceKey, string $stream, mixed $cursor, int $count, ?string $error): void
    {
        DB::table('analytics_sync_cursors')->updateOrInsert(
            ['source_key' => $sourceKey, 'stream' => $stream],
            ['cursor' => $cursor, 'last_count' => $count, 'last_synced_at' => now(), 'last_error' => $error, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function assertConfigured(): void
    {
        if (! config('geoflow.website_analytics.enabled')) {
            throw new RuntimeException('Website analytics sync is disabled.');
        }
        if (filter_var(config('geoflow.website_analytics.base_url'), FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('GEOFLOW_WEBSITE_ANALYTICS_URL is invalid.');
        }
        if (strlen((string) config('geoflow.website_analytics.secret')) < 32) {
            throw new RuntimeException('GEOFLOW_ANALYTICS_SYNC_SECRET must contain at least 32 characters.');
        }
    }
}
