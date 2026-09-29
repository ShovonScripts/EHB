<?php

namespace App\Http\Controllers;

use App\Support\SiteSettings;

/**
 * SEO.md §9 — dynamic robots.txt so the absolute Sitemap URL can be
 * emitted. Draft/preview content already 404s publicly via the
 * published() scope, so the only crawl-surface exclusions needed are
 * the admin panel and internal search result query strings — plus
 * anything the journalist has added under Admin → Settings.
 */
class RobotsController extends Controller
{
    public function __invoke()
    {
        // Draft/preview content already 404s publicly via the published()
        // scope, so the only crawl-surface exclusions needed are the admin
        // panel, internal search result query strings, and anything the
        // journalist has added in Admin → Settings.
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /search?',
        ];

        foreach (SiteSettings::extraRobotsRules() as $rule) {
            $lines[] = $rule;
        }

        // A site-wide noindex switch, mirrored here for crawlers that read
        // robots.txt but not the meta tag. The meta tag on every page is the
        // stronger signal; this is belt and braces.
        if (! SiteSettings::isIndexable()) {
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
