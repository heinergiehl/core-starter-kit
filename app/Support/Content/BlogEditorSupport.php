<?php

namespace App\Support\Content;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogEditorSupport
{
    /**
     * @var array<string, array<string, string>>
     */
    protected static array $taxonomyLookupCache = [];

    public static function generateSlug(?string $value): string
    {
        return Str::slug((string) $value);
    }

    public static function normalizeSlugInput(?string $value): string
    {
        return static::generateSlug($value);
    }

    public static function syncSlugFromSource(?string $sourceValue): string
    {
        return static::generateSlug($sourceValue);
    }

    public static function generateUniqueBlogPostSlug(
        string $locale,
        ?string $preferredSlug,
        ?string $fallbackTitle = null,
        ?int $ignorePostId = null
    ): string {
        $baseSlug = static::normalizeSlugInput($preferredSlug);

        if ($baseSlug === '') {
            $baseSlug = static::generateSlug($fallbackTitle);
        }

        if ($baseSlug === '') {
            $baseSlug = 'post';
        }

        $existingSlugs = BlogPost::query()
            ->forLocale($locale)
            ->when($ignorePostId, fn ($query, int $postId) => $query->whereKeyNot($postId))
            ->where(function ($query) use ($baseSlug): void {
                $query->where('slug', $baseSlug)
                    ->orWhere('slug', 'like', $baseSlug.'-%');
            })
            ->pluck('slug')
            ->all();

        if (! in_array($baseSlug, $existingSlugs, true)) {
            return $baseSlug;
        }

        $existingSlugMap = array_fill_keys($existingSlugs, true);
        $suffix = 2;

        do {
            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        } while (isset($existingSlugMap[$candidate]));

        return $candidate;
    }

    /**
     * @return array{slug:string,sync:bool}
     */
    public static function initializeSlugEditorState(?string $slugValue, ?string $sourceValue): array
    {
        $generatedSlug = static::generateSlug($sourceValue);
        $normalizedSlug = static::normalizeSlugInput($slugValue);

        if ($normalizedSlug === '') {
            return [
                'slug' => $generatedSlug,
                'sync' => true,
            ];
        }

        return [
            'slug' => $normalizedSlug,
            'sync' => $normalizedSlug === $generatedSlug,
        ];
    }

    /**
     * @return array{slug:string,sync:bool}
     */
    public static function updateSlugEditorState(?string $slugValue, ?string $sourceValue): array
    {
        $generatedSlug = static::generateSlug($sourceValue);
        $normalizedSlug = static::normalizeSlugInput($slugValue);

        if ($normalizedSlug === '') {
            return [
                'slug' => $generatedSlug,
                'sync' => true,
            ];
        }

        return [
            'slug' => $normalizedSlug,
            'sync' => $normalizedSlug === $generatedSlug,
        ];
    }

    public static function shouldAutoUpdateSlug(?string $currentSlug, ?string $previousSourceValue): bool
    {
        $currentSlug = (string) $currentSlug;

        if ($currentSlug === '') {
            return true;
        }

        return $currentSlug === static::generateSlug($previousSourceValue);
    }

    public static function describeSlugBehavior(?string $sourceValue, bool $syncEnabled, string $sourceLabel = 'name'): string
    {
        if (static::generateSlug($sourceValue) === '') {
            return "Start with the {$sourceLabel}. You can still type a custom slug any time.";
        }

        if ($syncEnabled) {
            return "Auto-sync is on. Change the {$sourceLabel} to update this slug, or edit the slug to make it custom.";
        }

        return "Custom slug locked in. Reset it to follow the {$sourceLabel} again.";
    }

    public static function renderSlugSyncState(string $sourceStatePath, bool $syncEnabled): HtmlString
    {
        $stateClasses = 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium tracking-[0.01em]';

        if (! $syncEnabled) {
            return new HtmlString(<<<HTML
<span class="{$stateClasses} border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
    <span class="h-1.5 w-1.5 rounded-full bg-slate-400 dark:bg-slate-500"></span>
    Custom slug
</span>
HTML);
        }

        $escapedStatePath = e($sourceStatePath);

        return new HtmlString(<<<HTML
<span class="{$stateClasses} border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-300">
    <span wire:loading.remove wire:target="{$escapedStatePath}" class="inline-flex items-center gap-1.5">
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
        Auto-sync on
    </span>
    <span wire:loading wire:target="{$escapedStatePath}" class="inline-flex items-center gap-1.5">
        <span class="h-2.5 w-2.5 animate-spin rounded-full border border-current border-t-transparent"></span>
        Syncing...
    </span>
</span>
HTML);
    }

