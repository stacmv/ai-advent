<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

// Load .env directly - simple parser
function loadEnv($filePath) {
    $config = [];
    if (!file_exists($filePath)) {
        return $config;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            $config[$key] = $value;
        }
    }

    return $config;
}

// Load environment from .env file
$env = loadEnv(__DIR__ . '/../.env');
if (empty($env['YANDEX_API_KEY']) || empty($env['YANDEX_FOLDER_ID'])) {
    // Try to load from .env.example if .env doesn't exist
    $env = array_merge(loadEnv(__DIR__ . '/../.env.example'), $env);
}

// Parse arguments
$day = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--day=') === 0) {
        $day = str_replace('--day=', '', $arg);
        break;
    }
}

if (!$day || !in_array($day, ['1', '2', '3', '4'])) {
    echo "Usage: php tools/record.php --day=<1|2|3|4>\n";
    exit(1);
}

echo "=== AI Advent Recording Orchestration (Day {$day}) ===\n\n";

// Create recordings directory
if (!is_dir(__DIR__ . '/../recordings')) {
    mkdir(__DIR__ . '/../recordings', 0755, true);
}

$timestamp = date('Y-m-d_His');
$recordingFile = __DIR__ . "/../recordings/day{$day}_{$timestamp}.mp4";
$compressedFile = __DIR__ . "/../recordings/day{$day}_{$timestamp}_compressed.mp4";

// Step 0: Prepare recording area
echo "[*] Recording will capture top-left 1200x700 area for 20 seconds\n";
echo "[*] Move terminal to TOP-LEFT corner and make sure it's FOCUSED\n";
echo "[*] When ready, press ENTER to start:\n";
echo "    ";

// Wait for user confirmation
fgets(STDIN);

echo "\n[+] Starting in 3 seconds...\n";
sleep(3);

echo "1. Starting screen capture...\n";
echo "   Output: $recordingFile\n";
echo "   Area: 1200x700 (top-left corner)\n";

// Detect OS and use appropriate screen capture
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

if ($isWindows) {
    // Windows GDI screen grab with ffmpeg - capture terminal window area (top-left 1200x700)
    // This captures just the terminal instead of entire desktop
    $ffmpegCmd = [
        'ffmpeg',
        '-f', 'gdigrab',
        '-framerate', '30',
        '-offset_x', '0',      // Start from left edge
        '-offset_y', '0',      // Start from top
        '-video_size', '1200x700',  // Capture only terminal area
        '-i', 'desktop',
        '-t', '20',  // Record for max 20 seconds
        '-c:v', 'libx264',     // Use H.264 codec
        '-preset', 'fast',     // Fast encoding
        '-crf', '18',          // Quality (lower = better, 18-28 is good)
        $recordingFile
    ];
} else {
    // Linux/Mac: capture specific window area
    $ffmpegCmd = [
        'ffmpeg',
        '-f', 'x11grab',
        '-r', '30',
        '-s', '1200x700',
        '-i', ':0+0,0',
        '-t', '20',
        '-c:v', 'libx264',
        '-preset', 'fast',
        '-crf', '18',
        $recordingFile
    ];
}

$recordProcess = new Process($ffmpegCmd);
$recordProcess->setTimeout(60);   // 60 second timeout (20s recording + overhead)
$recordProcess->setIdleTimeout(30);  // Idle timeout of 30 seconds

// Start recording in background
$recordProcess->start();
sleep(2);  // Give ffmpeg time to start recording

echo "   [Recording started - will auto-stop after 20 seconds]\n";
echo "   [+] Recording for 20 seconds...\n";

echo "2. Loading demo cases...\n";

$demoCasesFile = __DIR__ . "/../days/day{$day}/demo_cases.php";
if (!file_exists($demoCasesFile)) {
    echo "Error: Demo cases file not found: $demoCasesFile\n";
    exit(1);
}

require $demoCasesFile;

if (!isset($demoCases) || empty($demoCases)) {
    echo "Error: No demo cases found\n";
    exit(1);
}

echo "   Found " . count($demoCases) . " demo case(s)\n\n";

echo "3. Running demo cases...\n";
echo str_repeat("-", 60) . "\n";

// Run each demo case
$cliFile = __DIR__ . "/../days/day{$day}/cli.php";
foreach ($demoCases as $idx => $case) {
    $caseNum = $idx + 1;
    echo "\n[Case {$caseNum}] {$case['name']}\n";
    echo str_repeat("-", 60) . "\n";

    // Run the CLI script with loaded environment variables
    $process = new Process(['php', $cliFile, "--case={$caseNum}"]);
    $process->setTimeout(120);
    $process->setEnv($env);  // Pass the directly-loaded $env from .env file
    $process->run();

    echo $process->getOutput();

    if (!$process->isSuccessful()) {
        echo "Warning: Case {$caseNum} failed: " . $process->getErrorOutput() . "\n";
    }

    // Sleep between cases for video clarity
    if ($idx < count($demoCases) - 1) {
        echo "\nWaiting 3 seconds before next case...\n";
        sleep(3);
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "4. Waiting for screen capture to auto-stop...\n";

// Wait for ffmpeg to finish (it will auto-stop after 20 seconds due to -t flag)
try {
    $recordProcess->wait();
} catch (Exception $e) {
    echo "Warning: " . $e->getMessage() . "\n";
    // Forcefully terminate if it doesn't stop gracefully
    $recordProcess->stop(5, SIGTERM);
}

if (!file_exists($recordingFile)) {
    echo "Error: Recording failed - file not created\n";
    exit(1);
}

echo "   Recording saved: $recordingFile\n";
echo "   Size: " . filesize($recordingFile) . " bytes\n\n";

echo "5. Re-encoding video with better codec...\n";
// Skip compression - already encoded during recording with good quality
// This saves time and ensures video is playable
$finalFile = $recordingFile;
echo "   Video ready: $recordingFile\n";
echo "   Size: " . filesize($recordingFile) . " bytes\n";
echo "   (No additional compression needed - already H.264 encoded)\n\n";

echo "6. Uploading to Yandex.Disk...\n";

$yandexToken = $_ENV['YANDEX_DISK_TOKEN'] ?? '';
if (!$yandexToken) {
    echo "Warning: YANDEX_DISK_TOKEN not set, skipping upload\n";
    echo "\nGenerated files:\n";
    echo "   Recording: $recordingFile\n";
    if ($finalFile !== $recordingFile) {
        echo "   Compressed: $compressedFile\n";
    }
    exit(0);
}

$uploader = require __DIR__ . '/upload_yandex.php';
$uploadResult = $uploader($finalFile, "day{$day}_{$timestamp}.mp4", $yandexToken);

echo "7. Results\n";
echo str_repeat("=", 60) . "\n";

$repoUrl = $_ENV['GITHUB_REPO_URL'] ?? 'https://github.com/stacmv/ai-advent';

echo "Code: {$repoUrl}/tree/day{$day}\n";

if (isset($uploadResult['shareLink'])) {
    echo "Video: {$uploadResult['shareLink']}\n";
} else {
    echo "Video: [Upload failed]\n";
}

echo "\nDone!\n";
