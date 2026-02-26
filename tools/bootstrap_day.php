<?php

/**
 * Bootstrap a new day branch with boilerplate code
 *
 * Usage: php tools/bootstrap_day.php <day_number> [title]
 *
 * Creates:
 *   - Git branch dayN from main
 *   - days/dayN/cli.php with LLMClient boilerplate
 *   - days/dayN/demo_cases.php template
 *   - Makefile with day-specific targets
 */

$day = $argv[1] ?? null;
$title = $argv[2] ?? "Day {$day}";

if (!$day || !ctype_digit($day) || (int)$day < 1) {
    echo "Usage: php tools/bootstrap_day.php <day_number> [\"Day title\"]\n";
    echo "Example: php tools/bootstrap_day.php 5 \"Function Calling\"\n";
    exit(1);
}

$branch = "day{$day}";
$dayDir = __DIR__ . "/../days/day{$day}";

// Check if branch already exists
$existing = trim(shell_exec("git branch --list {$branch} 2>/dev/null") ?? '');
if ($existing) {
    echo "Error: Branch '{$branch}' already exists\n";
    exit(1);
}

// Check if directory already exists
if (is_dir($dayDir)) {
    echo "Error: Directory days/day{$day}/ already exists\n";
    exit(1);
}

echo "=== Bootstrapping Day {$day}: {$title} ===\n\n";

// Step 1: Create branch from main
echo "[*] Creating branch '{$branch}' from main...\n";
exec("git checkout main 2>&1", $out, $code);
if ($code !== 0) {
    echo "Error: Failed to checkout main\n";
    exit(1);
}
exec("git checkout -b {$branch} 2>&1", $out, $code);
if ($code !== 0) {
    echo "Error: Failed to create branch {$branch}\n";
    exit(1);
}

// Step 2: Create day directory
echo "[*] Creating days/day{$day}/...\n";
mkdir($dayDir, 0755, true);

// Step 3: Write cli.php
$cliContent = <<<'PHP'
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

PHP;

$cliContent .= "\necho \"=== Day {$day}: {$title} ===\\n\";\n";
$cliContent .= "echo \"Prompt: \$prompt\\n\";\n";
$cliContent .= "echo str_repeat(\"=\", 80) . \"\\n\\n\";\n\n";
$cliContent .= <<<'PHP'
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
PHP;

file_put_contents("{$dayDir}/cli.php", $cliContent);
echo "[+] Created days/day{$day}/cli.php\n";

// Step 4: Write demo_cases.php
$demoCasesContent = <<<'PHP'
<?php

$demoCases = [
    [
        'name' => 'Demo',
        'prompt' => 'TODO: Set your demo prompt here',
    ]
];
PHP;

file_put_contents("{$dayDir}/demo_cases.php", $demoCasesContent);
echo "[+] Created days/day{$day}/demo_cases.php\n";

// Step 5: Write Makefile
$makefile = ".PHONY: help install lint test demo record upload clean setup get-token next-day\n";
$makefile .= "\nhelp:\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\t@echo \"*** AI ADVENT - DAY {$day}: " . strtoupper($title) . " ***\"\n";
$makefile .= "\t@echo \"==========================================\"\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\t@echo \"[*] Available commands:\"\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\t@echo \"  Setup:\"\n";
$makefile .= "\t@echo \"    make install          Install composer dependencies\"\n";
$makefile .= "\t@echo \"    make setup            Copy .env.example to .env\"\n";
$makefile .= "\t@echo \"    make get-token        Get Yandex.Disk OAuth token\"\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\t@echo \"  Code Quality:\"\n";
$makefile .= "\t@echo \"    make lint             Check code style (PSR-12)\"\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\t@echo \"  Running:\"\n";
$makefile .= "\t@echo \"    make demo             Run Day {$day} demo\"\n";
$makefile .= "\t@echo \"    make test             Run Day {$day} interactively\"\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\t@echo \"  Recording & Upload:\"\n";
$makefile .= "\t@echo \"    make record           Start screen recording and run demo\"\n";
$makefile .= "\t@echo \"    make upload           Upload latest video for this day\"\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\t@echo \"  Bootstrap:\"\n";
$makefile .= "\t@echo \"    make next-day N=6     Bootstrap next day branch\"\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\t@echo \"  Utilities:\"\n";
$makefile .= "\t@echo \"    make clean            Remove recordings directory\"\n";
$makefile .= "\t@echo \"\"\n";
$makefile .= "\ninstall:\n\tcomposer install\n";
$makefile .= "\nsetup:\n";
$makefile .= "\t@if [ ! -f .env ]; then \\\n";
$makefile .= "\t\tcp .env.example .env; \\\n";
$makefile .= "\t\techo \"[+] Created .env from .env.example\"; \\\n";
$makefile .= "\t\techo \"[!] Please fill in your API keys in .env\"; \\\n";
$makefile .= "\telse \\\n";
$makefile .= "\t\techo \"[+] .env already exists\"; \\\n";
$makefile .= "\tfi\n";
$makefile .= "\nget-token:\n\tphp tools/get_yandex_token.php\n";
$makefile .= "\nlint:\n\tcomposer run lint\n";
$makefile .= "\ntest:\n";
$makefile .= "\t@echo \"Running Day {$day} CLI (interactive mode)...\"\n";
$makefile .= "\tphp days/day{$day}/cli.php\n";
$makefile .= "\ndemo:\n";
$makefile .= "\t@echo \"Running Day {$day} demo...\"\n";
$makefile .= "\tphp days/day{$day}/cli.php --case=1\n";
$makefile .= "\nrecord:\n";
$makefile .= "\t@echo \"Starting screen recording for Day {$day} demo...\"\n";
$makefile .= "\tphp tools/record.php --day={$day}\n";
$makefile .= "\nupload:\n";
$makefile .= "\t@echo \"Uploading latest Day {$day} video...\"\n";
$makefile .= "\tphp tools/upload_latest.php {$day}\n";
$makefile .= "\nnext-day:\n";
$nextDayUsage = "Usage: make next-day N=<day_number> [T=\\\"Title\\\"]";
$makefile .= "\t@if [ -z \"\$(N)\" ]; then echo \"{$nextDayUsage}\"; exit 1; fi\n";
$makefile .= "\tphp tools/bootstrap_day.php \$(N) \"\$(T)\"\n";
$makefile .= "\nclean:\n\trm -rf recordings/\n\t@echo \"[+] Cleaned recordings directory\"\n";

file_put_contents(__DIR__ . '/../Makefile', $makefile);
echo "[+] Created Makefile\n";

// Step 6: Commit
echo "\n[*] Committing boilerplate...\n";
exec("git add days/day{$day}/ Makefile 2>&1");
exec("git commit -m \"Bootstrap day {$day}: {$title}\" 2>&1", $commitOut, $commitCode);
if ($commitCode === 0) {
    echo "[+] Committed on branch '{$branch}'\n";
} else {
    echo "[-] Commit failed: " . implode("\n", $commitOut) . "\n";
}

echo "\n========================================\n";
echo "Day {$day} bootstrapped!\n";
echo "========================================\n";
echo "\nNext steps:\n";
echo "  1. Edit days/day{$day}/cli.php — implement the day's logic\n";
echo "  2. Edit days/day{$day}/demo_cases.php — set demo prompts\n";
echo "  3. make demo — test it\n";
echo "  4. make record — record the demo\n";
echo "  5. make upload — upload and get submission links\n";
