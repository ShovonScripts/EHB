<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach($entries as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
        <lastmod>{{ $entry['lastmod']->format('c') }}</lastmod>
        @foreach($entry['images'] as $image)
            <image:image>
                <image:loc>{{ $image['loc'] }}</image:loc>
                <image:title>{{ $image['title'] }}</image:title>
            </image:image>
        @endforeach
    </url>
@endforeach
</urlset>
