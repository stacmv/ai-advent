<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;

// Load .env directly
function loadEnv($filePath) {
    $config = [];
    if (!file_exists($filePath)) {
        return $config;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        $config[$key] = $value;
    }
    return $config;
}

$env = loadEnv(__DIR__ . '/../../.env');

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
    'claude' => $env['ANTHROPIC_API_KEY'] ?? '',
    'deepseek' => $env['DEEPSEEK_API_KEY'] ?? '',
    'yandexgpt' => $env['YANDEX_API_KEY'] ?? ''
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
            $provider === 'yandexgpt' ? ($env['YANDEX_FOLDER_ID'] ?? '') : null
        );
        $response = $client->chat($prompt);
        $elapsed = microtime(true) - $start;

        $results[$provider] = [
            'response' => $response,
            'time' => $elapsed
        ];

        echo sprintf("Response (in %.2fs):\n", $elapsed);
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
