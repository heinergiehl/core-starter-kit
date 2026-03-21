<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Data\ParsedMarkdownBlogPost;
use App\Enums\PostStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MarkdownBlogFrontMatterParser
{
    /**
     * @param  array<int, string>  $supportedLocales
     */
    public function parse(string $absolutePath, string $rootPath, array $supportedLocales): ParsedMarkdownBlogPost
    {
        $normalizedRootPath = rtrim(str_replace('\\', '/', $rootPath), '/');
        $normalizedAbsolutePath = str_replace('\\', '/', $absolutePath);
        $relativePath = ltrim(Str::after($normalizedAbsolutePath, $normalizedRootPath), '/');
        $familyKey = str_replace('\\', '/', dirname($relativePath));
        $locale = Str::lower((string) pathinfo($relativePath, PATHINFO_FILENAME));

        if (($familyKey === '.') || ($familyKey === '')) {
            throw new InvalidArgumentException("Markdown post [{$relativePath}] must live in its own article folder, for example content/blog/stripe-vs-paddle/en.md.");
        }

        if (! in_array($locale, $supportedLocales, true)) {
            $supportedLocaleList = implode(', ', $supportedLocales);

            throw new InvalidArgumentException("Markdown post [{$relativePath}] uses unsupported locale [{$locale}]. Use one of: {$supportedLocaleList}.");
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', (string) File::get($absolutePath)) ?? '';
        [$frontMatter, $bodyMarkdown] = $this->splitFrontMatter($contents, $relativePath);

        $parsedFrontMatter = $this->parseFrontMatter($frontMatter, $relativePath);

        $title = trim((string) ($parsedFrontMatter['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException("Markdown post [{$relativePath}] must define a non-empty title in front matter.");
        }

        $normalizedBodyMarkdown = trim($bodyMarkdown);

        if ($normalizedBodyMarkdown === '') {
            throw new InvalidArgumentException("Markdown post [{$relativePath}] must contain markdown content below the front matter block.");
        }

        $slug = trim((string) ($parsedFrontMatter['slug'] ?? ''));
        $status = $this->parseStatus($parsedFrontMatter['status'] ?? null, $relativePath);

        return new ParsedMarkdownBlogPost(
            familyKey: $familyKey,
            locale: $locale,
            sourcePath: $relativePath,
            sourceHash: hash('sha256', $contents),
            title: $title,
            slug: $slug !== '' ? Str::slug($slug) : Str::slug($title),
            excerpt: $this->nullableTrimmedString($parsedFrontMatter['excerpt'] ?? null),
            bodyMarkdown: $normalizedBodyMarkdown,
            authorEmail: $this->nullableTrimmedString($parsedFrontMatter['author_email'] ?? null),
            category: $this->nullableTrimmedString($parsedFrontMatter['category'] ?? null),
            tags: $this->normalizeTags($parsedFrontMatter['tags'] ?? []),
            status: $status,
            publishedAt: $this->parsePublishedAt($parsedFrontMatter['published_at'] ?? null, $relativePath),
            metaTitle: $this->nullableTrimmedString($parsedFrontMatter['meta_title'] ?? null),
            metaDescription: $this->nullableTrimmedString($parsedFrontMatter['meta_description'] ?? null),
            featuredImage: $this->nullableTrimmedString($parsedFrontMatter['featured_image'] ?? null),
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitFrontMatter(string $contents, string $relativePath): array
    {
        $pattern = '/\A---\R(?P<front_matter>.*?)\R---\R?(?P<body>.*)\z/s';

        if (preg_match($pattern, $contents, $matches) !== 1) {
            throw new InvalidArgumentException("Markdown post [{$relativePath}] must start with a YAML front matter block wrapped in --- lines.");
        }

        return [
            (string) ($matches['front_matter'] ?? ''),
            (string) ($matches['body'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFrontMatter(string $frontMatter, string $relativePath): array
    {
        $lines = preg_split('/\R/', $frontMatter) ?: [];
        $parsed = [];
        $currentListKey = null;

        foreach ($lines as $lineIndex => $line) {
            $trimmedLine = trim($line);

            if (($trimmedLine === '') || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            if (preg_match('/^\s*-\s*(.+)\s*$/', $line, $matches) === 1) {
                if ($currentListKey === null) {
                    throw new InvalidArgumentException("Markdown post [{$relativePath}] has invalid YAML front matter near line ".($lineIndex + 1).'.');
                }

                if (! is_array($parsed[$currentListKey] ?? null)) {
                    throw new InvalidArgumentException("Markdown post [{$relativePath}] has invalid YAML front matter near line ".($lineIndex + 1).'.');
                }

                $parsed[$currentListKey][] = $this->parseScalarValue($matches[1]);

                continue;
            }

            $currentListKey = null;

            if (preg_match('/^([A-Za-z0-9_]+):(.*)$/', $line, $matches) !== 1) {
                throw new InvalidArgumentException("Markdown post [{$relativePath}] has invalid YAML front matter near line ".($lineIndex + 1).'.');
            }

            $key = trim($matches[1]);
            $rawValue = ltrim($matches[2]);

            if ($rawValue === '') {
                if ($this->nextSignificantLineStartsList($lines, $lineIndex + 1)) {
                    $parsed[$key] = [];
                    $currentListKey = $key;
                } else {
                    $parsed[$key] = null;
                }

                continue;
            }

            $parsed[$key] = $this->parseValue($rawValue, $relativePath, $lineIndex + 1);
        }

        return $parsed;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function nextSignificantLineStartsList(array $lines, int $startIndex): bool
    {
        $remainingLines = array_slice($lines, $startIndex);

        foreach ($remainingLines as $line) {
            $trimmedLine = trim($line);

            if (($trimmedLine === '') || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            return preg_match('/^\s*-\s+/', $line) === 1;
        }

        return false;
    }

    private function parseValue(string $rawValue, string $relativePath, int $lineNumber): mixed
    {
        $trimmedValue = trim($rawValue);

        if (($trimmedValue !== '') && str_starts_with($trimmedValue, '[')) {
            if (! str_ends_with($trimmedValue, ']')) {
                throw new InvalidArgumentException("Markdown post [{$relativePath}] has invalid YAML front matter near line {$lineNumber}.");
            }

            return $this->parseInlineList(substr($trimmedValue, 1, -1));
        }

        return $this->parseScalarValue($trimmedValue);
    }

    /**
     * @return array<int, string>
     */
    private function parseInlineList(string $value): array
    {
        $items = [];
        $buffer = '';
        $quote = null;
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $character) {
            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;
                }

                $buffer .= $character;

                continue;
            }

            if (($character === '"') || ($character === '\'')) {
                $quote = $character;
                $buffer .= $character;

                continue;
            }

            if ($character === ',') {
                $items[] = $this->parseScalarValue($buffer);
                $buffer = '';

                continue;
            }

            $buffer .= $character;
        }

        if (trim($buffer) !== '') {
            $items[] = $this->parseScalarValue($buffer);
        }

        return array_values(array_filter($items, static fn (mixed $item): bool => $item !== null && $item !== ''));
    }

    private function parseScalarValue(string $value): mixed
    {
        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return '';
        }

        if (($trimmedValue === 'null') || ($trimmedValue === '~')) {
            return null;
        }

        if (
            ((str_starts_with($trimmedValue, '"') && str_ends_with($trimmedValue, '"'))
            || (str_starts_with($trimmedValue, '\'') && str_ends_with($trimmedValue, '\'')))
            && (strlen($trimmedValue) >= 2)
        ) {
            $quote = $trimmedValue[0];
            $unquotedValue = substr($trimmedValue, 1, -1);

            if ($quote === '"') {
                return stripcslashes($unquotedValue);
            }

            return str_replace(["\\'", '\\\\'], ["'", '\\'], $unquotedValue);
        }

        return $trimmedValue;
    }

    private function parseStatus(mixed $value, string $relativePath): PostStatus
    {
        $normalizedValue = Str::lower(trim((string) ($value ?? 'draft')));
        $status = PostStatus::tryFrom($normalizedValue);

        if ($status instanceof PostStatus) {
            return $status;
        }

        $supportedStatuses = implode(', ', array_map(
            static fn (PostStatus $candidate): string => $candidate->value,
            PostStatus::cases(),
        ));

        throw new InvalidArgumentException("Markdown post [{$relativePath}] uses invalid status [{$normalizedValue}]. Use one of: {$supportedStatuses}.");
    }

    private function parsePublishedAt(mixed $value, string $relativePath): ?CarbonInterface
    {
        if (($value === null) || ($value === '')) {
            return null;
        }

        try {
            if ($value instanceof CarbonInterface) {
                return $value;
            }

            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value);
            }

            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                return CarbonImmutable::createFromTimestamp((int) $value);
            }

            if (is_string($value)) {
                return CarbonImmutable::parse($value);
            }
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException("Markdown post [{$relativePath}] has an invalid published_at value [{$value}].", previous: $exception);
        }

        throw new InvalidArgumentException("Markdown post [{$relativePath}] has an invalid published_at value.");
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTags(mixed $value): array
    {
        if (is_string($value)) {
            return $this->normalizeTagList([$value]);
        }

        if (is_array($value)) {
            return $this->normalizeTagList($value);
        }

        if ($value === null) {
            return [];
        }

        return $this->normalizeTagList([(string) $value]);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizeTagList(array $values): array
    {
        return collect($values)
            ->flatMap(function (mixed $value): array {
                $normalizedValue = trim((string) $value);

                if ($normalizedValue === '') {
                    return [];
                }

                return preg_split('/\s*,\s*/', $normalizedValue) ?: [];
            })
            ->map(static fn (string $tag): string => trim($tag))
            ->filter()
            ->unique(static fn (string $tag): string => Str::lower($tag))
            ->values()
            ->all();
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        $normalizedValue = trim((string) ($value ?? ''));

        return $normalizedValue !== '' ? $normalizedValue : null;
    }
}
