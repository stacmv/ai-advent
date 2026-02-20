<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;

function loadEnv($filePath)
{
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
            if (
                (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
            ) {
                $value = substr($value, 1, -1);
            }
            $config[$key] = $value;
        }
    }
    return $config;
}

// --- ANSI color helpers ---
function c(string $code, string $text): string
{
    return "\033[{$code}m{$text}\033[0m";
}

function tierColor(string $tier): string
{
    return match ($tier) {
        'СЛАБАЯ'  => '32',   // green
        'СРЕДНЯЯ' => '33',   // yellow
        'СИЛЬНАЯ' => '35',   // magenta
        'ALICE'   => '36',   // cyan
        default   => '0',
    };
}

function colorTier(string $tier): string
{
    return c(tierColor($tier), $tier);
}

function colorLabel(string $tier, string $label): string
{
    return c(tierColor($tier), $label);
}

function bar(int $value, int $maxValue, int $width = 20): string
{
    if ($maxValue <= 0) {
        return str_repeat('░', $width);
    }
    $filled = (int)round($value / $maxValue * $width);
    $filled = min($filled, $width);
    return str_repeat('█', $filled) . str_repeat('░', $width - $filled);
}

function calculateCost(string $model, int $totalTokens): float
{
    $ratePerK = match (true) {
        str_contains($model, 'yandexgpt-lite') => 0.20,
        default                                => 0.40,
    };
    return round($totalTokens * $ratePerK / 1000, 4);
}

$env = loadEnv(__DIR__ . '/../../.env');

if (empty($env['YANDEX_API_KEY']) || empty($env['YANDEX_FOLDER_ID'])) {
    echo c('31', "Error: YANDEX_API_KEY and YANDEX_FOLDER_ID must be set in .env") . "\n";
    exit(1);
}

$models = [
    ['tier' => 'СЛАБАЯ',  'label' => 'YandexGPT Lite',   'model' => 'yandexgpt-lite/latest'],
    ['tier' => 'СРЕДНЯЯ', 'label' => 'YandexGPT Pro',    'model' => 'yandexgpt/latest'],
    ['tier' => 'СИЛЬНАЯ', 'label' => 'YandexGPT Pro RC', 'model' => 'yandexgpt/rc'],
    ['tier' => 'ALICE',   'label' => 'Alice AI LLM',     'model' => 'aliceai-llm/latest'],
];

