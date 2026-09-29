<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ $channel['title'] }}</title>
    <link>{{ $channel['link'] }}</link>
    <description>{{ $channel['description'] }}</description>
    <language>en</language>
    <lastBuildDate>{{ ($items->first()?->published_at ?? now())->format(DATE_RSS) }}</lastBuildDate>
    <atom:link href="{{ $channel['selfUrl'] }}" rel="self" type="application/rss+xml"/>
@foreach($items as $item)
    <item>
        <title>{{ $item->title }}</title>
        <link>{{ url($item->publicPath()) }}</link>
        <guid isPermaLink="true">{{ url($item->publicPath()) }}</guid>
        <pubDate>{{ ($item->published_at ?? $item->created_at)->format(DATE_RSS) }}</pubDate>
        @if($item->publication)
            <source url="{{ url($item->publicPath()) }}">{{ $item->publication->name }}</source>
        @endif
        <description>{{ $item->summary }}</description>
    </item>
@endforeach
</channel>
</rss>
