<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$caseNum = $argv[1] ?? null;

if ($caseNum === '--case=1' || $caseNum === '1') {
    require __DIR__ . '/demo_cases.php';
    $case = $demoCases[0] ?? null;
    if (!$case) {
        echo "No demo case found\n";
        exit(1);
    }
    $prompt = $case['prompt'];
} else {
    $prompt = 'Tell me a fun fact about PHP';
}

echo "=== Day 1: Basic API Call ===\n";
echo "Prompt: $prompt\n";
echo str_repeat("=", 60) . "\n\n";

$apis = [
    'claude' => $_ENV['ANTHROPIC_API_KEY'] ?? '',
    'deepseek' => $_ENV['DEEPSEEK_API_KEY'] ?? '',
    'yandexgpt' => $_ENV['YANDEX_API_KEY'] ?? ''
];

$results = [];
foreach ($apis as $provider => $apiKey) {
    if (!$apiKey) {
        echo "[$provider] - Skipped (no API key)\n";
        continue;
    }

    echo "[$provider] Calling API...\n";
    $start = microtime(true);

    try {
        $client = new LLMClient(
            $provider,
            $apiKey,
            $provider === 'yandexgpt' ? ($_ENV['YANDEX_FOLDER_ID'] ?? '') : null
        );
        $response = $client->chat($prompt);
        $elapsed = microtime(true) - $start;

        $results[$provider] = [
            'response' => $response,
            'time' => $elapsed
        ];

        echo "Response (in {$elapsed:.2f}s):\n";
        echo substr($response, 0, 300);
        if (strlen($response) > 300) {
            echo "\n... [truncated]";
        }
        echo "\n\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

echo str_repeat("=", 60) . "\n";
echo "Summary:\n";
foreach ($results as $provider => $data) {
    echo sprintf("- %s: %.2fs\n", $provider, $data['time']);
}
