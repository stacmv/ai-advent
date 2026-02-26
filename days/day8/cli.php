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

// Helper to draw progress bar
function progressBar($percentage, $width = 30): string {
    $filled = (int)($percentage / 100 * $width);
    $bar = str_repeat('█', $filled) . str_repeat('░', $width - $filled);
    return sprintf("[%s] %3d%%", $bar, $percentage);
}

// Helper to display token stats
function displayTokenStats(array $result): void {
    echo "\n┌─────────────────────────────────────────────┐\n";
    echo "│ This turn:  in=" . str_pad($result['turn_input_tokens'], 4) . "  out=" . str_pad($result['turn_output_tokens'], 4) . "  tot=" . str_pad($result['turn_total_tokens'], 5) . "│\n";
    echo "│ Cumulative: in=" . str_pad($result['total_input_tokens'], 4) . "  out=" . str_pad($result['total_output_tokens'], 4) . "  tot=" . str_pad($result['total_tokens'], 5) . "│\n";
    echo "│ Context:    " . progressBar((int)(100 * $result['total_tokens'] / 4000), 28) . " │\n";
    echo "└─────────────────────────────────────────────┘\n";
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

$historyFile = __DIR__ . '/../../storage/history_day8.json';
$caseNum = $argv[1] ?? null;
$isDemo = ($caseNum === '--case=1' || $caseNum === '1' || $caseNum === '--all');

if ($isDemo) {
    require __DIR__ . '/demo_cases.php';
    // Run each demo case
    foreach ($demoCases as $index => $case) {
        echo "=== Demo Case " . ($index + 1) . ": " . $case['name'] . " ===\n";
        echo "Options: " . json_encode($case['options']) . "\n\n";

        // Create a unique history file for each case
        $sessionHistoryFile = __DIR__ . '/../../storage/history_day8_case' . ($index + 1) . '.json';

        try {
            $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
            $agent = new Agent($client, $sessionHistoryFile, $case['options']);

            // Run all turns
            foreach ($case['turns'] as $turnNum => $prompt) {
                echo "Turn " . ($turnNum + 1) . " - You: " . substr($prompt, 0, 60) . (strlen($prompt) > 60 ? '...' : '') . "\n";
                $result = $agent->run($prompt);

                echo "Agent: " . substr($result['text'], 0, 100) . "...\n";
                displayTokenStats($result);

                if ($agent->isApproachingLimit()) {
                    echo "\n⚠️  WARNING: Approaching token limit (80% used)!\n\n";
                }
            }

            echo str_repeat("=", 80) . "\n\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }
} else {
    // Interactive mode
    echo "=== Day 8: Token Counting and Limit Awareness ===\n";
    echo "Commands: 'exit' to quit, 'stats' to show stats, 'clear' to reset\n";
    echo str_repeat("=", 80) . "\n\n";

    $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
    $agent = new Agent($client, $historyFile);

    while (true) {
        echo "You: ";
        $input = trim(fgets(STDIN));

        if ($input === 'exit') {
            break;
        }
        if ($input === 'stats') {
            $stats = $agent->getStats();
            echo "\nToken Stats:\n";
            echo "  Total input:  " . $stats['total_input'] . "\n";
            echo "  Total output: " . $stats['total_output'] . "\n";
            echo "  Total:        " . $stats['total'] . "\n";
            echo "  Percentage:   " . $agent->getTokenPercentage() . "%\n\n";
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
            echo $result['text'] . "\n";
            displayTokenStats($result);

            if ($agent->isApproachingLimit()) {
                echo "\n⚠️  WARNING: Approaching token limit (80% used)!\n\n";
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }

    echo "\nGoodbye!\n";
}