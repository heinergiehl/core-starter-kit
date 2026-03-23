<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Data\ParsedMarkdownBlogPost;
use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Domain\Content\Support\MarkdownBlogFrontMatterParser;
use App\Enums\PostStatus;
use App\Models\User;
use App\Support\Content\BlogEditorSupport;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MarkdownBlogSynchronizer
{
    public function __construct(
        public MarkdownBlogFrontMatterParser $parser,
    ) {}

    /**
     * @return array{
     *     root_path:string,
     *     dry_run:bool,
     *     discovered:int,
     *     created:int,
     *     updated:int,
     *     skipped:int,
     *     unchanged:int,
     *     archived:int,
     *     warnings:array<int, string>,
     *     errors:array<int, string>,
     *     changes:array<int, array{action:string, source_path:string, title:string, locale:string}>
     * }
     */
    public function sync(
        string $path,
        bool $dryRun = false,
        bool $archiveMissing = false,
        ?string $fallbackAuthorEmail = null,
        bool $createOnly = false,
        bool $forcePublish = false,
        bool $publishNow = false,
    ): array {
        $rootPath = $this->resolveRootPath($path);
        $result = [
            'root_path' => $rootPath,
            'dry_run' => $dryRun,
            'discovered' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'unchanged' => 0,
            'archived' => 0,
            'warnings' => [],
            'errors' => [],
            'changes' => [],
        ];

        if (! File::isDirectory($rootPath)) {
            $result['errors'][] = "Content path [{$rootPath}] does not exist.";

            return $result;
        }

        $markdownFiles = collect(File::allFiles($rootPath))
            ->filter(static fn (\SplFileInfo $file): bool => Str::lower($file->getExtension()) === 'md')
            ->sortBy(static fn (\SplFileInfo $file): string => str_replace('\\', '/', $file->getPathname()))
            ->values();

        $result['discovered'] = $markdownFiles->count();

        $supportedLocales = array_values(array_keys(config('saas.locales.supported', ['en' => 'English'])));
        $parsedPosts = [];

        if ($markdownFiles->isNotEmpty()) {
            foreach ($markdownFiles as $markdownFile) {
                try {
                    $parsedPosts[] = $this->parser->parse($markdownFile->getPathname(), $rootPath, $supportedLocales);
                } catch (\Throwable $exception) {
                    $result['errors'][] = $exception->getMessage();
                }
            }

            if ($result['errors'] !== []) {
                return $result;
            }

            $result['errors'] = $this->validateParsedPosts($parsedPosts);

            if ($result['errors'] !== []) {
                return $result;
            }

            foreach ($parsedPosts as $parsedPost) {
                try {
                    $this->validateEntryDependencies($parsedPost, $fallbackAuthorEmail);
                } catch (\Throwable $exception) {
                    $result['errors'][] = $exception->getMessage();
                }
            }

            if ($result['errors'] !== []) {
                return $result;
            }
        }

        $parsedPostCollection = collect($parsedPosts);
        $sourcePaths = $parsedPostCollection
            ->map(static fn (ParsedMarkdownBlogPost $parsedPost): string => $parsedPost->sourcePath)
            ->all();

        $missingMarkdownPosts = BlogPost::query()
            ->where('content_source', 'markdown')
            ->whereNotNull('content_source_path')
            ->whereNotIn('content_source_path', $sourcePaths)
            ->get();

        if ($missingMarkdownPosts->isNotEmpty() && (! $archiveMissing)) {
            $missingCount = $missingMarkdownPosts->count();
            $result['warnings'][] = "{$missingCount} markdown-managed blog posts no longer have source files. Re-run with --archive-missing to archive them.";
        }

        $persistChanges = function () use (&$result, $archiveMissing, $createOnly, $dryRun, $fallbackAuthorEmail, $forcePublish, $publishNow, $missingMarkdownPosts, $parsedPostCollection): void {
            $existingPostsBySourcePath = BlogPost::query()
                ->where('content_source', 'markdown')
                ->whereIn('content_source_path', $parsedPostCollection->map(static fn (ParsedMarkdownBlogPost $parsedPost): string => $parsedPost->sourcePath)->all())
                ->get()
                ->keyBy('content_source_path');

            $groupUuidsByKey = BlogPost::query()
                ->where('content_source', 'markdown')
                ->whereIn('content_source_key', $parsedPostCollection->map(static fn (ParsedMarkdownBlogPost $parsedPost): string => $parsedPost->familyKey)->unique()->all())
                ->get(['content_source_key', 'translation_group_uuid'])
                ->pluck('translation_group_uuid', 'content_source_key')
                ->filter()
                ->all();

            foreach ($parsedPostCollection as $parsedPost) {
                $existingPost = $existingPostsBySourcePath->get($parsedPost->sourcePath);

                if ($existingPost && $createOnly) {
                    $result['skipped']++;
                    $result['changes'][] = [
                        'action' => 'skipped_existing',
                        'source_path' => $parsedPost->sourcePath,
                        'title' => $parsedPost->title,
                        'locale' => $parsedPost->locale,
                    ];
                    $groupUuidsByKey[$parsedPost->familyKey] = $existingPost->translation_group_uuid;

                    continue;
                }

                $effectiveStatus = $this->determineStatus($parsedPost, $forcePublish, $publishNow);
                $effectivePublishedAt = $this->determinePublishedAt($parsedPost, $existingPost, $forcePublish, $publishNow);

                if (
                    $existingPost
                    && ($existingPost->content_source_hash === $parsedPost->sourceHash)
                    && $this->matchesPublicationState($existingPost, $effectiveStatus, $effectivePublishedAt)
                ) {
                    $result['unchanged']++;
                    $result['changes'][] = [
                        'action' => 'unchanged',
                        'source_path' => $parsedPost->sourcePath,
                        'title' => $parsedPost->title,
                        'locale' => $parsedPost->locale,
                    ];
                    $groupUuidsByKey[$parsedPost->familyKey] = $existingPost->translation_group_uuid;

                    continue;
                }

                $authorId = $this->resolveAuthorId($parsedPost, $fallbackAuthorEmail);
                $categoryId = $this->resolveCategoryId($parsedPost, $dryRun);
                $tagIds = $this->resolveTagIds($parsedPost, $dryRun);
                $translationGroupUuid = $groupUuidsByKey[$parsedPost->familyKey] ?? (string) Str::uuid();
                $groupUuidsByKey[$parsedPost->familyKey] = $translationGroupUuid;
                $action = $existingPost ? ($dryRun ? 'would_update' : 'updated') : ($dryRun ? 'would_create' : 'created');

                if ($dryRun) {
                    if ($existingPost) {
                        $result['updated']++;
                    } else {
                        $result['created']++;
                    }

                    $result['changes'][] = [
                        'action' => $action,
                        'source_path' => $parsedPost->sourcePath,
                        'title' => $parsedPost->title,
                        'locale' => $parsedPost->locale,
                    ];

                    continue;
                }

                $post = $existingPost ?? new BlogPost;

                $post->forceFill([
                    'translation_group_uuid' => $translationGroupUuid,
                    'locale' => $parsedPost->locale,
                    'content_source' => 'markdown',
                    'content_source_key' => $parsedPost->familyKey,
                    'content_source_path' => $parsedPost->sourcePath,
                    'content_source_hash' => $parsedPost->sourceHash,
                    'content_source_synced_at' => now(),
                    'title' => $parsedPost->title,
                    'slug' => $parsedPost->slug,
                    'excerpt' => $parsedPost->excerpt,
                    'body_markdown' => $parsedPost->bodyMarkdown,
                    'body_html' => Str::markdown($parsedPost->bodyMarkdown),
                    'featured_image' => $parsedPost->featuredImage,
                    'meta_title' => $parsedPost->metaTitle,
                    'meta_description' => $parsedPost->metaDescription,
                    'status' => $effectiveStatus,
                    'published_at' => $effectivePublishedAt,
                    'author_id' => $authorId,
                    'category_id' => $categoryId,
                ]);

                $post->save();
                $post->tags()->sync($tagIds);

                $existingPostsBySourcePath->put($parsedPost->sourcePath, $post);
                $result[$existingPost ? 'updated' : 'created']++;
                $result['changes'][] = [
                    'action' => $action,
                    'source_path' => $parsedPost->sourcePath,
                    'title' => $parsedPost->title,
                    'locale' => $parsedPost->locale,
                ];
            }

            if (! $archiveMissing) {
                return;
            }

            foreach ($missingMarkdownPosts as $missingMarkdownPost) {
                if ($missingMarkdownPost->status === PostStatus::Archived) {
                    continue;
                }

                $result['archived']++;
                $result['changes'][] = [
                    'action' => $dryRun ? 'would_archive' : 'archived',
                    'source_path' => (string) $missingMarkdownPost->content_source_path,
                    'title' => (string) $missingMarkdownPost->title,
                    'locale' => (string) $missingMarkdownPost->locale,
                ];

                if ($dryRun) {
                    continue;
                }

                $missingMarkdownPost->forceFill([
                    'status' => PostStatus::Archived,
                    'content_source_synced_at' => now(),
                ])->save();
            }
        };

        if ($dryRun) {
            $persistChanges();

            return $result;
        }

        DB::transaction($persistChanges);

        return $result;
    }

    /**
     * @param  array<int, ParsedMarkdownBlogPost>  $parsedPosts
     * @return array<int, string>
     */
    private function validateParsedPosts(array $parsedPosts): array
    {
        if ($parsedPosts === []) {
            return [];
        }

        $errors = [];
        $familyLocaleMap = [];
        $slugLocaleMap = [];
        $parsedPostsBySourcePath = collect($parsedPosts)->keyBy(static fn (ParsedMarkdownBlogPost $parsedPost): string => $parsedPost->sourcePath);

        foreach ($parsedPosts as $parsedPost) {
            $familyLocaleKey = "{$parsedPost->familyKey}|{$parsedPost->locale}";

            if (isset($familyLocaleMap[$familyLocaleKey])) {
                $errors[] = "Translation family [{$parsedPost->familyKey}] contains more than one [{$parsedPost->locale}] file.";
            }

            $familyLocaleMap[$familyLocaleKey] = true;

            $slugLocaleKey = "{$parsedPost->locale}|{$parsedPost->slug}";

            if (isset($slugLocaleMap[$slugLocaleKey])) {
                $errors[] = "Locale [{$parsedPost->locale}] has duplicate slug [{$parsedPost->slug}] across source files.";
            }

            $slugLocaleMap[$slugLocaleKey] = true;
        }

        /** @var Collection<int, BlogPost> $existingConflicts */
        $existingConflicts = BlogPost::query()
            ->where(function ($query) use ($parsedPosts): void {
                foreach ($parsedPosts as $parsedPost) {
                    $query->orWhere(function ($nestedQuery) use ($parsedPost): void {
                        $nestedQuery->where('locale', $parsedPost->locale)
                            ->where('slug', $parsedPost->slug);
                    });
                }
            })
            ->get(['id', 'locale', 'slug', 'content_source_path']);

        foreach ($existingConflicts as $existingConflict) {
            $parsedPost = $parsedPostsBySourcePath->first(
                static fn (ParsedMarkdownBlogPost $candidate): bool => ($candidate->locale === $existingConflict->locale)
                    && ($candidate->slug === $existingConflict->slug)
            );

            if (! $parsedPost) {
                continue;
            }

            if ($existingConflict->content_source_path === $parsedPost->sourcePath) {
                continue;
            }

            $errors[] = "Markdown post [{$parsedPost->sourcePath}] uses slug [{$parsedPost->slug}] for locale [{$parsedPost->locale}], but that URL is already taken by another blog post.";
        }

        return array_values(array_unique($errors));
    }

    private function validateEntryDependencies(ParsedMarkdownBlogPost $parsedPost, ?string $fallbackAuthorEmail): void
    {
        $this->resolveAuthorId($parsedPost, $fallbackAuthorEmail);
        $this->validateCategory($parsedPost);
        $this->validateTags($parsedPost);
    }

    private function determineStatus(ParsedMarkdownBlogPost $parsedPost, bool $forcePublish, bool $publishNow): PostStatus
    {
        if ($forcePublish || $publishNow) {
            return PostStatus::Published;
        }

        return $parsedPost->status;
    }

    private function determinePublishedAt(
        ParsedMarkdownBlogPost $parsedPost,
        ?BlogPost $existingPost,
        bool $forcePublish,
        bool $publishNow,
    ): ?CarbonInterface {
        if ($publishNow) {
            if (($parsedPost->publishedAt instanceof CarbonInterface) && $parsedPost->publishedAt->lessThanOrEqualTo(now())) {
                return $parsedPost->publishedAt;
            }

            if (($existingPost?->published_at instanceof CarbonInterface) && $existingPost->published_at->lessThanOrEqualTo(now())) {
                return $existingPost->published_at;
            }

            return now();
        }

        if (! $forcePublish) {
            return $parsedPost->publishedAt;
        }

        if ($parsedPost->publishedAt instanceof CarbonInterface) {
            return $parsedPost->publishedAt;
        }

        if ($existingPost?->published_at instanceof CarbonInterface) {
            return $existingPost->published_at;
        }

        return now();
    }

    private function matchesPublicationState(
        BlogPost $existingPost,
        PostStatus $effectiveStatus,
        ?CarbonInterface $effectivePublishedAt,
    ): bool {
        $existingStatus = $existingPost->status instanceof PostStatus
            ? $existingPost->status
            : PostStatus::tryFrom((string) $existingPost->status);

        if ($existingStatus !== $effectiveStatus) {
            return false;
        }

        return $this->timestampsMatch($existingPost->published_at, $effectivePublishedAt);
    }

    private function timestampsMatch(?CarbonInterface $first, ?CarbonInterface $second): bool
    {
        if (($first === null) || ($second === null)) {
            return $first === $second;
        }

        return $first->equalTo($second);
    }

    private function resolveAuthorId(ParsedMarkdownBlogPost $parsedPost, ?string $fallbackAuthorEmail): int
    {
        $authorEmail = $parsedPost->authorEmail ?: $fallbackAuthorEmail;

        if ($authorEmail) {
            $author = User::query()->where('email', $authorEmail)->first();

            if ($author) {
                return $author->id;
            }

            throw new RuntimeException("Markdown post [{$parsedPost->sourcePath}] references author_email [{$authorEmail}], but no matching user exists.");
        }

        $author = User::query()
            ->orderByDesc('is_admin')
            ->orderBy('id')
            ->first();

        if ($author) {
            return $author->id;
        }

        throw new RuntimeException("Markdown post [{$parsedPost->sourcePath}] could not resolve an author. Create a user first or set author_email in front matter.");
    }

    private function resolveCategoryId(ParsedMarkdownBlogPost $parsedPost, bool $dryRun): ?int
    {
        if (! $parsedPost->category) {
            return null;
        }

        $drafts = [[
            'name' => $parsedPost->category,
            'slug' => BlogEditorSupport::generateSlug($parsedPost->category),
        ]];

        if ($dryRun) {
            $this->validateCategory($parsedPost);

            return null;
        }

        $result = BlogEditorSupport::commitTaxonomyDrafts(
            drafts: $drafts,
            modelClass: BlogCategory::class,
            singularLabel: 'category',
            errorPathPrefix: "content.{$parsedPost->sourcePath}.category",
        );

        return $result['ids'][0] ?? null;
    }

    /**
     * @return array<int, int>
     */
    private function resolveTagIds(ParsedMarkdownBlogPost $parsedPost, bool $dryRun): array
    {
        if ($parsedPost->tags === []) {
            return [];
        }

        $drafts = collect($parsedPost->tags)
            ->map(static fn (string $tag): array => [
                'name' => $tag,
                'slug' => BlogEditorSupport::generateSlug($tag),
            ])
            ->all();

        if ($dryRun) {
            $this->validateTags($parsedPost);

            return [];
        }

        $result = BlogEditorSupport::commitTaxonomyDrafts(
            drafts: $drafts,
            modelClass: BlogTag::class,
            singularLabel: 'tag',
            errorPathPrefix: "content.{$parsedPost->sourcePath}.tags",
        );

        return $result['ids'];
    }

    private function validateCategory(ParsedMarkdownBlogPost $parsedPost): void
    {
        if (! $parsedPost->category) {
            return;
        }

        $this->wrapTaxonomyValidationException(
            fn (): array => BlogEditorSupport::validateTaxonomyDrafts(
                drafts: [[
                    'name' => $parsedPost->category,
                    'slug' => BlogEditorSupport::generateSlug($parsedPost->category),
                ]],
                modelClass: BlogCategory::class,
                singularLabel: 'category',
                errorPathPrefix: "content.{$parsedPost->sourcePath}.category",
            ),
            $parsedPost->sourcePath,
        );
    }

    private function validateTags(ParsedMarkdownBlogPost $parsedPost): void
    {
        if ($parsedPost->tags === []) {
            return;
        }

        $this->wrapTaxonomyValidationException(
            fn (): array => BlogEditorSupport::validateTaxonomyDrafts(
                drafts: collect($parsedPost->tags)
                    ->map(static fn (string $tag): array => [
                        'name' => $tag,
                        'slug' => BlogEditorSupport::generateSlug($tag),
                    ])
                    ->all(),
                modelClass: BlogTag::class,
                singularLabel: 'tag',
                errorPathPrefix: "content.{$parsedPost->sourcePath}.tags",
            ),
            $parsedPost->sourcePath,
        );
    }

    /**
     * @param  callable(): array<int, array{name:string,slug:string}>  $callback
     */
    private function wrapTaxonomyValidationException(callable $callback, string $sourcePath): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $firstMessage = collect($exception->errors())
                ->flatten()
                ->first();

            throw new RuntimeException("Markdown post [{$sourcePath}] has invalid taxonomy metadata. {$firstMessage}");
        }
    }

    private function resolveRootPath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '\\/');
        }

        return base_path(trim($path, '\\/'));
    }

    private function isAbsolutePath(string $path): bool
    {
        return (bool) preg_match('/^(?:[A-Za-z]:[\\\\\\/]|[\\\\\\/]{2}|\/)/', $path);
    }
}
