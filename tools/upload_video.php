<?php

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

echo "=== Yandex.Disk Video Upload Tool ===\n\n";

// Get file from command line or list available files
$fileName = $argv[1] ?? null;

if (!$fileName) {
    // List available recordings
    $recordingsDir = __DIR__ . '/../recordings';
    if (!is_dir($recordingsDir)) {
        echo "No recordings directory found\n";
        exit(1);
    }

    $files = scandir($recordingsDir, SCANDIR_SORT_DESCENDING);
    $mp4Files = array_filter($files, function ($f) {
        return strpos($f, '.mp4') !== false;
    });

    if (empty($mp4Files)) {
        echo "No MP4 files found in recordings/ directory\n";
        exit(1);
    }

    echo "Available recordings:\n";
    foreach ($mp4Files as $i => $file) {
        $filePath = "{$recordingsDir}/{$file}";
        $size = filesize($filePath);
        $sizeStr = formatBytes($size);
        $mtime = filemtime($filePath);
        $dateStr = date('Y-m-d H:i:s', $mtime);
        echo sprintf("  [%d] %s (%s, %s)\n", $i + 1, $file, $sizeStr, $dateStr);
    }

    echo "\nSelect file to upload (1-" . count($mp4Files) . ") or file name: ";
    $input = trim(fgets(STDIN));

    if (is_numeric($input) && $input >= 1 && $input <= count($mp4Files)) {
        $mp4Files = array_values($mp4Files);
        $fileName = $mp4Files[$input - 1];
    } else {
        $fileName = $input;
    }
}

// Construct file path
if (strpos($fileName, '/') === false && strpos($fileName, '\\') === false) {
    $filePath = __DIR__ . '/../recordings/' . $fileName;
} else {
    $filePath = $fileName;
}

// Check if file exists
if (!file_exists($filePath)) {
    echo "Error: File not found: {$filePath}\n";
    exit(1);
}

$fileName = basename($filePath);

echo "Uploading: {$fileName}\n";
echo "Path: {$filePath}\n";
echo "Size: " . formatBytes(filesize($filePath)) . "\n\n";

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
$uploadResult = $uploader($filePath, $fileName, $yandexToken);

if (isset($uploadResult['error'])) {
    echo "[-] Upload failed: " . $uploadResult['error'] . "\n";
    exit(1);
}

echo "\n[+] Upload successful!\n";

if (isset($uploadResult['shareLink'])) {
    echo "[+] Public link: " . $uploadResult['shareLink'] . "\n";
} elseif (isset($uploadResult['path'])) {
    echo "[+] Uploaded to: " . $uploadResult['path'] . "\n";
}

echo "[+] Done!\n";

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
