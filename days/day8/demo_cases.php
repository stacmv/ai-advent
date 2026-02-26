<?php

$demoCases = [
    [
        'name' => 'Short responses',
        'options' => [],
        'turns' => [
            'What is 2+2?',
            'What is the capital of France?',
            'What is PHP?',
        ]
    ],
    [
        'name' => 'Verbose responses',
        'options' => ['max_tokens' => 500],
        'turns' => [
            'Explain how the internet works in detail',
            'Tell me about the history of programming',
            'Describe cloud computing comprehensively',
        ]
    ],
    [
        'name' => 'Long prompt with normal responses',
        'options' => [],
        'turns' => [
            'Please analyze the following text and provide a detailed summary: Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.',
            'Based on your analysis, what are the key themes?',
            'Can you explain this in simpler terms?',
        ]
    ]
];