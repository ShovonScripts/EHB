<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RedirectForm
{
    /**
     * A site-relative path: an optional leading slash, then URL-safe path
     * characters, optionally ending in a slash.
     *
     * Built as a negated character class rather than an allowlist, because an
     * allowlist has to exclude `:` explicitly and that is easy to get wrong:
     * an earlier version of this pattern permitted `:` (it was needed for
     * ports), which let `https://example.com/about` and `javascript:alert(1)`
     * through — exactly the values the rule exists to reject.
     *
     * Denying the dangerous characters outright is the safer shape here:
     *
     * - `:` — a scheme separator, so no `https:`, `javascript:`, `data:`;
     * - `/` beyond the leading position — no protocol-relative `//evil.com`;
     * - `\` — browsers treat `/\evil.com` as protocol-relative too;
     * - whitespace and control characters — a header-splitting primitive, and
     *   `Location: /path\nX-Injected: 1` is a real response-splitting shape;
     * - `?` and `#` — a redirect target is a path, not a URL with a query or
     *   fragment, and allowing them invites the same ambiguity.
     *
     * `ApplyRedirects` prefixes the stored value with `/`, so what reaches the
     * browser is always same-origin. This rule is about the admin finding out
     * at save time, not about the redirect being an open redirect.
     */
    private const PATH_PATTERN = "#^/?[^\\s\\\\/?\#:]+(?:/[^\\s\\\\/?\#:]+)*/?$#";

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('from_path')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The old path, e.g. "old-article-title" or "/old-article-title". No domain.')
                    ->rules(['regex:'.self::PATH_PATTERN]),

                TextInput::make('to_path')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Where it should point, e.g. "about" or "/work/new-slug". Must be a path on this site, not a full URL.')
                    ->rules(['regex:'.self::PATH_PATTERN]),

                TextInput::make('status_code')
                    ->required()
                    ->numeric()
                    ->minValue(301)
                    ->maxValue(308)
                    ->default(301)
                    ->helperText('301 or 302 for a permanent or temporary move. Other 3xx codes are coerced to 301 on use.'),
            ]);
    }
}
