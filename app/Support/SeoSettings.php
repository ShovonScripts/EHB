<?php

namespace App\Support;

/**
 * The preset: every setting the site understands, declared in one place.
 *
 * ## Why a preset rather than a free-form key/value editor
 *
 * The settings table is a key/value store, and the admin used to be a plain
 * Key/Value form over it. That works for a row you already know about and is
 * close to unusable for finding one: the list showed raw snake_case keys, the
 * form gave no hint what a value was *for*, and the only way to set the share
 * image was to know a Media id and type it in. `default_og_image_media_id`
 * was mistyped once during development and the result was silent — every share
 * of every one of the 210 pieces lost its image.
 *
 * Declaring each setting here means the form, the list, the validation and the
 * docs are all derived from one list. Adding a setting is one array entry, and
 * a setting that exists but is not wired into the layout is visible as such
 * rather than being a row that quietly does nothing.
 *
 * ## Values live in the database, structure lives here
 *
 * The `key`, `type`, `label` and `group` below are code and change on deploy.
 * The `value` is a database row the journalist edits without a deploy. That
 * split is deliberate: an SEO setting should be changeable from the panel, but
 * the *shape* of the panel should not be editable by a form.
 *
 * A class rather than `config/seo.php` on purpose — a config file is cached by
 * `config:cache` and goes stale until someone remembers to clear it, which is
 * a failure mode this project has already hit once (a scheduled command that
 * referenced a command name which did not exist). A class is autoloaded, so a
 * typo in a key is a fatal error rather than a silently empty array.
 *
 * @phpstan-type Preset array{key: string, type: string, group: string, label: string, help: string, default: mixed}
 */
class SeoSettings
{
    public const GROUP_IDENTITY = 'Site identity';

    public const GROUP_SOCIAL = 'Social sharing';

    public const GROUP_CARDS = 'Content cards';

    public const GROUP_SEARCH = 'Search engines';

    public const GROUP_CONTACT = 'Contact';

    /**
     * The bucket for a row the preset does not declare.
     *
     * Deliberately not part of `groups()`: that list is the set of groups the
     * preset *declares*, and `SeoSettingsPresetTest` asserts every entry in it
     * has at least one setting. This is the label the panel puts on a row that
     * is in none of them, so the table, the filter and the model's
     * `setting_group` accessor cannot drift apart.
     */
    public const GROUP_UNKNOWN = 'Other';

