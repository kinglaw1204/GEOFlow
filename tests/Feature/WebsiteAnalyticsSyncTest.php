<?php

namespace Tests\Feature;

use App\Services\GeoFlow\Analytics\WebsiteAnalyticsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteAnalyticsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_authenticates_and_idempotently_syncs_official_visits_and_leads(): void
    {
        $secret = str_repeat('a', 64);
        config()->set('geoflow.website_analytics', [
            'enabled' => true,
            'base_url' => 'https://www.nanshanlin.com',
            'secret' => $secret,
            'site_key' => 'nanshanlin-website',
            'batch_limit' => 200,
            'timeout_seconds' => 10,
        ]);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($secret) {
            $timestamp = $request->header('X-GEOFlow-Timestamp')[0] ?? '';
            $parts = parse_url($request->url());
            $target = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
            $expected = hash_hmac('sha256', $timestamp."\n".$target, $secret);
            $this->assertSame($expected, $request->header('X-GEOFlow-Signature')[0] ?? '');

            if (str_contains($request->url(), '/visits')) {
                return Http::response(['hasMore' => false, 'nextCursor' => 'visit-cursor', 'items' => [[
                    'eventId' => 'visit-1', 'createdAt' => '2026-08-21T01:00:00+08:00', 'path' => '/zh-CN',
                    'ipAddress' => '203.0.113.1', 'visitorId' => 'visitor-1', 'sessionId' => 'session-1',
                    'deviceType' => 'mobile', 'sourceType' => 'direct', 'pageTitle' => '南山林',
                ]]]);
            }

            return Http::response(['hasMore' => false, 'nextCursor' => 'lead-cursor', 'items' => [[
                'eventId' => 'lead-1', 'createdAt' => '2026-08-20T01:00:00+08:00', 'updatedAt' => '2026-08-21T02:00:00+08:00',
                'followStatus' => 'won', 'name' => '测试客户', 'contact' => '13800000000', 'sourcePage' => '/contact',
            ]]]);
        });

        $service = app(WebsiteAnalyticsSyncService::class);
        $this->assertSame(['visits' => 1, 'leads' => 1], $service->sync());
        $this->assertSame(['visits' => 1, 'leads' => 1], $service->sync());

        $this->assertSame(1, DB::table('view_logs')->where('site_key', 'nanshanlin-website')->count());
        $this->assertDatabaseHas('view_logs', ['source_event_id' => 'visit-1', 'source' => 'channel', 'ip_address' => '203.0.113.1']);
        $this->assertSame(1, DB::table('lead_submissions')->where('source_system', 'nanshanlin-website')->count());
        $this->assertDatabaseHas('lead_submissions', ['external_id' => 'lead-1', 'status' => 'converted']);
        $this->assertDatabaseHas('analytics_sync_cursors', ['stream' => 'visits', 'last_error' => null]);
    }
}
