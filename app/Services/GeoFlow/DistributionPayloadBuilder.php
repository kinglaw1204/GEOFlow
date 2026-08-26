<?php

namespace App\Services\GeoFlow;

use App\Models\Article;
use App\Support\GeoFlow\ImageUrlNormalizer;
use App\Support\Site\ArticleHtmlPresenter;

class DistributionPayloadBuilder
{
    private const MAX_EMBEDDED_IMAGE_BYTES = 5 * 1024 * 1024;

    /**
     * 构造目标站 Agent 可稳定消费的文章分发载荷。
     *
     * @return array<string,mixed>
     */
    public function build(Article $article): array
    {
        $article->loadMissing([
            'category:id,name,slug',
            'author:id,name',
            'task:id,name',
            'articleImages.image',
        ]);
        $title = (string) $article->title;
        $content = (string) $article->content;
        $summaryCount = preg_match_all('/^\s*#{1,6}\s*核心摘要\s*$/um', $content) ?: 0;
        $body = $this->deduplicateArticle(ArticleHtmlPresenter::stripLeadingTitleHeading($content, $title));
        $contentHtml = ArticleHtmlPresenter::markdownToHtml($body);
        $excerpt = ArticleHtmlPresenter::cleanExcerpt((string) ($article->excerpt ?? ''), $title, 180);
        if ($excerpt === '') {
            $excerpt = ArticleHtmlPresenter::excerptFromContent($content, $title, 180);
        }
        $heroImageUrl = $this->heroImageUrl($article);

        return [
            'version' => '1.0',
            'source' => 'geoflow',
            'event' => 'article.publish',
            '_distribution_diagnostics' => [
                'content_length_before_send' => mb_strlen($content, 'UTF-8'),
                'core_summary_count_before_send' => $summaryCount,
                'content_hash_before_send' => hash('sha256', $content),
                'content_was_deduplicated' => $body !== ArticleHtmlPresenter::stripLeadingTitleHeading($content, $title),
            ],
            'article' => [
                'id' => (int) $article->id,
                'title' => $title,
                'slug' => (string) $article->slug,
                'excerpt' => $excerpt,
                'content' => $body,
                'content_format' => 'markdown',
                'content_html' => $contentHtml,
                'hero_image_url' => $heroImageUrl,
                'keywords' => (string) ($article->keywords ?? ''),
                'meta_description' => ArticleHtmlPresenter::cleanExcerpt((string) ($article->meta_description ?? $excerpt), $title, 180) ?: $excerpt,
                'status' => (string) $article->status,
                'is_featured' => (bool) $article->is_featured,
                'is_hot' => (bool) $article->is_hot,
                'published_at' => $article->published_at?->toISOString(),
                'updated_at' => $article->updated_at?->toISOString(),
                'category' => $article->category ? [
                    'id' => (int) $article->category->id,
                    'name' => (string) $article->category->name,
                    'slug' => (string) ($article->category->slug ?? ''),
                ] : null,
                'author' => $article->author ? [
                    'id' => (int) $article->author->id,
                    'name' => (string) $article->author->name,
                ] : null,
                'task' => $article->task ? [
                    'id' => (int) $article->task->id,
                    'name' => (string) $article->task->name,
                ] : null,
            ],
            'assets' => [
                'images' => $this->extractImageAssets($content, $contentHtml, $heroImageUrl !== '' ? [$heroImageUrl] : []),
            ],
        ];
    }

    private function deduplicateArticle(string $content): string
    {
        $content = $this->deduplicateCoreSummaries($content);
        $blocks = preg_split('/\R{2,}/u', trim($content)) ?: [];
        $blocks = array_values(array_filter(array_map('trim', $blocks), static fn (string $block): bool => $block !== ''));
        if (count($blocks) < 4) {
            return $content;
        }

        foreach ([0, 1] as $offset) {
            $remaining = count($blocks) - $offset;
            if ($remaining < 4 || $remaining % 2 !== 0) {
                continue;
            }
            $half = intdiv($remaining, 2);
            $first = implode("\n\n", array_slice($blocks, $offset, $half));
            $second = implode("\n\n", array_slice($blocks, $offset + $half));
            if (min(mb_strlen($this->normalizedDuplicateText($first)), mb_strlen($this->normalizedDuplicateText($second))) < 100) {
                continue;
            }
            if ($this->similarity($first, $second) >= 0.9) {
                return implode("\n\n", array_merge(array_slice($blocks, 0, $offset), array_slice($blocks, $offset, $half)));
            }
        }

        return $content;
    }

