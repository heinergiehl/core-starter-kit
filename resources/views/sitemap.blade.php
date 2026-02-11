<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
    @foreach ($entries as $entry)
        @php
            $localizedUrls = [];
            foreach ($supportedLocales as $locale) {
                $localizedUrls[$locale] = route(
                    $entry['route'],
                    array_merge($entry['parameters'], ['locale' => $locale])
                );
            }

            $canonicalUrl = $localizedUrls[$defaultLocale] ?? reset($localizedUrls);
        @endphp
        <url>
            <loc>{{ $canonicalUrl }}</loc>
            @foreach ($localizedUrls as $locale => $localizedUrl)
                <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $localizedUrl }}" />
            @endforeach
            <xhtml:link rel="alternate" hreflang="x-default" href="{{ $canonicalUrl }}" />
            <lastmod>{{ $entry['lastmod'] }}</lastmod>
        </url>
    @endforeach
</urlset>
