<?php

$demoCases = [
    [
        'name' => 'Multi-turn conversation about a topic',
        'turns' => [
            'Tell me about the history of the internet',
            'Who invented the World Wide Web?',
            'What was the first website?',
        ]
    ],
    [
        'name' => 'Continuing context from previous turn',
        'turns' => [
            'I like cooking. What are some good starter recipes?',
            'Can you recommend something vegetarian?',
            'Do you have any tips for cooking risotto?',
        ]
    ]
];