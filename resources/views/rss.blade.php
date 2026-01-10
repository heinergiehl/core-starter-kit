<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title><![CDATA[{{ config('app.name') }} Blog]]></title>
        <link>{{ url('/') }}</link>
        <description><![CDATA[Product updates, engineering notes, and SaaS playbooks.]]></description>
        <lastBuildDate>{{ optional($updatedAt)->toRfc2822String() }}</lastBuildDate>
        @foreach ($posts as $post)
            <item>
                <title><![CDATA[{{ $post->title }}]]></title>
                <link>{{ route('blog.show', $post->slug) }}</link>
                <guid>{{ route('blog.show', $post->slug) }}</guid>
                <pubDate>{{ optional($post->published_at)->toRfc2822String() }}</pubDate>
                <description><![CDATA[{{ $post->excerpt ?? '' }}]]></description>
            </item>
        @endforeach
    </channel>
</rss>
