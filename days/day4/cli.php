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
    $prompt = 'Write a short poem about a programmer who loves PHP';
}

echo "=== Day 4: Temperature Comparison ===\n";
echo "Prompt: $prompt\n";
echo str_repeat("=", 100) . "\n\n";

$apis = [
    'claude' => $env['ANTHROPIC_API_KEY'] ?? '',
    'deepseek' => $env['DEEPSEEK_API_KEY'] ?? '',
    'yandexgpt' => $env['YANDEX_API_KEY'] ?? ''
];

$temperatures = [0, 0.5, 1.0];

// Run all combinations
$results = [];
foreach ($temperatures as $temp) {
    foreach ($apis as $provider => $apiKey) {
        if (!$apiKey) {
            continue;
        }

        echo "[$provider @ temp=$temp] Calling API...\n";
        try {
            $client = new LLMClient(
                $provider,
                $apiKey,
                $provider === 'yandexgpt' ? ($env['YANDEX_FOLDER_ID'] ?? '') : null
            );
            $response = $client->chat($prompt, ['temperature' => $temp]);
            $results[$temp][$provider] = substr($response, 0, 250);
        } catch (Exception $e) {
            $results[$temp][$provider] = "Error: " . $e->getMessage();
        }
    }
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "3x3 TEMPERATURE COMPARISON TABLE\n";
echo str_repeat("=", 100) . "\n\n";

// Display in table format
foreach ($temperatures as $temp) {
    echo "TEMPERATURE: $temp\n";
    echo str_repeat("-", 100) . "\n";

    foreach ($apis as $provider => $apiKey) {
        if (!$apiKey) {
            continue;
        }

        echo "\n[$provider]:\n";
        $text = $results[$temp][$provider] ?? '(not run)';
        echo $text . "\n";
    }

    echo "\n";
}

echo str_repeat("=", 100) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 100) . "\n";

// Calculate some basic stats
foreach ($temperatures as $temp) {
    echo "\nTemperature $temp:\n";
    $avgLen = 0;
    $count = 0;

    foreach ($apis as $provider => $apiKey) {
        if (!$apiKey) {
            continue;
        }

        $text = $results[$temp][$provider] ?? '';
        if (!empty($text) && strpos($text, 'Error') === false) {
            $avgLen += strlen($text);
            $count++;
        }
    }

    if ($count > 0) {
        $avgLen = intval($avgLen / $count);
        echo "  Average response length: $avgLen characters\n";
    }
}

echo "\nObservation: Lower temperatures tend to produce more consistent/deterministic outputs,\n";
echo "while higher temperatures produce more creative/diverse responses.\n";
