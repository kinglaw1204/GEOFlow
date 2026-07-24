<?php

use App\Models\KnowledgeBase;
use App\Services\GeoFlow\KnowledgeChunkSyncService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$relativePath = 'imports/南山林-GEO第一批入库知识点-v1.md';
$path = storage_path('app/'.$relativePath);

if (! is_file($path)) {
    fwrite(STDERR, "Import file not found: {$path}\n");
    exit(1);
}

$content = trim((string) file_get_contents($path));
if ($content === '') {
    fwrite(STDERR, "Import file is empty: {$path}\n");
    exit(1);
}

$name = '南山林 GEO 基础知识库 v1';
$payload = [
    'name' => $name,
    'description' => '第一批已审核通过的通用知识点，覆盖护墙板基础百科、材料与工艺通识、环保与安全边界；不包含南山林内部产品体系、安装工艺、报价、证书和案例。',
    'content' => $content,
    'character_count' => mb_strlen($content, 'UTF-8'),
    'used_task_count' => 0,
    'file_type' => 'markdown',
    'file_path' => $relativePath,
    'word_count' => mb_strlen(strip_tags($content), 'UTF-8'),
    'usage_count' => 0,
    'source_name' => '南山林 GEO 第一批入库知识点 v1',
    'source_url' => 'docs/南山林-GEO第一批入库知识点-v1.md',
    'source_type' => 'document',
    'business_line' => '南山林-护墙板GEO',
    'effective_date' => '2026-06-30',
    'risk_level' => 'medium',
    'review_status' => 'reviewed',
];

$knowledgeBase = DB::transaction(function () use ($name, $payload): KnowledgeBase {
    $existing = KnowledgeBase::query()->where('name', $name)->first();

    if ($existing) {
        $existing->fill($payload);
        $existing->save();

        return $existing->refresh();
    }

    return KnowledgeBase::query()->create($payload);
});

$chunkCount = app(KnowledgeChunkSyncService::class)->sync((int) $knowledgeBase->id, $content);

echo json_encode([
    'status' => 'ok',
    'knowledge_base_id' => $knowledgeBase->id,
    'name' => $knowledgeBase->name,
    'character_count' => $knowledgeBase->character_count,
    'chunk_count' => $chunkCount,
    'review_status' => $knowledgeBase->review_status,
    'business_line' => $knowledgeBase->business_line,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
