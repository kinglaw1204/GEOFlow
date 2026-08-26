<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Services\GeoFlow\DistributionPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionContentDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_keeps_one_of_two_highly_similar_core_summaries_and_records_diagnostics(): void
    {
        $summary = '护墙板选购先确认基层条件、空间湿度、收口节点和交付标准，再比较材料参数与总成本。';
        $content = "# 测试文章\n\n## 核心摘要\n\n{$summary}\n\n## 正文\n\n这是足够长的正文内容，用于验证正常章节不会被删除。\n\n## 核心摘要\n\n{$summary}";
        $payload = app(DistributionPayloadBuilder::class)->build($this->article($content));

        $this->assertSame(1, preg_match_all('/<h2>核心摘要<\/h2>/u', (string) $payload['article']['content_html']));
        $this->assertSame(2, $payload['_distribution_diagnostics']['core_summary_count_before_send']);
        $this->assertSame(mb_strlen($content), $payload['_distribution_diagnostics']['content_length_before_send']);
        $this->assertSame(hash('sha256', $content), $payload['_distribution_diagnostics']['content_hash_before_send']);
        $this->assertTrue($payload['_distribution_diagnostics']['content_was_deduplicated']);
    }

    public function test_payload_keeps_one_copy_when_the_whole_article_body_is_repeated(): void
    {
        $sectionA = "## 选材判断\n\n护墙板选材需要结合基层平整度、空间湿度、设计效果、耐用要求和预算边界进行综合判断，不能只比较单块板材价格。";
        $sectionB = "## 安装验收\n\n安装前检查墙体含水率和基层牢固度，安装中核对拼缝、阴阳角与收口节点，完工后检查表面平整度和色差。";
        $sectionC = "## 交付建议\n\n合同中应明确材料规格、辅料系统、节点做法、损耗计算、工期安排、验收标准和售后责任，降低后续争议。";
        $body = implode("\n\n", [$sectionA, $sectionB, $sectionC]);
        $payload = app(DistributionPayloadBuilder::class)->build($this->article("# 测试文章\n\n{$body}\n\n{$body}"));

        $this->assertSame(1, substr_count((string) $payload['article']['content'], '## 选材判断'));
        $this->assertSame(1, substr_count((string) $payload['article']['content_html'], '<h2>安装验收</h2>'));
        $this->assertTrue($payload['_distribution_diagnostics']['content_was_deduplicated']);
    }

    private function article(string $content): Article
    {
        $category = Category::query()->create(['name' => '测试分类', 'slug' => 'test-category']);
        $author = Author::query()->create(['name' => '测试作者']);

        return Article::query()->create([
            'title' => '测试文章',
            'slug' => 'deduplication-test',
            'excerpt' => '',
            'content' => $content,
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);
    }
}
