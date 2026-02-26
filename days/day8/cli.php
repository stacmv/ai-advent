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
    echo "Enter prompt: ";
    $prompt = trim(fgets(STDIN));
    if (empty($prompt)) {
        echo "No prompt provided\n";
        exit(1);
    }
}

echo "=== Day 8: Token Counting and Limit Awareness ===\n";
echo "Prompt: $prompt\n";
echo str_repeat("=", 80) . "\n\n";

// Available APIs
$providers = [];
if (!empty($env['ANTHROPIC_API_KEY'])) {
    $providers['claude'] = $env['ANTHROPIC_API_KEY'];
}
if (!empty($env['DEEPSEEK_API_KEY'])) {
    $providers['deepseek'] = $env['DEEPSEEK_API_KEY'];
}
if (!empty($env['YANDEX_API_KEY'])) {
    $providers['yandexgpt'] = $env['YANDEX_API_KEY'];
}

foreach ($providers as $provider => $apiKey) {
    echo "[{$provider}] Calling API...\n";
    $start = microtime(true);

    try {
        $client = new LLMClient(
            $provider,
            $apiKey,
            $provider === 'yandexgpt' ? ($env['YANDEX_FOLDER_ID'] ?? '') : null
        );
        $response = $client->chat($prompt);
        $elapsed = round(microtime(true) - $start, 2);

        echo "[{$provider}] ({$elapsed}s):\n";
        echo $response . "\n\n";
    } catch (Exception $e) {
        echo "[{$provider}] Error: " . $e->getMessage() . "\n\n";
    }
}

echo str_repeat("=", 80) . "\n";
echo "Done.\n";