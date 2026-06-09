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
        'Cadet' => ['CDT', 'JNR', 'D1A', 'DV2'],
        'Junior' => ['JNR', 'CDT', 'D1A', 'DV2'],
        'Senior' => ['JNR', 'D1A', 'DV2'],
        'Vet' => ['VET', 'D1A', 'DV2'],
    ],

    // An event contesting this many of the fencer's eligible categories is "high value".
    'multi_category_threshold' => 3,

    // Default drive radius (miles) when a fencer hasn't set one.
    'default_drive_radius' => 450,

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

    'goals' => [
        'earn_b' => 'Earn a B rating',
        'qualify_jo' => 'Qualify for Junior Olympics',
        'regional_standing' => 'Build regional standing',
        'explore' => 'Explore / just getting started',
    ],
];
