<?php

return [
    /*
    | Which competition categories a fencer can enter, keyed by age group.
    | Division events (D1A, DV2) are open to 13+ regardless of age bucket,
    | rating permitting. This is a sane v1 approximation, not the full
    | USA Fencing eligibility ruleset — refined over time.
    */
    'eligibility' => [
        'Y10' => ['Y10', 'Y12'],
        'Y12' => ['Y12', 'Y14'],
        'Y14' => ['Y14', 'CDT'],
        'Cadet' => ['CDT', 'JNR', 'D1A', 'DV2', 'OPEN'],
        'Junior' => ['JNR', 'CDT', 'D1A', 'DV2', 'OPEN'],
        'Senior' => ['JNR', 'D1A', 'DV2', 'OPEN'],
        'Vet' => ['VET', 'D1A', 'DV2', 'OPEN'],
    ],

    // An event contesting this many of the fencer's eligible categories is "high value".
    'multi_category_threshold' => 3,

    // Default drive radius (miles) when a fencer hasn't set one.
    'default_drive_radius' => 450,

    // Days before an event's start to nudge unregistered plan items. We don't
    // have true registration deadlines from AskFRED, so lead times encode the
    // norms: national events (NACs, JOs, Championships) close ~6 weeks out;
    // club/regional events typically close 1-2 weeks out.
    'reminder_lead_days' => [
        'national' => 45,
        'default' => 14,
    ],

    // Trip cost buckets on the budget tracker (key => column label).
    'expense_categories' => [
        'fees' => 'Fees',
        'coaching' => 'Coaching',
        'hotel' => 'Hotel',
        'travel' => 'Travel',
        'food' => 'Food',
    ],

    // Money columns are decimal(8,2); cap inputs so an over-large typo can't
    // overflow the column (a 500 on MySQL) — no real fencing season nears this.
    'max_money' => 999999.99,

    // Tier priority for conflict resolution (higher wins the weekend).
    'tier_rank' => [
        'nac' => 6,
        'home' => 5,
        'priority' => 4,
        'drive' => 3,
        'fly' => 2,
        'skip' => 1,
        'ineligible' => 0,
    ],

    /*
    | Structured goal types. Goals live in the goals table; these are the
    | labels for the type picker.
    */
    'goal_types' => [
        'rating' => 'Earn a rating',
        'qualify' => 'Qualify for a championship',
        'standing' => 'Build regional standing',
        'develop' => 'Get competition mileage',
    ],

    // Categories where letter ratings are realistically earned (strong fields).
    'rating_earning_categories' => ['D1A', 'DV2', 'OPEN'],

    /*
    | Path-aware qualification targets. The engine labels events that are ON
    | a target's qualification path (circuits, named qualifiers, and the
    | championship itself). It never computes "qualified" — those rules
    | shift season to season.
    */
    'qualify_targets' => [
        'jo' => [
            'label' => 'Junior Olympics',
            'path_circuits' => ['RJCC'],
            'categories' => ['JNR', 'CDT'],
            'qualifier_pattern' => '/junior\s+olympic.*qualif|jo\s+qualif/i',
            'championship_pattern' => '/junior\s+olympics/i',
        ],
        'summer_nationals' => [
            'label' => 'Summer Nationals',
            'path_circuits' => ['ROC', 'RYC', 'RJCC'],
            'categories' => [],
            'qualifier_pattern' => '/qualif|divisional/i',
            'championship_pattern' => '/summer\s+nationals/i',
        ],
    ],

    /*
    | State -> USA Fencing region. Approximate: regions are formally composed
    | of divisions, and a few states straddle regions (notably California,
    | where NorCal fences R1 and SoCal R4 — we default CA to R4). Used by the
    | AskFRED sync; correct individual events in the admin if needed.
    */
    'state_regions' => [
        // R1 — Northwest / mountain west
        'AK' => 'R1', 'WA' => 'R1', 'OR' => 'R1', 'ID' => 'R1', 'MT' => 'R1',
        'WY' => 'R1', 'UT' => 'R1', 'NV' => 'R1', 'HI' => 'R1',
        // R2 — Midwest
        'IL' => 'R2', 'IN' => 'R2', 'IA' => 'R2', 'KY' => 'R2', 'MI' => 'R2',
        'MN' => 'R2', 'OH' => 'R2', 'WI' => 'R2', 'ND' => 'R2', 'SD' => 'R2',
        // R3 — Northeast / mid-Atlantic north
        'CT' => 'R3', 'MA' => 'R3', 'ME' => 'R3', 'NH' => 'R3', 'NY' => 'R3',
        'NJ' => 'R3', 'PA' => 'R3', 'RI' => 'R3', 'VT' => 'R3',
        // R4 — Southwest / SoCal
        'AZ' => 'R4', 'CA' => 'R4', 'CO' => 'R4', 'NM' => 'R4',
        // R5 — South central / plains
        'TX' => 'R5', 'OK' => 'R5', 'AR' => 'R5', 'LA' => 'R5', 'KS' => 'R5',
        'MO' => 'R5', 'NE' => 'R5',
        // R6 — Southeast / mid-Atlantic south
        'AL' => 'R6', 'DC' => 'R6', 'DE' => 'R6', 'FL' => 'R6', 'GA' => 'R6',
        'MD' => 'R6', 'MS' => 'R6', 'NC' => 'R6', 'SC' => 'R6', 'TN' => 'R6',
        'VA' => 'R6', 'WV' => 'R6',
    ],
];
