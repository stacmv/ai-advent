<?php

$demoCases = [
    [
        'name' => 'Long multi-turn conversation with compression',
        'enable_compression' => true,
        'options' => [],
        'turns' => [
            'Tell me a fun fact about space',
            'What else is interesting about planets?',
            'How do astronauts train for space?',
            'What about their diet in space?',
            'How do they communicate with Earth?',
            'What happens to their muscles in zero gravity?',
            'Can they sleep in space?',
            'What about weather in space?',
            'What is a black hole?',
            'Can we travel through black holes?',
        ]
    ],
    [
        'name' => 'Short conversation (compression not triggered)',
        'enable_compression' => true,
        'options' => [],
        'turns' => [
            'What is Python?',
            'What is PHP?',
            'What is JavaScript?',
        ]
    ]
];