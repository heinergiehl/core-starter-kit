<?php

namespace App\Http\Controllers\Blog;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Domain\Content\Support\ArticleContentRenderer;
use App\Support\Localization\LocalizedRouteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController
{
    public function __construct(
        private readonly LocalizedRouteService $localizedRouteService,
        private readonly ArticleContentRenderer $articleContentRenderer,
    ) {}

    public function index(Request $request): View
    {
        $locale = (string) app()->getLocale();
        $search = $request->input('search');
        $categorySlug = $request->input('category');
        $tagSlug = $request->input('tag');
        $localeLabels = (array) config('saas.locales.supported', ['en' => 'English']);
        $publishedCountsByLocale = BlogPost::query()
            ->published()
            ->selectRaw('locale, COUNT(*) as aggregate')
            ->groupBy('locale')
            ->pluck('aggregate', 'locale');
        $localeArchives = collect($this->localizedRouteService->supportedLocales())
            ->map(function (string $supportedLocale) use ($localeLabels, $publishedCountsByLocale, $locale): array {
                return [
                    'code' => strtoupper($supportedLocale),
                    'label' => (string) ($localeLabels[$supportedLocale] ?? strtoupper($supportedLocale)),
                    'count' => (int) ($publishedCountsByLocale[$supportedLocale] ?? 0),
                    'url' => route('blog.index', ['locale' => $supportedLocale]),
                    'is_current' => $supportedLocale === $locale,
                ];
            })
            ->all();

        $posts = BlogPost::query()
            ->published()
            ->forLocale($locale)
            ->with(['category', 'tags', 'author'])
            ->when($search, function (Builder $query, string $search) {
                $this->applySearchFilter($query, $search);
            })
            ->when($categorySlug, function (Builder $query, string $categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            ->when($tagSlug, function (Builder $query, string $tagSlug) {
                $query->whereHas('tags', fn ($q) => $q->where('slug', $tagSlug));
            })
            ->orderByDesc('published_at')
            ->paginate(10)
            ->withQueryString();

        $categories = BlogCategory::whereHas('posts', fn (Builder $query) => $query->published()->forLocale($locale))
            ->withCount(['posts' => fn (Builder $query) => $query->published()->forLocale($locale)])
            ->orderBy('name')
            ->get();

        $tags = BlogTag::whereHas('posts', fn (Builder $query) => $query->published()->forLocale($locale))
            ->withCount(['posts' => fn (Builder $query) => $query->published()->forLocale($locale)])
            ->orderBy('name')
            ->get();

        $activeCategory = $categorySlug ? BlogCategory::where('slug', $categorySlug)->first() : null;
        $activeTag = $tagSlug ? BlogTag::where('slug', $tagSlug)->first() : null;

        return view('blog.index', [
            'posts' => $posts,
            'categories' => $categories,
            'tags' => $tags,
            'search' => $search,
            'activeCategory' => $activeCategory,
            'activeTag' => $activeTag,
            'currentLocaleLabel' => (string) ($localeLabels[$locale] ?? strtoupper($locale)),
            'localeArchives' => $localeArchives,
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $post = BlogPost::query()
            ->forLocale($locale)
            ->where('slug', $slug)
            ->published()
            ->with(['category', 'tags', 'author', 'translations'])
            ->firstOrFail();

        $renderedArticle = filled($post->body_html)
            ? $this->articleContentRenderer->renderHtml((string) $post->body_html)
            : $this->articleContentRenderer->renderMarkdown((string) ($post->body_markdown ?? ''));

        return view('blog.show', [
            'post' => $post,
            'content' => $renderedArticle['html'],
            'toc' => count($renderedArticle['toc']) >= 3 ? $renderedArticle['toc'] : [],
        ]);
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $term = '%'.mb_strtolower(trim($search)).'%';
        $driver = $query->getConnection()->getDriverName();

        $query->where(function (Builder $nestedQuery) use ($driver, $term) {
            if ($driver === 'pgsql') {
                $nestedQuery->whereRaw('title ILIKE ?', [$term])
                    ->orWhereRaw('excerpt ILIKE ?', [$term]);

                return;
            }

            $nestedQuery->whereRaw('LOWER(title) LIKE ?', [$term])
                ->orWhereRaw('LOWER(excerpt) LIKE ?', [$term]);
        });
    }
}
