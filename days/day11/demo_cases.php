<?php

return [
    // Demo Case 1: Profile Impact
    // Shows how user profile (long-term) affects responses to the same question
    1 => [
        'name' => 'Profile Impact: Same Question, Different Contexts',
        'description' => 'Set different user profiles and ask the same question to see how long-term memory affects responses',
        'steps' => [
            // User 1: Beginner learning Python
            [
                'type' => 'set-profile',
                'data' => [
                    'name' => 'Sarah',
                    'role' => 'Beginner',
                    'expertise' => 'Just learning to code',
                    'preferences' => 'Prefers simple analogies, step-by-step explanations, lots of examples'
                ]
            ],
            [
                'type' => 'add-knowledge',
                'domain' => 'Programming',
                'items' => [
                    'User is completely new to programming',
                    'Prefers visual metaphors to explain concepts',
                    'Gets overwhelmed by too much technical jargon'
                ]
            ],
            [
                'type' => 'chat',
                'message' => 'Explain what a function is and why it\'s useful'
            ],
            // Add delay and switch profile
            [
                'type' => 'set-profile',
                'data' => [
                    'name' => 'Alex',
                    'role' => 'Senior Developer',
                    'expertise' => '10 years Python, Go, Rust',
                    'preferences' => 'Prefers concise technical explanations, patterns, performance implications'
                ]
            ],
            [
                'type' => 'add-knowledge',
                'domain' => 'Programming',
                'items' => [
                    'Expert in functional programming paradigms',
                    'Interested in design patterns and optimization',
                    'Prefers academic or formal explanations'
                ]
            ],
            [
                'type' => 'chat',
                'message' => 'Explain what a function is and why it\'s useful'
            ],
        ]
    ],

    // Demo Case 2: Task Switching
    // Shows how working memory isolates different tasks
    2 => [
        'name' => 'Task Switching: Isolated Working Memory',
        'description' => 'Start different tasks and see how working memory maintains task-specific context',
        'steps' => [
            // Set up long-term profile
            [
                'type' => 'set-profile',
                'data' => [
                    'name' => 'Morgan',
                    'role' => 'Product Manager',
                    'expertise' => 'API Design, User Experience'
                ]
            ],
            // Task 1: API Design
            [
                'type' => 'set-task',
                'name' => 'API Design for User Service',
                'description' => 'Design REST API endpoints for user authentication and profile management'
            ],
            [
                'type' => 'chat',
                'message' => 'What endpoints should our user service expose?'
            ],
            [
                'type' => 'chat',
                'message' => 'What about authentication headers?'
            ],
            // Complete and switch task
            [
                'type' => 'set-task',
                'name' => 'Database Schema Design',
                'description' => 'Design database schema for user and product tables'
            ],
            [
                'type' => 'chat',
                'message' => 'How should we structure the user table? (Assume we just discussed API endpoints)'
            ],
            [
                'type' => 'chat',
                'message' => 'What about indexing for performance?'
            ],
            [
                'type' => 'chat',
                'message' => 'Should we store the auth tokens in the database?'
            ],
        ]
    ],

    // Demo Case 3: Knowledge Retention
    // Shows how long-term memory preserves knowledge across sessions
    3 => [
        'name' => 'Knowledge Retention: Building Knowledge Base',
        'description' => 'Add knowledge to long-term memory and see how it influences understanding',
        'steps' => [
            // Build knowledge base about the company
            [
                'type' => 'set-profile',
                'data' => [
                    'name' => 'Jordan',
                    'role' => 'New Team Member',
                    'expertise' => 'JavaScript'
                ]
            ],
            [
                'type' => 'add-knowledge',
                'domain' => 'Company',
                'items' => [
                    'Company uses TypeScript for all frontend code',
                    'We deploy to AWS using Kubernetes',
                    'Team uses Next.js as the primary framework',
                    'All services communicate via gRPC internally'
                ]
            ],
            [
                'type' => 'add-knowledge',
                'domain' => 'Technology Stack',
                'items' => [
                    'Backend: Go microservices',
                    'Frontend: React with TypeScript',
                    'Database: PostgreSQL with migrations',
                    'Message queue: RabbitMQ',
                    'Monitoring: Prometheus + Grafana'
                ]
            ],
            [
                'type' => 'add-knowledge',
                'domain' => 'Development Practices',
                'items' => [
                    'All PRs require 2 approvals',
                    'Tests must have >80% coverage',
                    'Commits should be atomic and well-described'
                ]
            ],
            // Now ask questions that require this knowledge
            [
                'type' => 'chat',
                'message' => 'What\'s the best way to set up a new microservice in our stack?'
            ],
            [
                'type' => 'chat',
                'message' => 'How should we handle inter-service communication?'
            ],
            [
                'type' => 'chat',
                'message' => 'What\'s our deployment process?'
            ],
        ]
    ],
];
