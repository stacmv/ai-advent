<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;
use AiAdvent\Agent;
use AiAdvent\TerminalIO;

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

// Initialize terminal for UTF-8 handling
TerminalIO::initializeUTF8();

// Check for Yandex API key
if (empty($env['YANDEX_API_KEY'])) {
    echo "Error: YANDEX_API_KEY not found in .env\n";
    exit(1);
}
if (empty($env['YANDEX_FOLDER_ID'])) {
    echo "Error: YANDEX_FOLDER_ID not found in .env\n";
    exit(1);
}

$caseNum = $argv[1] ?? null;
$isDemo = false;

// Parse case number from --case=N format
if (strpos($caseNum, '--case=') === 0) {
    $caseNum = (int)str_replace('--case=', '', $caseNum);
    $isDemo = true;
} elseif ($caseNum === '--all') {
    $isDemo = true;
}

if ($isDemo) {
    require __DIR__ . '/demo_cases.php';
    // Run all demo cases
    foreach ($demoCases as $index => $case) {
        echo "=== Case " . ($index + 1) . " ===\n";
        echo "Prompt: " . $case['prompt'] . "\n";
        echo str_repeat("-", 80) . "\n";

        try {
            $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
            $agent = new Agent($client);
            $response = $agent->run($case['prompt']);

            echo $response . "\n\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }
} else {
    // Interactive mode
    echo "=== Day 6: Agent Architecture - Basic Agent ===\n";
    echo "Commands: 'exit' to quit\n";
    echo str_repeat("=", 80) . "\n\n";

    $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
    $agent = new Agent($client);

    while (true) {
        $input = TerminalIO::readLine("You: ");

        if ($input === 'exit' || $input === '') {
            break;
        }

        try {
            echo "\nAgent: ";
            $response = $agent->run($input);
            echo $response . "\n\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }

    echo "\nGoodbye!\n";
}