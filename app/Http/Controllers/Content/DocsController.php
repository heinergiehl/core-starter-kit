<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocsController
{
    public function index(): View
    {
        return view('docs.index', [
            'docs' => array_values($this->availableDocs()),
        ]);
    }

    public function show(string $page): View
    {
        $page = strtolower(trim($page));

        if (! preg_match('/^[a-z0-9-]+$/', $page)) {
            abort(404);
        }

        $docs = $this->availableDocs();
        $doc = $docs[$page] ?? null;

        abort_if($doc === null, 404);

        $markdown = File::get($doc['path']);
        $rendered = $this->renderMarkdown($markdown);

        return view('docs.show', [
            'doc' => $doc,
            'docs' => array_values($docs),
            'content' => $rendered['html'],
            'toc' => $rendered['toc'],
        ]);
    }

    /**
     * @return array<string, array{slug: string, title: string, path: string, summary: string}>
     */
    private function availableDocs(): array
    {
        $docsPath = base_path('docs');

        if (! File::isDirectory($docsPath)) {
            return [];
        }

        $docs = [];

        foreach (File::files($docsPath) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $slug = strtolower((string) $file->getFilenameWithoutExtension());

            if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
                continue;
            }

            $markdown = File::get($file->getPathname());

            $docs[$slug] = [
                'slug' => $slug,
                'title' => $this->extractTitle($markdown, $slug),
                'path' => $file->getPathname(),
                'summary' => $this->extractSummary($markdown, $slug),
            ];
        }

        ksort($docs);

        return $docs;
    }

    /**
     * @return array{html: string, toc: list<array{id: string, title: string, level: int}>}
     */
    private function renderMarkdown(string $markdown): array
    {
        $html = (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $this->decorateHtml($html);
    }

    /**
     * @return array{html: string, toc: list<array{id: string, title: string, level: int}>}
     */
    private function decorateHtml(string $html): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $errors = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($errors);

        $xpath = new DOMXPath($dom);

        $firstH1 = $xpath->query('//h1')->item(0);
        if ($firstH1 instanceof DOMElement) {
            $firstH1->parentNode?->removeChild($firstH1);
        }

        $usedHeadingIds = [];
        $toc = [];

        foreach ($xpath->query('//h2 | //h3') as $heading) {
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

            $id = $this->uniqueHeadingId($id, $usedHeadingIds);
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

    private function extractTitle(string $markdown, string $slug): string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return Str::headline($slug);
    }

    private function extractSummary(string $markdown, string $slug): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '```') || $line === '---') {
                continue;
            }

            $summary = preg_replace('/[*_`>#\[\]\(\)]/', '', $line);
            $summary = trim((string) $summary);

            if ($summary !== '') {
                return Str::limit($summary, 140);
            }
        }

        return Str::headline($slug).' documentation.';
    }
}
