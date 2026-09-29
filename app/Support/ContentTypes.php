<?php

namespace App\Support;

/**
 * Maps public URL sections to the content_type values they list.
 * See docs/FRONTEND.md §1 (route map) and docs/CONTENT_MODEL.md §4.
 */
class ContentTypes
{
    /**
     * section path key => definition.
     *
     * @var array<string, array{label: string, types: string[], nav: bool}>
     */
    public const SECTIONS = [
        'articles' => [
            'label' => 'Articles',
            'types' => ['news'],
            'nav' => true,
        ],
        'investigations' => [
            'label' => 'Investigations',
            'types' => ['investigation'],
            'nav' => true,
        ],
        'interviews' => [
            'label' => 'Interviews',
            'types' => ['interview'],
            'nav' => true,
        ],
        'opinions' => [
            'label' => 'Opinions',
            'types' => ['opinion'],
            'nav' => true,
        ],
        'multimedia' => [
            'label' => 'Multimedia',
            'types' => ['video', 'photo_story'],
            'nav' => false, // folded into Archive per DESIGN_SYSTEM.md §10
        ],
        'other' => [
            'label' => 'Other Work',
            'types' => ['other'],
            'nav' => false,
        ],
    ];

    /**
     * All content_type values, flattened.
     *
     * @return string[]
     */
    public static function allTypes(): array
    {
        $types = [];

        foreach (self::SECTIONS as $section) {
            $types = array_merge($types, $section['types']);
        }

        return $types;
    }

    /**
     * Human labels for content_type values (badges, filters, admin).
     *
     * @return array<string, string>
     */
    public const TYPE_LABELS = [
        'news' => 'News',
        'investigation' => 'Investigation',
        'interview' => 'Interview',
        'opinion' => 'Opinion',
        'video' => 'Video',
        'photo_story' => 'Photo Story',
        'other' => 'Other Work',
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Options for filter dropdowns: label => value (matching admin select order).
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        $options = [];

        foreach (self::SECTIONS as $section) {
            foreach ($section['types'] as $type) {
                $options[$type] = self::typeLabel($type);
            }
        }

        return $options;
    }

    /**
     * The section key (e.g. "investigations") that owns a given content_type.
     */
    public static function sectionForType(string $type): ?string
    {
        foreach (self::SECTIONS as $key => $section) {
            if (in_array($type, $section['types'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Section definitions that appear in the primary nav.
     *
     * @return array<string, array{label: string, types: string[], nav: bool}>
     */
    public static function navSections(): array
    {
        return array_filter(self::SECTIONS, fn (array $section) => $section['nav']);
    }
}