function runComparison(string $prompt, array $env, array $models)
{
    $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);

    // Header
    echo "\n";
    echo c('1;37', "╔══════════════════════════════════════════════════════════════╗") . "\n";
    echo c('1;37', "║") . c('1;33', "           Day 5: Версии моделей                           ") . c('1;37', "║") . "\n";
    echo c('1;37', "╚══════════════════════════════════════════════════════════════╝") . "\n";

    // Prompt box
    $displayPrompt = mb_substr($prompt, 0, 56);
    if (mb_strlen($prompt) > 56) {
        $displayPrompt .= '...';
    }
    echo c('90', "┌──────────────────────────────────────────────────────────────┐") . "\n";
    echo c('90', "│") . " " . c('1;37', "Prompt: ") . c('37', $displayPrompt)
        . str_repeat(' ', max(0, 53 - mb_strlen($displayPrompt))) . c('90', "│") . "\n";
    echo c('90', "└──────────────────────────────────────────────────────────────┘") . "\n";
    echo "\n";

    // Phase 1: Run each model tier
    echo c('1;37', " ▸ Тестирование моделей") . "\n\n";
    $results = [];

    foreach ($models as $i => $modelInfo) {
        $tier = $modelInfo['tier'];
        $label = $modelInfo['label'];
        $model = $modelInfo['model'];
        $num = $i + 1;

        echo "   " . c('90', "[{$num}/" . count($models) . "]") . " " . colorLabel($tier, str_pad($label, 16)) . " ";
        echo c('90', "⏳ запрос...");

        $limitedPrompt = $prompt . "\n\nОтвечай кратко, не более 10 предложений.";
        $startTime = microtime(true);
        $metrics = $client->chatWithMetrics($limitedPrompt, ['model' => $model]);
        $elapsed = microtime(true) - $startTime;

        // Overwrite the "запрос..." with result
        echo "\r   " . c('90', "[{$num}/" . count($models) . "]") . " "
            . colorLabel($tier, str_pad($label, 16)) . " "
            . c('1;32', "✓") . " " . c('1;37', round($elapsed, 2) . "s") . "   \n";

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

    // Phase 2: Metrics table with box-drawing
    echo "\n";
    echo c('1;37', " ▸ Метрики") . "\n\n";

    // Find max values for bar charts
    $maxTime = max(array_column($results, 'elapsed'));
    $maxTokens = max(array_column($results, 'total_tokens'));

    // Table header
    echo c('90', "   ┌──────────┬──────────────────┬─────────┬───────┬───────┬───────┬───────────┐") . "\n";
    echo c('90', "   │") . c('1;37', " Уровень ") . c('90', "│")
        . c('1;37', " Модель           ") . c('90', "│")
        . c('1;37', "  Время  ") . c('90', "│")
        . c('1;37', "  Вх. ") . c('90', "│")
        . c('1;37', "  Вых. ") . c('90', "│")
        . c('1;37', " Всего ") . c('90', "│")
        . c('1;37', " Стоим-ть ") . c('90', "│") . "\n";
    echo c('90', "   ├──────────┼──────────────────┼─────────┼───────┼───────┼───────┼───────────┤") . "\n";

    foreach ($results as $i => $r) {
        $timeStr = str_pad(round($r['elapsed'], 2) . "s", 7);
        echo c('90', "   │") . " " . colorTier(str_pad($r['tier'], 8)) . " "
            . c('90', "│") . " " . colorLabel($r['tier'], str_pad($r['label'], 16)) . " "
            . c('90', "│") . " " . c('1;37', $timeStr) . " "
            . c('90', "│") . " " . c('37', str_pad($r['input_tokens'], 4, ' ', STR_PAD_LEFT)) . "  "
            . c('90', "│") . " " . c('37', str_pad($r['output_tokens'], 4, ' ', STR_PAD_LEFT)) . "  "
            . c('90', "│") . " " . c('1;37', str_pad($r['total_tokens'], 4, ' ', STR_PAD_LEFT)) . "  "
            . c('90', "│") . " " . c('33', str_pad($r['cost'], 6, ' ', STR_PAD_LEFT)) . " ₽ "
            . c('90', "│") . "\n";

        if ($i < count($results) - 1) {
            echo c('90', "   ├──────────┼──────────────────┼─────────┼───────┼───────┼───────┼───────────┤") . "\n";
        }
    }
    echo c('90', "   └──────────┴──────────────────┴─────────┴───────┴───────┴───────┴───────────┘") . "\n";

    // Bar charts
    echo "\n";
    echo c('1;37', " ▸ Сравнение (время / токены)") . "\n\n";

    foreach ($results as $r) {
        $colorCode = tierColor($r['tier']);
        echo "   " . c($colorCode, str_pad($r['label'], 16))
            . " " . c('90', "⏱") . " " . c($colorCode, bar((int)($r['elapsed'] * 100), (int)($maxTime * 100), 15))
            . " " . c('37', str_pad(round($r['elapsed'], 2) . "s", 6))
            . " " . c('90', "◆") . " " . c($colorCode, bar($r['total_tokens'], $maxTokens, 15))
            . " " . c('37', $r['total_tokens'] . " tok")
            . "\n";
    }

    // Phase 3: Display responses
    echo "\n";
    echo c('1;37', " ▸ Ответы моделей") . "\n";

    foreach ($results as $r) {
        echo "\n";
        $colorCode = tierColor($r['tier']);
        echo c($colorCode, "   ┌─ ") . c("1;{$colorCode}", $r['tier'] . " — " . $r['label']) . "\n";
        // Indent each line of the response
        $lines = explode("\n", $r['text']);
        foreach ($lines as $line) {
            echo c($colorCode, "   │") . " " . c('37', $line) . "\n";
        }
        echo c($colorCode, "   └" . str_repeat("─", 40)) . "\n";
    }

    // Phase 4: AI-generated conclusions
    echo "\n";
    echo c('1;37', "╔══════════════════════════════════════════════════════════════╗") . "\n";
    echo c('1;37', "║") . c('1;33', "           Вывод моделей о себе                            ") . c('1;37', "║") . "\n";
    echo c('1;37', "╚══════════════════════════════════════════════════════════════╝") . "\n";
    echo c('90', "   (Каждая модель анализирует реальные данные сравнения)") . "\n";

    // Build summary for AI analysis
    $summaryPrompt = "Ты — аналитик AI-систем. Ниже приведены результаты тестирования четырёх моделей Yandex на одном и том же запросе.\n\n";
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

    foreach ($results as $r) {
        echo "\n";
        $colorCode = tierColor($r['tier']);
        echo c($colorCode, "   ┌─ ") . c("1;{$colorCode}", $r['label'] . " о результатах") . "\n";
        echo c($colorCode, "   │") . " " . c('90', "⏳ анализ...");

        $conclusionMetrics = $client->chatWithMetrics($summaryPrompt, ['model' => $r['model']]);

        echo "\r" . str_repeat(' ', 40) . "\r"; // clear "анализ..."
        $lines = explode("\n", $conclusionMetrics['text']);
        foreach ($lines as $line) {
            echo c($colorCode, "   │") . " " . c('37', $line) . "\n";
        }
        echo c($colorCode, "   └" . str_repeat("─", 40)) . "\n";
    }

    echo "\n" . c('90', str_repeat("─", 62)) . "\n";
}

// Argument handling
$arg = $argv[1] ?? null;

if ($arg === '--all') {
    require __DIR__ . '/demo_cases.php';
    foreach ($demoCases as $idx => $case) {
        runComparison($case['prompt'], $env, $models);
        if ($idx < count($demoCases) - 1) {
            echo "\n" . c('1;90', str_repeat("═", 62)) . "\n";
            echo c('33', "   [Press Enter for next case...]") . "\n";
            fgets(STDIN);
        }
    }
} elseif ($arg && str_starts_with($arg, '--case=')) {
    $caseNum = (int)substr($arg, 7);
    require __DIR__ . '/demo_cases.php';
    if (!isset($demoCases[$caseNum])) {
        echo c('31', "Error: Case {$caseNum} not found") . "\n";
        exit(1);
    }
    $case = $demoCases[$caseNum];
    runComparison($case['prompt'], $env, $models);
} else {
    // Interactive mode
    echo "\n";
    echo c('1;37', "╔══════════════════════════════════════════════════════════════╗") . "\n";
    echo c('1;37', "║") . c('1;33', "           Day 5: Версии моделей                           ") . c('1;37', "║") . "\n";
    echo c('1;37', "╚══════════════════════════════════════════════════════════════╝") . "\n";
    echo c('1;37', " Enter your prompt: ");
    $prompt = trim(fgets(STDIN));
    if (empty($prompt)) {
        echo c('31', "Error: Empty prompt") . "\n";
        exit(1);
    }
    runComparison($prompt, $env, $models);
}