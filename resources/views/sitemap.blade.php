<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @php
        $staticUrls = [
            url('/'),
            route('features'),
            route('pricing'),
            route('solutions.index'),
            route('docs.index'),
            route('blog.index'),
            route('roadmap'),
        ];
    @endphp
    @foreach ($staticUrls as $staticUrl)
        <url>
            <loc>{{ $staticUrl }}</loc>
            <lastmod>{{ $now->toAtomString() }}</lastmod>
        </url>
    @endforeach
    @foreach ($solutionSlugs as $solutionSlug)
        <url>
            <loc>{{ route('solutions.show', $solutionSlug) }}</loc>
            <lastmod>{{ $now->toAtomString() }}</lastmod>
        </url>
    @endforeach
    @foreach ($posts as $post)
        <url>
            <loc>{{ route('blog.show', $post->slug) }}</loc>
            <lastmod>{{ optional($post->updated_at ?? $post->published_at)->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
