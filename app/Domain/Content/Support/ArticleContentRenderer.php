<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class ArticleContentRenderer
{
    /**
     * @param  array{strip_first_h1?: bool, rewrite_relative_markdown_links?: bool, toc_levels?: list<int>}  $options
     * @return array{html: string, toc: list<array{id: string, title: string, level: int}>}
     */
    public function renderMarkdown(string $markdown, array $options = []): array
    {
        $html = (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $this->renderHtml($html, $options);
    }

    /**
     * @param  array{strip_first_h1?: bool, rewrite_relative_markdown_links?: bool, toc_levels?: list<int>}  $options
     * @return array{html: string, toc: list<array{id: string, title: string, level: int}>}
     */
    public function renderHtml(string $html, array $options = []): array
    {
        $settings = $this->normalizeOptions($options);

        if ($html === '') {
            return [
                'html' => '',
                'toc' => [],
            ];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $errors = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($errors);

        $xpath = new DOMXPath($dom);

        if ($settings['strip_first_h1']) {
            $firstH1 = $xpath->query('//h1')->item(0);

            if ($firstH1 instanceof DOMElement) {
                $firstH1->parentNode?->removeChild($firstH1);
            }
        }

        if ($settings['rewrite_relative_markdown_links']) {
            $this->rewriteRelativeMarkdownLinks($xpath);
        }

        $headingIds = [];
        $toc = [];

        foreach ($xpath->query($this->headingXPathExpression($settings['toc_levels'])) as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            $title = trim((string) $heading->textContent);

            if ($title === '') {
                continue;
            }

            $id = Str::slug($title);

            if ($id === '') {
                continue;
            }

            $id = $this->uniqueHeadingId($id, $headingIds);
            $heading->setAttribute('id', $id);

            $toc[] = [
                'id' => $id,
                'title' => $title,
                'level' => (int) ltrim($heading->tagName, 'h'),
            ];
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $renderedHtml = '';

        if ($body instanceof DOMElement) {
            foreach ($body->childNodes as $node) {
                $renderedHtml .= (string) $dom->saveHTML($node);
            }
        }

        return [
            'html' => $renderedHtml !== '' ? $renderedHtml : $html,
            'toc' => $toc,
        ];
    }

    /**
     * @param  array{strip_first_h1?: bool, rewrite_relative_markdown_links?: bool, toc_levels?: list<int>}  $options
     * @return array{strip_first_h1: bool, rewrite_relative_markdown_links: bool, toc_levels: list<int>}
     */
    private function normalizeOptions(array $options): array
    {
        $tocLevels = array_values(array_filter(
            $options['toc_levels'] ?? [2, 3],
            static fn (mixed $level): bool => is_int($level) && $level >= 1 && $level <= 6
        ));

        return [
            'strip_first_h1' => (bool) ($options['strip_first_h1'] ?? true),
            'rewrite_relative_markdown_links' => (bool) ($options['rewrite_relative_markdown_links'] ?? false),
            'toc_levels' => $tocLevels !== [] ? $tocLevels : [2, 3],
        ];
    }

    /**
     * @param  list<int>  $tocLevels
     */
    private function headingXPathExpression(array $tocLevels): string
    {
        return collect($tocLevels)
            ->map(static fn (int $level): string => "//h{$level}")
            ->implode(' | ');
    }

    private function rewriteRelativeMarkdownLinks(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//a[@href]') as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = trim((string) $link->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1) {
                continue;
            }

            if (str_starts_with($href, '/')) {
                continue;
            }

            $parts = explode('#', $href, 2);
            $path = $parts[0] ?? '';
            $fragment = $parts[1] ?? '';
            $path = Str::startsWith($path, './') ? Str::after($path, './') : $path;

            if (preg_match('/^[A-Za-z0-9-]+\\.md$/', $path) !== 1) {
                continue;
            }

            $slug = Str::beforeLast($path, '.md');
            $rewritten = $slug.($fragment !== '' ? "#{$fragment}" : '');

            $link->setAttribute('href', $rewritten);
        }
    }

    /**
     * @param  array<string, int>  $usedHeadingIds
     */
    private function uniqueHeadingId(string $baseId, array &$usedHeadingIds): string
    {
        if (! isset($usedHeadingIds[$baseId])) {
            $usedHeadingIds[$baseId] = 0;

            return $baseId;
        }

        $usedHeadingIds[$baseId]++;

        return $baseId.'-'.$usedHeadingIds[$baseId];
    }
}
