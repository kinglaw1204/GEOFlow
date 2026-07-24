<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$timestamp = date('Ymd-His');
$backupRoot = base_path('backups/nanshanlin-geoflow-'.$timestamp);

if (! is_dir($backupRoot) && ! mkdir($backupRoot, 0775, true) && ! is_dir($backupRoot)) {
    throw new RuntimeException('Cannot create backup directory: '.$backupRoot);
}

if (! is_dir($backupRoot.'/tables') && ! mkdir($backupRoot.'/tables', 0775, true) && ! is_dir($backupRoot.'/tables')) {
    throw new RuntimeException('Cannot create backup tables directory: '.$backupRoot.'/tables');
}

$tables = [
    'knowledge_bases',
    'knowledge_chunks',
    'prompts',
    'keyword_libraries',
    'keywords',
    'title_libraries',
    'titles',
    'categories',
    'authors',
    'sensitive_words',
    'tasks',
    'task_knowledge_bases',
    'task_distribution_channels',
    'distribution_channels',
    'site_settings',
    'enterprise_knowledge_projects',
    'enterprise_knowledge_sources',
    'enterprise_knowledge_revisions',
];

$sensitiveColumns = [
    'api_key',
    'secret',
    'token',
    'password',
    'credential',
    'authorization',
    'basic_auth',
    'access_key',
    'private_key',
];

$export = [
    'meta' => [
        'project' => '南山林 GEOFlow 配置与知识库备份',
        'exported_at' => date(DATE_ATOM),
        'database' => config('database.connections.'.config('database.default').'.database'),
        'notes' => [
            '本备份用于上线迁移前保留知识库、规则、关键词、标题库和任务配置。',
            '模型 API Key、分发密钥、管理员密码等敏感字段不会明文导出。',
            'knowledge_chunks 为切片数据；上线后如果 embedding 模型或向量库不同，建议重新切片/向量化。',
        ],
    ],
    'tables' => [],
];

foreach ($tables as $table) {
    if (! Schema::hasTable($table)) {
        continue;
    }

    $columns = Schema::getColumnListing($table);
    $rows = DB::table($table)->orderBy($columns[0] ?? 'id')->get()->map(function ($row) use ($columns, $sensitiveColumns) {
        $data = (array) $row;
        foreach ($columns as $column) {
            $lower = strtolower($column);
            foreach ($sensitiveColumns as $needle) {
                if (str_contains($lower, $needle) && array_key_exists($column, $data) && $data[$column] !== null && $data[$column] !== '') {
                    $data[$column] = '[REDACTED]';
                }
            }
        }

        return $data;
    })->all();

    $export['tables'][$table] = [
        'columns' => $columns,
        'count' => count($rows),
        'rows' => $rows,
    ];
}

$writeJson = static function (string $path, mixed $value): void {
    file_put_contents(
        $path,
        json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL
    );
};

$writeJson($backupRoot.'/geoflow-config-and-knowledge.json', $export);

foreach ($export['tables'] as $table => $payload) {
    $writeJson($backupRoot.'/tables/'.$table.'.json', $payload);
}

$md = [];
$md[] = '# 南山林 GEOFlow 配置与知识库备份';
$md[] = '';
$md[] = '- 导出时间：'.date('Y-m-d H:i:s');
$md[] = '- 备份目录：'.$backupRoot;
$md[] = '- 用途：上线前保存 GEO 知识库、生成规则、关键词、标题库、任务绑定关系。';
$md[] = '- 安全说明：API Key、密码、分发密钥等敏感字段已脱敏。';
$md[] = '';
$md[] = '## 数据表概览';
$md[] = '';
$md[] = '| 数据类型 | 表名 | 数量 | 用途 |';
$md[] = '| --- | --- | ---: | --- |';