    /**
     * Every setting, in the order they should appear in the panel.
     *
     * `type` is one of: text, textarea, image, toggle, email, handle,
     * retired. Anything else is a mistake and is asserted against in
     * SeoSettingsTest, so a typo fails a test rather than rendering nothing.
     *
     * @return array<int, Preset>
     */
    public static function preset(): array
    {
        return [
            // ── Identity ────────────────────────────────────────────
            [
                'key' => 'site_name',
                'type' => 'text',
                'group' => self::GROUP_IDENTITY,
                'label' => 'Site name',
                'help' => 'Used in the browser tab, the header, the footer, the RSS feed and the admin panel. On a single-author site this is normally your own name.',
                'default' => null,
            ],
            [
                'key' => 'site_tagline',
                'type' => 'text',
                'group' => self::GROUP_IDENTITY,
                'label' => 'Tagline',
                'help' => 'A short line under the site name. Used in the feed channel description and as the homepage title when no other title applies.',
                'default' => null,
            ],
            [
                'key' => 'default_seo_description',
                'type' => 'textarea',
                'group' => self::GROUP_IDENTITY,
                'label' => 'Default meta description',
                'help' => 'The fallback <meta name="description"> for any page without its own. Aim for 150–160 characters; search engines truncate beyond that. Individual pieces and pages override it.',
                'default' => null,
            ],

            // ── Social sharing ──────────────────────────────────────
            [
                'key' => 'default_og_image_media_id',
                'type' => 'image',
                'group' => self::GROUP_SOCIAL,
                'label' => 'Default share image',
                'help' => 'Used for any link that has no image of its own. 1200×630px is the safe size — smaller images render blurry in some feeds. Leave empty to use your profile photo.',
                'default' => null,
            ],
            [
                'key' => 'default_og_image_alt',
                'type' => 'text',
                'group' => self::GROUP_SOCIAL,
                'label' => 'Share image alt text',
                'help' => 'Describes the share image for anyone reading it with a screen reader. Keep it short — it is a label on a card, not a caption.',
                'default' => null,
            ],
            [
                'key' => 'twitter_handle',
                'type' => 'handle',
                'group' => self::GROUP_SOCIAL,
                'label' => 'X / Twitter handle',
                'help' => 'Your @handle, with or without the @. Emitted as twitter:site and twitter:creator so a shared link is attributed to you rather than to nobody.',
                'default' => null,
            ],

            // ── Content cards ──────────────────────────────────────
            [
                'key' => 'default_card_image_media_id',
                'type' => 'image',
                'group' => self::GROUP_CARDS,
                'label' => 'Default thumbnail image',
                'help' => 'Shown in the work grid for any piece that has no image of its own. Cards are cropped to 4:3, so a 1200×900px image suits them best. Leave empty and those cards show no picture — the share image above is a different setting and is not used here.',
                'default' => null,
            ],

            // ── Search engines ──────────────────────────────────────
            [
                'key' => 'indexable',
                'type' => 'toggle',
                'group' => self::GROUP_SEARCH,
                'label' => 'Allow search engines to index this site',
                'help' => 'On by default. Turning this off puts noindex on every page — useful while the site is still being built, and the fastest possible way to stop it appearing in results.',
                'default' => true,
            ],
            [
                'key' => 'google_site_verification',
                'type' => 'text',
                'group' => self::GROUP_SEARCH,
                'label' => 'Google site verification',
                'help' => 'The contents of the google-site-verification meta tag from Google Search Console. Paste the whole value; it is emitted verbatim.',
                'default' => null,
            ],
            [
                'key' => 'extra_robots_rules',
                'type' => 'textarea',
                'group' => self::GROUP_SEARCH,
                'label' => 'Extra robots.txt rules',
                'help' => 'One directive per line, e.g. "Disallow: /tmp/". Added to the rules this site already emits (/admin and /search? are always disallowed).',
                'default' => null,
            ],

            // ── Contact ─────────────────────────────────────────────
            [
                'key' => 'contact_email',
                'type' => 'email',
                'group' => self::GROUP_CONTACT,
                'label' => 'Public contact email',
                'help' => 'Shown in the footer and offered to search engines as part of the author\'s profile. Leave empty to hide it.',
                'default' => null,
            ],

            // ── Deliberately unsupported ────────────────────────────
            [
                'key' => 'google_analytics_id',
                'type' => 'retired',
                'group' => self::GROUP_SEARCH,
                'label' => 'Google Analytics',
                'help' => 'Not supported, and not an oversight. Analytics needs third-party script origins in the Content-Security-Policy, which would weaken the header for every visitor of a site that does not need the metric. A self-hosted, first-party script is the supported path.',
                'default' => null,
            ],
        ];
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return ['text', 'textarea', 'image', 'toggle', 'email', 'handle', 'retired'];
    }

    /** @return array<int, string> */
    public static function groups(): array
    {
        return [self::GROUP_IDENTITY, self::GROUP_SOCIAL, self::GROUP_CARDS, self::GROUP_SEARCH, self::GROUP_CONTACT];
    }

    /**
     * Preset entries for one group, in declaration order.
     *
     * @return array<int, Preset>
     */
    public static function group(string $group): array
    {
        return array_values(array_filter(
            self::preset(),
            fn (array $setting): bool => $setting['group'] === $group
        ));
    }

    /** The definition for one key, or null if the key is not in the preset. */
    public static function definition(string $key): ?array
    {
        foreach (self::preset() as $setting) {
            if ($setting['key'] === $key) {
                return $setting;
            }
        }

        return null;
    }

    public static function has(string $key): bool
    {
        return self::definition($key) !== null;
    }

    /**
     * A human label for a key, falling back to the key itself.
     *
     * The fallback matters: a row in the table that is not in the preset is
     * either legacy or a mistake, and showing its raw key is more useful than
     * hiding it.
     */
    public static function label(string $key): string
    {
        return self::definition($key)['label'] ?? $key;
    }

    public static function groupOf(string $key): ?string
    {
        return self::definition($key)['group'] ?? null;
    }

    public static function typeOf(string $key): ?string
    {
        return self::definition($key)['type'] ?? null;
    }

    /**
     * The value to fall back to when a row is absent or blank.
     *
     * Only meaningful for a toggle, where "not set" has to mean something. For
     * the rest a null default means "no value", which the accessors in
     * SiteSettings handle individually.
     */
    public static function defaultFor(string $key): mixed
    {
        return self::definition($key)['default'] ?? null;
    }
}
