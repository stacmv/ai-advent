<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;
use AiAdvent\Agent;

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

// Check for Yandex API key
if (empty($env['YANDEX_API_KEY'])) {
    echo "Error: YANDEX_API_KEY not found in .env\n";
    exit(1);
}
if (empty($env['YANDEX_FOLDER_ID'])) {
    echo "Error: YANDEX_FOLDER_ID not found in .env\n";
    exit(1);
}

$historyFile = __DIR__ . '/../../storage/history_day7.json';
$caseNum = $argv[1] ?? null;
$isDemo = ($caseNum === '--case=1' || $caseNum === '1' || $caseNum === '--all');

if ($isDemo) {
    require __DIR__ . '/demo_cases.php';
    // Run each demo case as a separate session (to show history persistence)
    foreach ($demoCases as $index => $case) {
        echo "=== Demo Session " . ($index + 1) . " ===\n";

        // Create a unique history file for each demo case
        $sessionHistoryFile = __DIR__ . '/../../storage/history_day7_case' . ($index + 1) . '.json';

        try {
            $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
            $agent = new Agent($client, $sessionHistoryFile);

            echo "[History loaded: " . $agent->getMessageCount() . " messages]\n";
            echo "---\n";

            // Run all prompts in the case
            foreach ($case['turns'] as $turnNum => $prompt) {
                echo "Turn " . ($turnNum + 1) . " - You: " . $prompt . "\n";
                $response = $agent->run($prompt);
                echo "Agent: " . $response . "\n";
                echo "---\n";
            }

            echo "[Session saved: " . $agent->getMessageCount() . " total messages]\n\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }
} else {
    // Interactive mode
    echo "=== Day 7: Agent with Persistent Conversation History ===\n";
    echo "[History loaded: messages from storage/history_day7.json]\n";
    echo "Commands: 'exit' to quit, 'clear' to reset history, 'history' to show all messages\n";
    echo str_repeat("=", 80) . "\n\n";

    $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
    $agent = new Agent($client, $historyFile);

    echo "[Loaded " . $agent->getMessageCount() . " messages from history]\n\n";

    while (true) {
        echo "You: ";
        $input = trim(fgets(STDIN));

        if ($input === 'exit') {
            break;
        }
        if ($input === 'clear') {
            $agent->clearHistory();
            echo "[History cleared]\n\n";
            continue;
        }
        if ($input === 'history') {
            $messages = $agent->getMessages();
            if (empty($messages)) {
                echo "[No messages in history]\n\n";
            } else {
                foreach ($messages as $msg) {
                    echo "[{$msg['role']}] {$msg['text']}\n";
                }
                echo "[Total: " . count($messages) . " messages]\n\n";
            }
            continue;
        }

        if ($input === '') {
            continue;
        }

        try {
            echo "\nAgent: ";
            $response = $agent->run($input);
            echo $response . "\n";
            echo "[Total history: " . $agent->getMessageCount() . " messages]\n\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }

    echo "\nGoodbye! (" . $agent->getMessageCount() . " messages saved to history)\n";
}