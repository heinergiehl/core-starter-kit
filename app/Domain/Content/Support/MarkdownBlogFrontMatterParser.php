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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

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

        try {
            $parsedFrontMatter = Yaml::parse($frontMatter);
        } catch (ParseException $exception) {
            throw new InvalidArgumentException("Markdown post [{$relativePath}] has invalid YAML front matter: {$exception->getMessage()}.", previous: $exception);
        }

        if (! is_array($parsedFrontMatter)) {
            throw new InvalidArgumentException("Markdown post [{$relativePath}] front matter must resolve to a key/value object.");
        }

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
