<?php

/**
 * Upload latest video for a specific day
 *
 * Usage: php tools/upload_latest.php [day_number]
 */

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

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

$env = loadEnv(__DIR__ . '/../.env');

// Get day from command line or detect from git branch
$day = $argv[1] ?? null;

if (!$day) {
    // Try to detect from git branch
    $branch = trim(shell_exec('git branch --show-current 2>/dev/null') ?? '');

    if (preg_match('/day(\d)/', $branch, $matches)) {
        $day = $matches[1];
    } else {
        echo "Error: Could not determine day. Usage: php tools/upload_latest.php [day]\n";
        exit(1);
    }
}

if (!ctype_digit($day) || (int)$day < 1) {
    echo "Error: Day must be a positive number\n";
    exit(1);
}

echo "=== Upload Latest Video for Day {$day} ===\n\n";

// Find latest video for this day
$recordingsDir = __DIR__ . '/../recordings';
if (!is_dir($recordingsDir)) {
    echo "Error: No recordings directory found\n";
    exit(1);
}

$files = scandir($recordingsDir, SCANDIR_SORT_DESCENDING);
$dayVideos = array_filter($files, function ($f) use ($day) {
    return preg_match("/day{$day}_.*\.mp4$/", $f);
});

if (empty($dayVideos)) {
    echo "Error: No recordings found for day {$day}\n";
    echo "Available: recordings/\n";
    exit(1);
}

// Get the first (latest) file
$latestFile = reset($dayVideos);
$filePath = "{$recordingsDir}/{$latestFile}";

echo "[*] Latest video for day {$day}: {$latestFile}\n";
echo "[*] Size: " . formatBytes(filesize($filePath)) . "\n";
echo "[*] Modified: " . date('Y-m-d H:i:s', filemtime($filePath)) . "\n\n";

// Check for token
$yandexToken = $env['YANDEX_DISK_TOKEN'] ?? '';

// Try to auto-generate token if missing but client credentials exist
$clientId = $env['YANDEX_DISK_CLIENT_ID'] ?? ($env['YANDEX_CLIENT_ID'] ?? '');
if (!$yandexToken && !empty($clientId)) {
    echo "[*] Token missing, attempting to generate with credentials...\n";
    $tokenProcess = new Process(['php', __DIR__ . '/get_yandex_token.php']);
    $tokenProcess->setTimeout(120);
    $tokenProcess->setEnv($env);
    $tokenProcess->run();

    if ($tokenProcess->isSuccessful()) {
        // Reload .env to get the new token
        $env = loadEnv(__DIR__ . '/../.env');
        $yandexToken = $env['YANDEX_DISK_TOKEN'] ?? '';
        echo "[+] Token generated successfully\n\n";
    } else {
        echo "[-] Token generation failed\n";
        echo $tokenProcess->getErrorOutput();
        echo "\nRun: php tools/get_yandex_token.php\n";
        exit(1);
    }
}

if (!$yandexToken) {
    echo "Error: YANDEX_DISK_TOKEN not set in .env\n";
    echo "Get a token: php tools/get_yandex_token.php\n";
    exit(1);
}

echo "[*] Uploading to Yandex.Disk...\n";

$uploader = require __DIR__ . '/upload_yandex.php';
$uploadResult = $uploader($filePath, $latestFile, $yandexToken);

if (isset($uploadResult['error'])) {
    echo "[-] Upload failed: " . $uploadResult['error'] . "\n";
    exit(1);
}

echo "\n[+] Upload successful!\n";

// Build submission message
$githubRepo = $env['GITHUB_REPO_URL'] ?? 'https://github.com/stacmv/ai-advent';
$codeLink = rtrim($githubRepo, '/') . "/tree/day{$day}";
$videoLink = $uploadResult['shareLink'] ?? $uploadResult['path'] ?? '(not available)';

echo "\n========================================\n";
echo "Код: {$codeLink}\n";
echo "Видео: {$videoLink}\n";
echo "========================================\n";

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
