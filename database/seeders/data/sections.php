<?php

/**
 * The outlet's filing sections — the "Section" column of the author export.
 *
 * These are the sections The Daily Star itself files bylines under, kept
 * verbatim so a reader who knows the paper can navigate by the same names.
 * They are `categories` in the database and are browsable at /sections.
 *
 * `sort_order` follows the size of each section across the 210-piece archive,
 * so the busiest beat leads.
 */
return [
    [
        'name' => 'Crime & Justice',
        'slug' => 'crime-justice',
        'sort_order' => 1,
        'description' => 'Courtrooms, prisons, police and prosecutors — the core of the beat.',
    ],
    [
        'name' => 'Bangladesh',
        'slug' => 'bangladesh',
        'sort_order' => 2,
        'description' => 'National politics, administration and public life in Dhaka and beyond.',
    ],
    [
        'name' => 'News',
        'slug' => 'news',
        'sort_order' => 3,
        'description' => 'Breaking news and general reporting from the news desk.',
    ],
    [
        'name' => 'Accidents & Fires',
        'slug' => 'accidents-fires',
        'sort_order' => 4,
        'description' => 'Building fires, transport disasters and the negligence behind them.',
    ],
    [
        'name' => 'Politics',
        'slug' => 'politics',
        'sort_order' => 5,
        'description' => 'Party politics, elections and political violence.',
    ],
    [
        'name' => 'Transport',
        'slug' => 'transport',
        'sort_order' => 6,
        'description' => 'Road, rail and river transport, and the regulation behind it.',
    ],
    [
        'name' => 'Health',
        'slug' => 'health',
        'sort_order' => 7,
        'description' => 'Public health, hospitals and health policy.',
    ],
    [
        'name' => 'Culture',
        'slug' => 'culture',
        'sort_order' => 8,
        'description' => 'Arts, music and life on campus.',
    ],
];