$labels = [
    'knowledge_bases' => ['知识库', '知识库正文、审核状态、风险等级等'],
    'knowledge_chunks' => ['知识库切片', '向量化/检索用切片，上线后可重新生成'],
    'prompts' => ['生成规则', '文章生成提示词、约束词、发布级规则'],
    'keyword_libraries' => ['关键词库', '关键词库分组'],
    'keywords' => ['关键词', '用户搜索词和生成任务关键词'],
    'title_libraries' => ['标题库', 'GEOFlow 生成任务标题来源'],
    'titles' => ['标题', '具体标题、使用状态、绑定信息'],
    'categories' => ['分类', '文章分类'],
    'authors' => ['作者', '文章作者'],
    'sensitive_words' => ['敏感词/风险词', '内容审核约束'],
    'tasks' => ['生成任务', '任务配置、模型/知识库/标题库关系'],
    'task_knowledge_bases' => ['任务-知识库绑定', '任务引用哪些知识库'],
    'task_distribution_channels' => ['任务-分发渠道绑定', '任务发布渠道关系'],
    'distribution_channels' => ['分发渠道', '官网分发目标配置，密钥已脱敏'],
    'site_settings' => ['站点设置', 'GEOFlow 站点设置'],
    'enterprise_knowledge_projects' => ['企业知识项目', '企业知识库项目'],
    'enterprise_knowledge_sources' => ['企业知识来源', '信息源/资料来源'],
    'enterprise_knowledge_revisions' => ['企业知识版本', '企业知识修订记录'],
];

foreach ($export['tables'] as $table => $payload) {
    [$label, $usage] = $labels[$table] ?? ['配置数据', 'GEOFlow 配置数据'];
    $md[] = sprintf('| %s | `%s` | %d | %s |', $label, $table, (int) $payload['count'], $usage);
}

$md[] = '';
$md[] = '## 当前知识库';
$md[] = '';
$knowledgeRows = $export['tables']['knowledge_bases']['rows'] ?? [];
foreach ($knowledgeRows as $row) {
    $md[] = sprintf(
        '- #%s %s（状态：%s，风险：%s，内容长度：%d）',
        $row['id'] ?? '',
        $row['name'] ?? '',
        $row['review_status'] ?? ($row['status'] ?? ''),
        $row['risk_level'] ?? '',
        mb_strlen((string) ($row['content'] ?? ''))
    );
}

$md[] = '';
$md[] = '## 当前生成规则/提示词';
$md[] = '';
$promptRows = $export['tables']['prompts']['rows'] ?? [];
foreach ($promptRows as $row) {
    $md[] = sprintf(
        '- #%s %s（类型：%s，长度：%d）',
        $row['id'] ?? '',
        $row['name'] ?? ($row['title'] ?? ''),
        $row['type'] ?? ($row['prompt_type'] ?? ''),
        mb_strlen((string) ($row['content'] ?? $row['prompt'] ?? ''))
    );
}

$md[] = '';
$md[] = '## 迁移建议';
$md[] = '';
$md[] = '1. 先在新服务器安装同版本 GEOFlow，并确认数据库迁移完成。';
$md[] = '2. 优先导入 `knowledge_bases`、`prompts`、`keyword_libraries/keywords`、`title_libraries/titles`。';
$md[] = '3. `knowledge_chunks` 可作为参考备份；如果上线模型或 embedding 配置不同，建议重新切片和向量化。';
$md[] = '4. AI 模型 API Key、官网分发密钥、管理员账号密码不要从备份里恢复，应该在新环境后台重新配置。';
$md[] = '5. 导入后先用 1-2 篇文章测试：生成、预览、分发、官网展示，再批量开启。';
$md[] = '';

file_put_contents($backupRoot.'/README-备份说明.md', implode(PHP_EOL, $md).PHP_EOL);

$restore = [];
$restore[] = '# 南山林 GEOFlow 上线恢复清单';
$restore[] = '';
$restore[] = '## 文件说明';
$restore[] = '';
$restore[] = '- `geoflow-config-and-knowledge.json`：完整脱敏配置备份。';
$restore[] = '- `tables/*.json`：按表拆分的备份，便于人工检查或局部恢复。';
$restore[] = '- `README-备份说明.md`：备份内容总览。';
$restore[] = '- `docs/`：本地知识框架、信息源审核表、实施方案等文档副本。';
$restore[] = '';
$restore[] = '## 上线恢复顺序';
$restore[] = '';
$restore[] = '1. 新环境配置 `.env`、数据库、队列、模型。';
$restore[] = '2. 恢复知识库和规则数据。';
$restore[] = '3. 重新配置 AI 模型 API Key 和官网分发密钥。';
$restore[] = '4. 对知识库执行切片/向量化。';
$restore[] = '5. 测试生成文章和官网分发。';
$restore[] = '';

file_put_contents($backupRoot.'/上线恢复清单.md', implode(PHP_EOL, $restore).PHP_EOL);

echo json_encode([
    'ok' => true,
    'backup_dir' => $backupRoot,
    'tables' => array_map(static fn ($payload) => $payload['count'], $export['tables']),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
