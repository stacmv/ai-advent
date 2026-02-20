<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

// Load .env directly - simple parser
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

$env = loadEnv(__DIR__ . '/../.env');

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

$cliFile = __DIR__ . "/../days/day{$day}/cli.php";

if (!file_exists($cliFile)) {
    echo "Error: CLI file not found: $cliFile\n";
    exit(1);
}

echo "=== AI Advent Recording (Day {$day}) ===\n\n";

// Prepare recording directory
if (!is_dir(__DIR__ . '/../recordings')) {
    mkdir(__DIR__ . '/../recordings', 0755, true);
}

$timestamp = date('Y-m-d_His');
$recordingFile = __DIR__ . "/../recordings/day{$day}_{$timestamp}.mp4";

echo "[1/3] Move terminal to TOP-LEFT corner, press ENTER to start recording:\n";
echo "      ";
fgets(STDIN);

echo "\n[+] Starting in 3 seconds...\n";
sleep(3);

// Step 2: Start ffmpeg (without -t limit, runs indefinitely)
echo "[2/3] Starting ffmpeg (recording will continue until you press Enter)...\n";

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

if ($isWindows) {
    $ffmpegCmd = [
        'ffmpeg',
        '-f', 'gdigrab',
        '-framerate', '30',
        '-offset_x', '0',
        '-offset_y', '0',
        '-video_size', '1200x700',
        '-i', 'desktop',
        '-c:v', 'libx264',
        '-preset', 'fast',
        '-crf', '18',
        $recordingFile
    ];
} else {
    $ffmpegCmd = [
        'ffmpeg',
        '-f', 'x11grab',
        '-r', '30',
        '-s', '1200x700',
        '-i', ':0+0,0',
        '-c:v', 'libx264',
        '-preset', 'fast',
        '-crf', '18',
        $recordingFile
    ];
}

$recordProcess = new Process($ffmpegCmd);
$recordProcess->setTimeout(null); // No timeout
$recordProcess->disableOutput();  // Don't buffer ffmpeg output
$recordProcess->start();
sleep(2);

// Load demo cases and run each one separately
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

$caseKeys = array_keys($demoCases);
foreach ($caseKeys as $i => $caseNum) {
    $case = $demoCases[$caseNum];
    echo "   [Case {$caseNum}] {$case['name']}\n";

    $process = new Process(['php', $cliFile, "--case={$caseNum}"]);
    $process->setTimeout(300);
    $process->setEnv($env);
    $process->run(function ($type, $buffer) {
        echo $buffer;
    });

    if (!$process->isSuccessful()) {
        echo "   Warning: Case {$caseNum} failed: " . $process->getErrorOutput() . "\n";
    }

    if ($i < count($caseKeys) - 1) {
        echo "\n   [Press Enter for next case...]\n";
        fgets(STDIN);
    }
}

// Wait for user to press Enter before stopping recording
echo "\n[3/3] Press Enter to stop recording...\n";
fgets(STDIN);

// Stop ffmpeg gracefully with SIGTERM (allows proper file finalization)
if ($recordProcess->isRunning()) {
    $recordProcess->stop(5, defined('SIGTERM') ? SIGTERM : 15);
}

if (!file_exists($recordingFile)) {
    echo "Error: Recording failed - file not created\n";
    exit(1);
}

// Done
echo "\n[+] Recording complete!\n";
echo str_repeat("=", 60) . "\n";
echo "[+] Video: {$recordingFile}\n";
echo "[+] Size: " . round(filesize($recordingFile) / 1024 / 1024, 2) . " MB\n";
echo "\nNext: review the video, then run 'make upload'\n";
