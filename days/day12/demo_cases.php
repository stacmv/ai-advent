<?php

$demoCases = [
    // Demo Case 0: Expertise Level Impact
    0 => [
        'name' => 'Expertise Impact: Same Question, Different Depths',
        'description' => 'Ask about dependency injection with beginner and expert profiles',
        'steps' => [
            ['type' => 'load-profile', 'profileName' => 'Beginner'],
            ['type' => 'question', 'question' => 'What is dependency injection and why is it useful?'],
            ['type' => 'load-profile', 'profileName' => 'Expert'],
            ['type' => 'question', 'question' => 'What is dependency injection and why is it useful?'],
        ]
    ],

    // Demo Case 1: Format Preferences
    1 => [
        'name' => 'Format Preferences: Bullets vs Prose',
        'description' => 'Ask about pagination with different format preferences',
        'steps' => [
            ['type' => 'load-profile', 'profileName' => 'Business'],
            ['type' => 'question', 'question' => 'How do I implement pagination in a REST API?'],
            ['type' => 'load-profile', 'profileName' => 'Casual'],
            ['type' => 'question', 'question' => 'How do I implement pagination in a REST API?'],
        ]
    ],

    // Demo Case 2: Restrictions & Includes
    2 => [
        'name' => 'Avoid & Always Include: Constraints in Action',
        'description' => 'See how "avoid" and "always_include" directives shape responses differently. Beginner profile avoids jargon and always includes simple examples. Expert profile uses technical terminology and code examples.',
        'steps' => [
            ['type' => 'compare', 'profileA' => 'Beginner', 'profileB' => 'Expert', 'question' => 'Explain how database indexing works'],
        ]
    ],
];

return $demoCases;