    private function deduplicateCoreSummaries(string $content): string
    {
        preg_match_all('/^\s*#{1,6}\s*核心摘要\s*$([\s\S]*?)(?=^\s*#{1,6}\s+\S|\z)/um', $content, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches[0] ?? []) !== 2) {
            return $content;
        }
        $first = trim((string) ($matches[1][0][0] ?? ''));
        $second = trim((string) ($matches[1][1][0] ?? ''));
        if (min(mb_strlen($this->normalizedDuplicateText($first)), mb_strlen($this->normalizedDuplicateText($second))) < 30 || $this->similarity($first, $second) < 0.88) {
            return $content;
        }
        $start = (int) $matches[0][1][1];
        $length = strlen((string) $matches[0][1][0]);

        return trim(substr($content, 0, $start)."\n\n".substr($content, $start + $length));
    }

    private function normalizedDuplicateText(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', '', $value) ?? $value;
        $value = preg_replace('/^#{1,6}\s*/um', '', $value) ?? $value;

        return preg_replace('/[\s\p{P}\p{S}]+/u', '', $value) ?? $value;
    }

    private function similarity(string $left, string $right): float
    {
        $left = $this->normalizedDuplicateText($left);
        $right = $this->normalizedDuplicateText($right);
        if ($left === '' || $right === '') {
            return 0.0;
        }
        if ($left === $right) {
            return 1.0;
        }
        $shorter = mb_strlen($left) <= mb_strlen($right) ? $left : $right;
        $longer = $shorter === $left ? $right : $left;
        if (str_contains($longer, $shorter)) {
            return mb_strlen($shorter) / max(1, mb_strlen($longer));
        }
        similar_text($left, $right, $percent);

        return $percent / 100;
    }

    /**
     * @return list<string>
     */
    private function heroImageUrl(Article $article): string
    {
        $image = $article->articleImages->sortBy('position')->first()?->image;
        if (! $image) {
            return '';
        }

        return ImageUrlNormalizer::toPublicUrl((string) ($image->file_path ?? ''));
    }

    /**
     * @param  list<string>  $additionalUrls
     * @return list<array<string,string>>
     */
    private function extractImageAssets(string $markdown, string $html, array $additionalUrls = []): array
    {
        $urls = $additionalUrls;
        if (preg_match_all('/!\[[^\]]*\]\(([^)\s]+)(?:\s+["\'][^"\']*["\'])?\)/u', $markdown, $matches)) {
            foreach ($matches[1] ?? [] as $url) {
                $urls[] = trim((string) $url);
            }
        }
        if (preg_match_all('/<img\b[^>]*\bsrc=(["\'])(.*?)\1/iu', $html, $matches)) {
            foreach ($matches[2] ?? [] as $url) {
                $urls[] = trim((string) $url);
            }
        }

        $assets = [];
        foreach (array_values(array_unique(array_filter($urls))) as $url) {
            $asset = $this->buildImageAsset((string) $url);
            if ($asset !== null) {
                $assets[] = $asset;
            }
        }

        return array_slice($assets, 0, 20);
    }

    /**
     * @return array<string,string>|null
     */
    private function buildImageAsset(string $url): ?array
    {
        if ($url === '' || str_starts_with(strtolower($url), 'data:') || str_starts_with(strtolower($url), 'javascript:')) {
            return null;
        }

        $asset = [
            'source_url' => $url,
            'filename' => $this->imageAssetFilename($url, ''),
        ];

        $path = $this->publicPathForImageUrl($url);
        if ($path !== null && is_file($path) && is_readable($path)) {
            $size = filesize($path);
            if (is_int($size) && $size > self::MAX_EMBEDDED_IMAGE_BYTES) {
                $asset['skip_reason'] = 'file_too_large';

                return $asset;
            }

            $contents = file_get_contents($path);
            if (is_string($contents) && $contents !== '') {
                $mimeType = mime_content_type($path) ?: 'application/octet-stream';
                $asset['mime_type'] = $mimeType;
                $asset['content_base64'] = base64_encode($contents);
                $asset['filename'] = $this->imageAssetFilename($url, (string) $mimeType);
            }
        }

        return $asset;
    }

    private function publicPathForImageUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }
        if (! str_starts_with($path, '/')) {
            return null;
        }

        $relativePath = ltrim($path, '/');
        if (str_contains($relativePath, '..')) {
            return null;
        }

        $publicPath = public_path($relativePath);
        if (is_file($publicPath)) {
            return $publicPath;
        }

        if (str_starts_with($relativePath, 'storage/')) {
            return storage_path('app/public/'.substr($relativePath, strlen('storage/')));
        }

        return $publicPath;
    }

    private function imageAssetFilename(string $url, string $mimeType): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION);
        if ($extension === '') {
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/svg+xml' => 'svg',
                default => 'img',
            };
        }

        return hash('sha256', $url).'.'.strtolower($extension);
    }
}
