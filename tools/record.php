<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\Process\Process;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Ensure environment variables are available to subprocess using putenv()
// This makes them available to all child processes
foreach ($_ENV as $key => $value) {
    if ($value !== '') {  // Only set non-empty values
        putenv("{$key}={$value}");
    }
}

// Parse arguments
$day = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--day=') === 0) {
        $day = str_replace('--day=', '', $arg);
        break;
    }
}

if (!$day || !ctype_digit($day) || (int)$day < 1) {
    echo "Usage: php tools/record.php --day=N\n";
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

    // Run the CLI script
    // Pass all environment variables explicitly to the subprocess
    // Build env array: start with $_ENV (has our loaded vars), add $_SERVER
    $env = $_ENV + $_SERVER;

    // Explicitly ensure API keys are in env
    if (!empty($_ENV['YANDEX_API_KEY'])) {
        $env['YANDEX_API_KEY'] = $_ENV['YANDEX_API_KEY'];
    }
    if (!empty($_ENV['YANDEX_FOLDER_ID'])) {
        $env['YANDEX_FOLDER_ID'] = $_ENV['YANDEX_FOLDER_ID'];
    }
    if (!empty($_ENV['ANTHROPIC_API_KEY'])) {
        $env['ANTHROPIC_API_KEY'] = $_ENV['ANTHROPIC_API_KEY'];
    }
    if (!empty($_ENV['DEEPSEEK_API_KEY'])) {
        $env['DEEPSEEK_API_KEY'] = $_ENV['DEEPSEEK_API_KEY'];
    }

    $process = new Process(['php', $cliFile, "--case={$caseNum}"]);
    $process->setTimeout(120);
    $process->setEnv($env);
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

echo "6. Recording Complete\n";
echo str_repeat("=", 60) . "\n";

$repoUrl = $env['GITHUB_REPO_URL'] ?? 'https://github.com/stacmv/ai-advent';

echo "\n[+] Video saved: {$recordingFile}\n";
echo "[+] Size: " . filesize($recordingFile) . " bytes\n";
echo "[+] Duration: ~20 seconds\n";

echo "\nNext steps:\n";
echo "1. Review the video: {$recordingFile}\n";
echo "2. Upload when ready:\n";
echo "   php tools/upload_video.php day{$day}_{$timestamp}.mp4\n";
echo "\nOr upload all recordings:\n";
echo "   php tools/upload_video.php\n";

echo "\nCode: {$repoUrl}/tree/day{$day}\n";
echo "\nDone!\n";
