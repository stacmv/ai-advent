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

echo "=== Day 2: Response Format Control ===\n";
echo "Prompt: $prompt\n";
echo str_repeat("=", 80) . "\n\n";

$apis = [
    'claude' => $env['ANTHROPIC_API_KEY'] ?? '',
    'deepseek' => $env['DEEPSEEK_API_KEY'] ?? '',
    'yandexgpt' => $env['YANDEX_API_KEY'] ?? ''
];

echo "Calling each API with UNCONSTRAINED format...\n";
echo str_repeat("-", 80) . "\n";

$unconstrainedResults = [];
foreach ($apis as $provider => $apiKey) {
    if (!$apiKey) {
        echo "[$provider] - Skipped (no API key)\n";
        continue;
    }

    try {
        $client = new LLMClient(
            $provider,
            $apiKey,
            $provider === 'yandexgpt' ? ($env['YANDEX_FOLDER_ID'] ?? '') : null
        );
        $response = $client->chat($prompt);
        $unconstrainedResults[$provider] = substr($response, 0, 200);
        echo "[$provider] OK\n";
    } catch (Exception $e) {
        echo "[$provider] Error: " . $e->getMessage() . "\n";
    }
}

echo "\nCalling each API with CONSTRAINED format...\n";
echo str_repeat("-", 80) . "\n";

$constrainedResults = [];
foreach ($apis as $provider => $apiKey) {
    if (!$apiKey) {
        continue;
    }

    try {
        $client = new LLMClient(
            $provider,
            $apiKey,
            $provider === 'yandexgpt' ? ($env['YANDEX_FOLDER_ID'] ?? '') : null
        );

        $options = [
            'system' => 'Respond in valid JSON format only. {"fact": "..."}',
            'max_tokens' => 100,
            'stop' => '---'
        ];

        $response = $client->chat($prompt, $options);
        $constrainedResults[$provider] = substr($response, 0, 200);
        echo "[$provider] OK\n";
    } catch (Exception $e) {
        echo "[$provider] Error: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "COMPARISON TABLE\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($apis as $provider => $apiKey) {
    if (!$apiKey) {
        continue;
    }

    echo "API: $provider\n";
    echo str_repeat("-", 80) . "\n";

    $unc = $unconstrainedResults[$provider] ?? '(not available)';
    $con = $constrainedResults[$provider] ?? '(not available)';

    echo "UNCONSTRAINED:\n";
    echo $unc . "\n\n";

    echo "CONSTRAINED (JSON + max_tokens=100 + stop='---'):\n";
    echo $con . "\n\n";

    $charDiff = abs(strlen($unc) - strlen($con));
    echo "Note: Length difference = $charDiff chars\n";
    echo "\n";
}
