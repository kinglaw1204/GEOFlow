<?php

namespace App\Support\Site;

use App\Models\Article;
use App\Support\GeoFlow\ImageUrlNormalizer;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * 文章正文 Markdown 渲染与摘要生成（对齐旧版前台展示习惯）。
 */
final class ArticleHtmlPresenter
{
    /**
     * 将 Markdown 转为 HTML（剥离不安全 HTML 输入）。
     */
    public static function markdownToHtml(string $markdown): string
    {
        $markdown = self::normalizeMarkdownImages(trim($markdown));
        if ($markdown === '') {
            return '';
        }

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return self::decorateRenderedHtml($converter->convert($markdown)->getContent());
    }

    /**
     * 从正文中去掉与标题一致的首行 H1，避免详情页重复大标题。
     */
    public static function stripLeadingTitleHeading(string $content, string $title): string
    {
        $content = (string) $content;
        $title = trim($title);
        if ($title === '') {
            return $content;
        }

        $pattern = '/^\s*#\s*'.preg_quote($title, '/').'\s*(?:\r?\n)+/u';

        return (string) preg_replace($pattern, '', $content, 1);
    }

    /**
     * 列表卡片摘要：优先 excerpt，否则从正文抽纯文本片段。
     */
    public static function cardSummary(Article $article, int $limit = 120): string
    {
        $excerpt = trim((string) $article->excerpt);
        if ($excerpt !== '') {
            return self::cleanExcerpt($excerpt, (string) $article->title, $limit);
        }

        $body = self::stripLeadingTitleHeading((string) $article->content, (string) $article->title);

        return self::cleanExcerpt($body, (string) $article->title, $limit);
    }

    /**
     * 统一清洗文章摘要，避免标题、核心摘要标签和半截句子进入前台/分发。
     */
    public static function cleanExcerpt(string $text, string $title = '', int $limit = 180): string
    {
        $plain = self::excerptSourcePlainText($text, $title);
        if ($plain === '') {
            return '';
        }

        return self::truncateAtSentence($plain, $limit);
    }

    public static function excerptFromContent(string $content, string $title = '', int $limit = 180): string
    {
        $body = self::stripLeadingTitleHeading($content, $title);
        $coreSummary = self::coreSummaryText($body);

        return self::cleanExcerpt($coreSummary !== '' ? $coreSummary : $body, $title, $limit);
    }

    private static function coreSummaryText(string $content): string
    {
        if (! preg_match('/^\s*#{1,3}\s*核心摘要\s*(?:\r?\n)+([\s\S]*?)(?=^\s*#{1,6}\s+\S|\z)/um', $content, $matches)) {
            return '';
        }

        $lines = preg_split('/\r?\n/u', trim((string) $matches[1])) ?: [];
        $summaryLines = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            $line = preg_replace('/^\s*(?:[-*+]|[0-9]+[.、])\s*/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $summaryLines[] = $line;
            if (mb_strlen(implode(' ', $summaryLines)) >= 120 || count($summaryLines) >= 2) {
                break;
            }
        }

        return trim(implode(' ', $summaryLines));
    }

    private static function toPlainLine(string $text): string
    {
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', ' ', $text) ?? $text;
        $text = preg_replace('/^\s*#{1,6}\s*核心摘要\s*$/um', ' ', $text) ?? $text;
        $text = preg_replace('/[#*_`>\[\]()]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function excerptSourcePlainText(string $text, string $title): string
    {
        $text = self::stripLeadingTitleHeading($text, $title);
        $plain = self::toPlainLine($text);
        if ($title !== '') {
            $plain = preg_replace('/^'.preg_quote($title, '/').'\s*/u', '', $plain, 1) ?? $plain;
        }
        $plain = preg_replace('/^核心摘要\s*/u', '', $plain, 1) ?? $plain;

        return trim($plain);
    }

    private static function truncateAtSentence(string $plain, int $limit): string
    {
        if (mb_strlen($plain) <= $limit) {
            return self::ensureFinalPunctuation($plain);
        }

        $candidate = mb_substr($plain, 0, $limit);
        if (preg_match('/^(.{60,}[。！？.!?])/u', $candidate, $matches)) {
            return trim((string) $matches[1]);
        }

        return rtrim($candidate, " \t\n\r\0\x0B，、；;：:").'…';
    }

    private static function ensureFinalPunctuation(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '' || preg_match('/[。！？.!?…]$/u', $plain) === 1) {
            return $plain;
        }

        return $plain.'。';
    }

    private static function normalizeMarkdownImages(string $markdown): string
    {
        return preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+(".*?"|\'.*?\'))?\)/u',
            static function (array $matches): string {
                $alt = ImageUrlNormalizer::readableAlt((string) ($matches[1] ?? ''));
                $url = ImageUrlNormalizer::toPublicUrl((string) ($matches[2] ?? ''));
                $title = trim((string) ($matches[3] ?? ''));

                return '!['.$alt.']('.$url.($title !== '' ? ' '.$title : '').')';
            },
            $markdown
        ) ?? $markdown;
    }

    private static function decorateRenderedHtml(string $html): string
    {
        $html = preg_replace('/<table>/u', '<div class="article-table-wrap"><table class="article-table">', $html) ?? $html;
        $html = preg_replace('/<\/table>/u', '</table></div>', $html) ?? $html;
        $html = preg_replace('/<p>\s*(<img\b[^>]*>)\s*<\/p>/u', '$1', $html) ?? $html;
        $html = preg_replace('/<img\b(?![^>]*\bloading=)/u', '<img loading="lazy"', $html) ?? $html;
        $html = preg_replace('/<img\b(?![^>]*\bdecoding=)/u', '<img decoding="async"', $html) ?? $html;

        return $html;
    }
}
