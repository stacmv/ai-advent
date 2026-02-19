<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\Process\Process;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

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

echo "1. Starting screen capture...\n";
echo "   Output: $recordingFile\n";

// Detect OS and use appropriate screen capture
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

if ($isWindows) {
    // Windows GDI screen grab with ffmpeg
    $ffmpegCmd = [
        'ffmpeg',
        '-f', 'gdigrab',
        '-framerate', '30',
        '-i', 'desktop',
        '-t', '60',  // Record for max 60 seconds
        $recordingFile
    ];
} else {
    // Fallback for other systems (would need adjustment)
    $ffmpegCmd = [
        'ffmpeg',
        '-f', 'x11grab',
        '-r', '30',
        '-s', '1920x1080',
        '-i', ':0',
        '-t', '60',
        $recordingFile
    ];
}

$recordProcess = new Process($ffmpegCmd);
$recordProcess->setTimeout(120);

// Start recording in background
$recordProcess->start();
sleep(2);  // Give ffmpeg time to start

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
    $process = new Process(['php', $cliFile, "--case={$caseNum}"]);
    $process->setTimeout(120);
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
echo "4. Stopping screen capture...\n";

// Signal ffmpeg to stop (try to write 'q' to stdin)
try {
    if (is_resource($recordProcess->getInput())) {
        fwrite($recordProcess->getInput(), 'q');
    }
} catch (Exception $e) {
    // Ignore if stdin not writable
}

$recordProcess->wait();

if (!file_exists($recordingFile)) {
    echo "Error: Recording failed - file not created\n";
    exit(1);
}

echo "   Recording saved: $recordingFile\n";
echo "   Size: " . filesize($recordingFile) . " bytes\n\n";

echo "5. Compressing video...\n";
$compressProcess = new Process([
    'ffmpeg',
    '-i', $recordingFile,
    '-vcodec', 'libx264',
    '-crf', '28',
    '-y',
    $compressedFile
]);
$compressProcess->setTimeout(300);
$compressProcess->run();

if (!file_exists($compressedFile)) {
    echo "Warning: Compression failed, using original file\n";
    $finalFile = $recordingFile;
} else {
    $finalFile = $compressedFile;
    echo "   Compressed: $compressedFile\n";
    echo "   Size: " . filesize($compressedFile) . " bytes\n\n";
}

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
