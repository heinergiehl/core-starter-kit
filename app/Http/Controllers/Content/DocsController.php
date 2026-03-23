<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Domain\Content\Support\ArticleContentRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocsController
{
    public function __construct(
        private readonly ArticleContentRenderer $articleContentRenderer
    ) {}

    public function index(): View
    {
        return view('docs.index', [
            'docs' => array_values($this->availableDocs()),
        ]);
    }

    public function show(string $locale, string $page): View
    {
        $page = strtolower(trim($page));

        if (! preg_match('/^[a-z0-9-]+$/', $page)) {
            abort(404);
        }

        $docs = $this->availableDocs();
        $doc = $docs[$page] ?? null;

        abort_if($doc === null, 404);

        $docsList = array_values($docs);
        [$prevDoc, $nextDoc] = $this->neighborDocs($docsList, $page);

        $markdown = File::get($doc['path']);
        $rendered = $this->articleContentRenderer->renderMarkdown($markdown, [
            'rewrite_relative_markdown_links' => true,
        ]);

        return view('docs.show', [
            'doc' => $doc,
            'docs' => $docsList,
            'content' => $rendered['html'],
            'toc' => $rendered['toc'],
            'prevDoc' => $prevDoc,
            'nextDoc' => $nextDoc,
        ]);
    }

    /**
     * @return array<string, array{slug: string, title: string, path: string, summary: string, filename: string, updated_at: string}>
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
                'filename' => $file->getFilename(),
                'updated_at' => Carbon::createFromTimestampUTC($file->getMTime())
                    ->setTimezone(config('app.timezone', 'UTC'))
                    ->toDateString(),
            ];
        }

        $docs = $this->sortDocs($docs);

        return $docs;
    }

    /**
     * @param  array<string, array{slug: string, title: string, path: string, summary: string, filename: string, updated_at: string}>  $docs
     * @return array<string, array{slug: string, title: string, path: string, summary: string, filename: string, updated_at: string}>
     */
    private function sortDocs(array $docs): array
    {
        $order = array_flip($this->docsSortOrder());

        uksort($docs, static function (string $a, string $b) use ($order): int {
            $posA = $order[$a] ?? PHP_INT_MAX;
            $posB = $order[$b] ?? PHP_INT_MAX;

            if ($posA !== $posB) {
                return $posA <=> $posB;
            }

            return $a <=> $b;
        });

        return $docs;
    }

    /**
     * @return array<int, string>
     */
    private function docsSortOrder(): array
    {
        return [
            'getting-started',
            'architecture',
            'features',
            'theming',
            'localization',
            'billing',
            'testing',
            'security',
            'billing-go-live-checklist',
            'customer-release-checklist',
            'email-client-qa',
            'versions',
        ];
    }

    /**
     * @param  list<array{slug: string, title: string, path: string, summary: string, filename: string, updated_at: string}>  $docs
     * @return array{0: ?array{slug: string, title: string, path: string, summary: string, filename: string, updated_at: string}, 1: ?array{slug: string, title: string, path: string, summary: string, filename: string, updated_at: string}}
     */
    private function neighborDocs(array $docs, string $slug): array
    {
        $count = count($docs);

        for ($i = 0; $i < $count; $i++) {
            if (($docs[$i]['slug'] ?? null) !== $slug) {
                continue;
            }

            return [
                $i > 0 ? $docs[$i - 1] : null,
                $i < $count - 1 ? $docs[$i + 1] : null,
            ];
        }

        return [null, null];
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
