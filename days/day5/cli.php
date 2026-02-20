<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;

function loadEnv($filePath) {
    $config = [];
    if (!file_exists($filePath)) {
        return $config;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            $config[$key] = $value;
        }
    }
    return $config;
}

function calculateCost(string $model, int $totalTokens): float {
    $ratePerK = match (true) {
        str_contains($model, 'yandexgpt-lite') => 0.20,
        default                                => 0.40,
    };
    return round($totalTokens * $ratePerK / 1000, 4);
}

$env = loadEnv(__DIR__ . '/../../.env');

if (empty($env['YANDEX_API_KEY']) || empty($env['YANDEX_FOLDER_ID'])) {
    echo "Error: YANDEX_API_KEY and YANDEX_FOLDER_ID must be set in .env\n";
    exit(1);
}

$models = [
    ['tier' => 'СЛАБАЯ',  'label' => 'YandexGPT Lite',   'model' => 'yandexgpt-lite/latest'],
    ['tier' => 'СРЕДНЯЯ', 'label' => 'YandexGPT Pro',    'model' => 'yandexgpt/latest'],
    ['tier' => 'СИЛЬНАЯ', 'label' => 'YandexGPT Pro RC', 'model' => 'yandexgpt/rc'],
];

function runComparison(string $prompt, array $env, array $models) {
    $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);

    echo "=== Day 5: Версии моделей ===\n";
    echo "Prompt: \"" . substr($prompt, 0, 70) . (strlen($prompt) > 70 ? "..." : "") . "\"\n\n";

    // Phase 1: Run each model tier
    echo "--- Тестирование моделей ---\n";
    $results = [];

    foreach ($models as $modelInfo) {
        $tier = $modelInfo['tier'];
        $label = $modelInfo['label'];
        $model = $modelInfo['model'];

        $startTime = microtime(true);
        $metrics = $client->chatWithMetrics($prompt, ['model' => $model]);
        $elapsed = microtime(true) - $startTime;

        echo "[" . str_pad($tier, 6) . " - {$label}] ...  " . round($elapsed, 2) . "s\n";

        $results[] = [
            'tier' => $tier,
            'label' => $label,
            'model' => $model,
            'text' => $metrics['text'],
            'input_tokens' => $metrics['input_tokens'],
            'output_tokens' => $metrics['output_tokens'],
            'total_tokens' => $metrics['total_tokens'],
            'elapsed' => $elapsed,
            'cost' => calculateCost($model, $metrics['total_tokens']),
        ];
    }

    // Phase 2: Display metrics table
    echo "\n--- Метрики ---\n";
    echo str_pad("Уровень", 8) . " | " .
         str_pad("Модель", 15) . " | " .
         str_pad("Время", 7) . " | " .
         str_pad("Вх.", 5) . " | " .
         str_pad("Вых.", 5) . " | " .
         str_pad("Всего", 5) . " | " .
         "Стоимость\n";
    echo str_repeat("-", 75) . "\n";

    foreach ($results as $result) {
        echo str_pad($result['tier'], 8) . " | " .
             str_pad($result['label'], 15) . " | " .
             str_pad(round($result['elapsed'], 2) . "s", 7) . " | " .
             str_pad($result['input_tokens'], 5) . " | " .
             str_pad($result['output_tokens'], 5) . " | " .
             str_pad($result['total_tokens'], 5) . " | " .
             $result['cost'] . " ₽\n";
    }

    // Phase 3: Display responses
    echo "\n--- Ответы ---\n";
    foreach ($results as $result) {
        echo "[" . $result['tier'] . " - " . $result['label'] . "]\n";
        echo $result['text'] . "\n\n";
    }

    // Phase 4: AI-generated conclusions
    echo "=== Вывод моделей о себе ===\n";

    // Build summary for AI analysis
    $summaryPrompt = "Ты — аналитик AI-систем. Ниже приведены результаты тестирования трёх версий YandexGPT на одном и том же запросе.\n\n";
    $summaryPrompt .= "=== Запрос пользователя ===\n{$prompt}\n\n";
    $summaryPrompt .= "=== Результаты ===\n";

    foreach ($results as $result) {
        $summaryPrompt .= "[" . $result['tier'] . " - " . $result['label'] . "]\n";
        $summaryPrompt .= "Время: " . round($result['elapsed'], 2) . "s | ";
        $summaryPrompt .= "Токены: " . $result['input_tokens'] . " вх / " . $result['output_tokens'] . " вых / " . $result['total_tokens'] . " итого | ";
        $summaryPrompt .= "Стоимость: " . $result['cost'] . " ₽\n";
        $summaryPrompt .= "Ответ:\n" . $result['text'] . "\n\n";
    }

    $summaryPrompt .= "Сделай короткий вывод (3-5 предложений): в чём разница между моделями по качеству, скорости и стоимости? Какая модель лучше подходит для данного типа задачи?\n";

    // Get conclusions from all 3 models
    foreach ($results as $result) {
        $conclusionMetrics = $client->chatWithMetrics($summaryPrompt, ['model' => $result['model']]);
        echo "[" . $result['label'] . " о результатах]:\n";
        echo $conclusionMetrics['text'] . "\n\n";
    }
}

// Argument handling
$arg = $argv[1] ?? null;

if ($arg === '--all') {
    require __DIR__ . '/demo_cases.php';
    foreach ($demoCases as $idx => $case) {
        runComparison($case['prompt'], $env, $models);
        if ($idx < count($demoCases) - 1) {
            echo "\n" . str_repeat("=", 80) . "\n";
            echo "[Press Enter for next case...]\n";
            fgets(STDIN);
        }
    }
} elseif ($arg && str_starts_with($arg, '--case=')) {
    $caseNum = (int)substr($arg, 7);
    require __DIR__ . '/demo_cases.php';
    if (!isset($demoCases[$caseNum])) {
        echo "Error: Case {$caseNum} not found\n";
        exit(1);
    }
    $case = $demoCases[$caseNum];
    runComparison($case['prompt'], $env, $models);
} else {
    // Interactive mode
    echo "=== Day 5: Версии моделей ===\n";
    echo "Enter your prompt: ";
    $prompt = trim(fgets(STDIN));
    if (empty($prompt)) {
        echo "Error: Empty prompt\n";
        exit(1);
    }
    runComparison($prompt, $env, $models);
}