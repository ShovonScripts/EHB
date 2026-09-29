<?php

/**
 * Beat dossiers — the cross-cutting editorial threads that run through the
 * 210 pieces, assigned per article in `published_work.php`.
 *
 * Why these are separate from the sections in `sections.php`: a section is
 * where the outlet filed the piece, a beat is what the piece is about. The
 * July uprising cases were filed under both "Crime & Justice" and "Bangladesh"
 * depending on the week, but they are one story. Sections answer "where was
 * this filed", beats answer "what is this about" — and the second is the axis
 * a reader actually wants once they have read a few pieces.
 *
 * `sort_order` is the order they appear in the UI; it is editorial, set by
 * prominence of the beat rather than by volume, so the nav does not churn
 * every time a piece is added.
 */
return [
    [
        'name' => 'Courts & Trials',
        'slug' => 'courts-trials',
        'sort_order' => 1,
        'description' => 'Bench reports, verdicts, hearings and the case files that stall behind them. The single largest thread in the archive: how long a case takes, and what that does to the people waiting on it.',
    ],
    [
        'name' => 'Victims & the Long Wait',
        'slug' => 'victims-witnesses',
        'sort_order' => 2,
        'description' => 'Reporting that centres the family rather than the docket — the mothers, fathers and children still waiting on a case years after the crime.',
    ],
    [
        'name' => 'Prisons, Bail & Remand',
        'slug' => 'prisons-bail',
        'sort_order' => 3,
        'description' => 'Who is held, on what, and for how long. Bail hearings, undertrial detention, prison conditions and e-bail.',
    ],
    [
        'name' => 'Policing & Investigation',
        'slug' => 'policing',
        'sort_order' => 4,
        'description' => 'DB and RAB conduct, custody deaths and allegations, arrest policy and the quality of the probes that follow.',
    ],
    [
        'name' => 'Press Freedom & Gag Orders',
        'slug' => 'press-freedom',
        'sort_order' => 5,
        'description' => 'Journalists charged, detained or killed; the Digital Security Act, the Official Secrets Act and the legal machinery used against reporting.',
    ],
    [
        'name' => 'July Uprising Cases',
        'slug' => 'july-uprising',
        'sort_order' => 6,
        'description' => 'The killings, disappearances and prosecutions that followed the July uprising — the largest single body of accountability reporting in the archive.',
    ],
    [
        'name' => 'Anti-Corruption & Graft',
        'slug' => 'anti-corruption',
        'sort_order' => 7,
        'description' => 'Purbachal plot scam, the BTRC and Evaly cases, money laundering and the long-running graft trials of senior officials.',
    ],
    [
        'name' => 'Narcotics & Drug Enforcement',
        'slug' => 'narcotics',
        'sort_order' => 8,
        'description' => 'Yaba and heroin hauls, trafficking routes, and the case backlog that makes drug enforcement unworkable.',
    ],
    [
        'name' => 'Fire, Arson & Building Safety',
        'slug' => 'fire-safety',
        'sort_order' => 9,
        'description' => 'Gutted buildings, fire licences, negligence and the question of who is accountable when a block burns.',
    ],
    [
        'name' => 'Human Rights & Civil Liberties',
        'slug' => 'human-rights',
        'sort_order' => 10,
        'description' => 'Fabricated cases, wrongful prosecution, land seizure and harassment — where legal process is used against people rather than for them.',
    ],
    [
        'name' => 'Transport & Road Safety',
        'slug' => 'road-safety',
        'sort_order' => 11,
        'description' => 'Bus and road deaths, the vehicles that kill, and the regulation that does or does not prevent them.',
    ],
    [
        'name' => 'Elections & Voting',
        'slug' => 'elections',
        'sort_order' => 12,
        'description' => 'Voting, vote counting, electoral commissions and the elections that are rigged or contested.',
    ],
];