    public static function renderTaxonomyDraftReviewGuidance(): HtmlString
    {
        return new HtmlString(<<<'HTML'
<div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-300">
    <p class="font-medium text-slate-900 dark:text-slate-100">Review tip</p>
    <p class="mt-1">Edit the name to keep auto-sync on. Edit the slug to switch that row to custom. Use reset if you want the generated slug back.</p>
</div>
HTML);
    }

    /**
     * @return array<int, string>
     */
    public static function parseBulkNames(?string $value): array
    {
        return collect(preg_split('/[\r\n,;]+/', (string) $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique(fn (string $item): string => Str::lower($item))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name:string,slug:string}>
     */
    public static function prepareTaxonomyDraftsFromBulkInput(?string $value): array
    {
        return collect(static::parseBulkNames($value))
            ->map(fn (string $name): array => [
                'name' => $name,
                'slug' => static::generateSlug($name),
            ])
            ->all();
    }

    public static function flushTaxonomyLookupCache(): void
    {
        static::$taxonomyLookupCache = [];
    }

    /**
     * @return array{created:int,existing:int,ids:array<int, int>}
     */
    public static function createTagRecordsFromBulkInput(string $input): array
    {
        return static::commitTaxonomyDrafts(
            static::prepareTaxonomyDraftsFromBulkInput($input),
            BlogTag::class,
            'tag',
        );
    }

    /**
     * @return array{created:int,existing:int,ids:array<int, int>}
     */
    public static function createCategoryRecordsFromBulkInput(string $input): array
    {
        return static::commitTaxonomyDrafts(
            static::prepareTaxonomyDraftsFromBulkInput($input),
            BlogCategory::class,
            'category',
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, array{name:string,slug:string}>
     */
    public static function validateTaxonomyDrafts(
        array $drafts,
        string $modelClass,
        string $singularLabel,
        string $errorPathPrefix = 'drafts'
    ): array {
        $normalizedDrafts = static::normalizeTaxonomyDrafts($drafts);
        $errors = [];

        if ($normalizedDrafts === []) {
            throw ValidationException::withMessages([
                $errorPathPrefix => "Add at least one {$singularLabel}.",
            ]);
        }

        $slugIndexes = [];

        foreach ($normalizedDrafts as $index => $draft) {
            if ($draft['name'] === '') {
                $errors["{$errorPathPrefix}.{$index}.name"] = 'Name is required.';
            }

            if ($draft['slug'] === '') {
                $errors["{$errorPathPrefix}.{$index}.slug"] = 'Slug is required.';

                continue;
            }

            $slugIndexes[$draft['slug']][] = $index;
        }

        foreach ($slugIndexes as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $errors["{$errorPathPrefix}.{$index}.slug"] = 'This slug is duplicated in the current list.';
            }
        }

        $existingRecords = $modelClass::query()
            ->whereIn('slug', array_keys($slugIndexes))
            ->get()
            ->keyBy(fn (Model $record): string => static::normalizeTaxonomySlug((string) $record->getAttribute('slug')));

        foreach ($normalizedDrafts as $index => $draft) {
            $existingRecord = $existingRecords->get($draft['slug']);

            if (! $existingRecord) {
                continue;
            }

            if (static::taxonomyNamesMatch((string) $existingRecord->getAttribute('name'), $draft['name'])) {
                continue;
            }

            $errors["{$errorPathPrefix}.{$index}.slug"] = static::buildExistingSlugConflictMessage(
                singularLabel: $singularLabel,
                existingName: (string) $existingRecord->getAttribute('name'),
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalizedDrafts;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array{created:int,existing:int,ids:array<int, int>}
     */
    public static function commitTaxonomyDrafts(
        array $drafts,
        string $modelClass,
        string $singularLabel,
        string $errorPathPrefix = 'drafts'
    ): array {
        $normalizedDrafts = static::validateTaxonomyDrafts($drafts, $modelClass, $singularLabel, $errorPathPrefix);

        $result = DB::transaction(function () use ($errorPathPrefix, $modelClass, $normalizedDrafts, $singularLabel): array {
            $created = 0;
            $existing = 0;
            $ids = [];

            foreach ($normalizedDrafts as $index => $draft) {
                try {
                    /** @var Model $record */
                    $record = $modelClass::query()->firstOrCreate(
                        ['slug' => $draft['slug']],
                        ['name' => $draft['name']]
                    );
                } catch (QueryException $exception) {
                    /** @var Model|null $record */
                    $record = $modelClass::query()
                        ->where('slug', $draft['slug'])
                        ->first();

                    if (! $record) {
                        throw $exception;
                    }
                }

                if (! static::taxonomyNamesMatch((string) $record->getAttribute('name'), $draft['name'])) {
                    throw ValidationException::withMessages([
                        "{$errorPathPrefix}.{$index}.slug" => static::buildExistingSlugConflictMessage(
                            singularLabel: $singularLabel,
                            existingName: (string) $record->getAttribute('name'),
                        ),
                    ]);
                }

                $ids[] = (int) $record->getKey();

                if ($record->wasRecentlyCreated) {
                    $created++;
                } else {
                    $existing++;
                }
            }

            return [
                'created' => $created,
                'existing' => $existing,
                'ids' => array_values(array_unique($ids)),
            ];
        });

        static::flushTaxonomyLookupCache();

        return $result;
    }

    /**
     * @param  array<int, mixed>  $drafts
     * @param  class-string<Model>  $modelClass
     * @return array{label:string,color:string,icon:string}
     */
    public static function getTaxonomyDraftStatusMeta(?string $name, ?string $slug, array $drafts, string $modelClass): array
    {
        $normalizedDrafts = static::normalizeTaxonomyDrafts($drafts);
        $existingTaxonomyNamesBySlug = static::getExistingTaxonomyNamesBySlug($normalizedDrafts, $modelClass);

        return static::resolveTaxonomyDraftStatusMeta(
            normalizedName: static::normalizeTaxonomyName($name),
            normalizedSlug: static::normalizeTaxonomySlug($slug),
            normalizedDrafts: $normalizedDrafts,
            existingTaxonomyNamesBySlug: $existingTaxonomyNamesBySlug,
        );
    }

    /**
     * @param  array<int, mixed>  $drafts
     * @param  class-string<Model>  $modelClass
     * @return array{total:int,new:int,reused:int,conflicts:int,needs_attention:int}
     */
    public static function summarizeTaxonomyDraftStatuses(array $drafts, string $modelClass): array
    {
        $normalizedDrafts = static::normalizeTaxonomyDrafts($drafts);
        $existingTaxonomyNamesBySlug = static::getExistingTaxonomyNamesBySlug($normalizedDrafts, $modelClass);

        $summary = [
            'total' => count($normalizedDrafts),
            'new' => 0,
            'reused' => 0,
            'conflicts' => 0,
            'needs_attention' => 0,
        ];

        foreach ($normalizedDrafts as $draft) {
            $status = static::resolveTaxonomyDraftStatusMeta(
                normalizedName: $draft['name'],
                normalizedSlug: $draft['slug'],
                normalizedDrafts: $normalizedDrafts,
                existingTaxonomyNamesBySlug: $existingTaxonomyNamesBySlug,
            )['label'];

            match ($status) {
                'Create new' => $summary['new']++,
                'Reuse existing' => $summary['reused']++,
                'Needs attention' => $summary['needs_attention']++,
                default => $summary['conflicts']++,
            };
        }

        return $summary;
    }

    /**
     * @param  array<int, mixed>  $drafts
     * @param  class-string<Model>  $modelClass
     */
    public static function renderTaxonomyDraftSummary(array $drafts, string $modelClass, string $singularLabel): HtmlString
    {
        $summary = static::summarizeTaxonomyDraftStatuses($drafts, $modelClass);
        $issues = $summary['conflicts'] + $summary['needs_attention'];
        $itemLabel = Str::plural($singularLabel, $summary['total']);
        $issueSummary = $issues > 0
            ? "{$issues} {$itemLabel} still need attention before saving."
            : 'Everything in this list is ready to save.';

        return new HtmlString(<<<HTML
<div class="grid gap-3 sm:grid-cols-4">
    <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Total</p>
        <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">{$summary['total']}</p>
    </div>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-3 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">New</p>
        <p class="mt-2 text-2xl font-semibold text-emerald-900 dark:text-emerald-100">{$summary['new']}</p>
    </div>
    <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-3 shadow-sm dark:border-sky-900/70 dark:bg-sky-950/40">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">Reuse</p>
        <p class="mt-2 text-2xl font-semibold text-sky-900 dark:text-sky-100">{$summary['reused']}</p>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 shadow-sm dark:border-amber-900/70 dark:bg-amber-950/40">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">Needs Fixes</p>
        <p class="mt-2 text-2xl font-semibold text-amber-900 dark:text-amber-100">{$issues}</p>
    </div>
</div>
<p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{$issueSummary}</p>
HTML);
    }

    /**
     * @param  array<int, mixed>  $drafts
     * @param  class-string<Model>  $modelClass
     */
    public static function describeTaxonomyDraftStatus(?string $name, ?string $slug, array $drafts, string $modelClass): string
    {
        return static::getTaxonomyDraftStatusMeta($name, $slug, $drafts, $modelClass)['label'];
    }

    /**
     * @param  array<int, mixed>  $drafts
     * @return array<int, array{name:string,slug:string}>
     */
    private static function normalizeTaxonomyDrafts(array $drafts): array
    {
        return collect($drafts)
            ->map(static fn (mixed $draft): array => [
                'name' => static::normalizeTaxonomyName(data_get($draft, 'name')),
                'slug' => static::normalizeTaxonomySlug(data_get($draft, 'slug')),
            ])
            ->values()
            ->all();
    }

    private static function normalizeTaxonomyName(mixed $value): string
    {
        return trim((string) $value);
    }

    private static function normalizeTaxonomySlug(mixed $value): string
    {
        return Str::lower(trim((string) $value));
    }

    private static function taxonomyNamesMatch(?string $left, ?string $right): bool
    {
        return Str::lower(trim((string) $left)) === Str::lower(trim((string) $right));
    }

    /**
     * @param  array<int, array{name:string,slug:string}>  $normalizedDrafts
     * @param  class-string<Model>  $modelClass
     * @return array<string, string>
     */
    private static function getExistingTaxonomyNamesBySlug(array $normalizedDrafts, string $modelClass): array
    {
        $slugs = collect($normalizedDrafts)
            ->pluck('slug')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($slugs === []) {
            return [];
        }

        $cacheKey = $modelClass.'|'.implode('|', $slugs);

        if (array_key_exists($cacheKey, static::$taxonomyLookupCache)) {
            return static::$taxonomyLookupCache[$cacheKey];
        }

        static::$taxonomyLookupCache[$cacheKey] = $modelClass::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->mapWithKeys(fn (Model $record): array => [
                static::normalizeTaxonomySlug((string) $record->getAttribute('slug')) => (string) $record->getAttribute('name'),
            ])
            ->all();

        return static::$taxonomyLookupCache[$cacheKey];
    }

    /**
     * @param  array<int, array{name:string,slug:string}>  $normalizedDrafts
     * @param  array<string, string>  $existingTaxonomyNamesBySlug
     * @return array{label:string,color:string,icon:string}
     */
    private static function resolveTaxonomyDraftStatusMeta(
        string $normalizedName,
        string $normalizedSlug,
        array $normalizedDrafts,
        array $existingTaxonomyNamesBySlug
    ): array {
        if (($normalizedName === '') || ($normalizedSlug === '')) {
            return [
                'label' => 'Needs attention',
                'color' => 'warning',
                'icon' => 'heroicon-m-pencil-square',
            ];
        }

        $slugOccurrences = collect($normalizedDrafts)
            ->pluck('slug')
            ->filter()
            ->countBy();

        if (($slugOccurrences[$normalizedSlug] ?? 0) > 1) {
            return [
                'label' => 'Conflict in list',
                'color' => 'danger',
                'icon' => 'heroicon-m-no-symbol',
            ];
        }

        $existingName = $existingTaxonomyNamesBySlug[$normalizedSlug] ?? null;

        if ($existingName === null) {
            return [
                'label' => 'Create new',
                'color' => 'success',
                'icon' => 'heroicon-m-plus-circle',
            ];
        }

        if (static::taxonomyNamesMatch($existingName, $normalizedName)) {
            return [
                'label' => 'Reuse existing',
                'color' => 'info',
                'icon' => 'heroicon-m-arrow-path',
            ];
        }

        return [
            'label' => 'Conflict with existing',
            'color' => 'danger',
            'icon' => 'heroicon-m-exclamation-triangle',
        ];
    }

    private static function buildExistingSlugConflictMessage(string $singularLabel, string $existingName): string
    {
        return "This slug already belongs to the existing {$singularLabel} [{$existingName}].";
    }
}
