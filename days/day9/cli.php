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

$historyFile = __DIR__ . '/../../storage/history_day9.json';
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
    // Run each demo case
    foreach ($demoCases as $index => $case) {
        echo "=== Demo Case " . ($index + 1) . ": " . $case['name'] . " ===\n";
        echo "Compression: " . ($case['enable_compression'] ? 'ENABLED' : 'DISABLED') . "\n\n";

        // Create a unique history file for each case
        $sessionHistoryFile = __DIR__ . '/../../storage/history_day9_case' . ($index + 1) . '.json';

        try {
            $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
            $agent = new Agent($client, $sessionHistoryFile, $case['options']);

            // Run all turns
            foreach ($case['turns'] as $turnNum => $prompt) {
                echo "Turn " . ($turnNum + 1) . " - You: " . substr($prompt, 0, 50) . (strlen($prompt) > 50 ? '...' : '') . "\n";
                $result = $agent->run($prompt);

                if ($result['was_compressed']) {
                    echo "[COMPRESSED: conversation summary created]\n";
                }

                echo "Agent: " . substr($result['text'], 0, 80) . "...\n";
                echo "  Tokens this turn: " . $result['turn_total_tokens'] . "  |  Cumulative: " . $result['total_tokens'] . "\n";

                if (!$case['enable_compression']) {
                    $storedMessages = count($agent->getMessages());
                    echo "  (Without compression, would need to send " . $storedMessages . " messages)\n";
                } else {
                    $storedMessages = count($agent->getMessages());
                    echo "  (Storing " . $storedMessages . " recent messages, older context in summary)\n";
                }
                echo "\n";
            }

            echo str_repeat("=", 80) . "\n\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }
} else {
    // Interactive mode
    echo "=== Day 9: Context Compression with Summary ===\n";
    echo "Commands: 'exit' to quit, 'summary' to show compressed context, 'clear' to reset\n";
    echo str_repeat("=", 80) . "\n\n";

    $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
    $agent = new Agent($client, $historyFile);

    while (true) {
        echo "You: ";
        $input = trim(fgets(STDIN));

        // Ensure input is properly decoded as UTF-8
        if (!mb_check_encoding($input, 'UTF-8')) {
            $input = mb_convert_encoding($input, 'UTF-8');
        }

        if ($input === 'exit') {
            break;
        }
        if ($input === 'summary') {
            $summary = $agent->getSummary();
            if ($summary) {
                echo "\n[Compressed Context Summary]\n";
                echo $summary . "\n\n";
            } else {
                echo "[No compression yet - full history is being used]\n\n";
            }
            continue;
        }
        if ($input === 'clear') {
            $agent->clearHistory();
            echo "[History cleared]\n\n";
            continue;
        }

        if ($input === '') {
            continue;
        }

        try {
            echo "\nAgent: ";
            $result = $agent->run($input);

            if ($result['was_compressed']) {
                echo "\n[Context compressed to summary]\n";
            }

            echo $result['text'] . "\n";
            echo "[Tokens this turn: " . $result['turn_total_tokens'] . "  |  Cumulative: " . $result['total_tokens'] . "]\n\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }

    echo "\nGoodbye!\n";
}