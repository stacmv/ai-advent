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
$webFile = __DIR__ . "/../days/day{$day}/web.php";
$demoCasesFile = __DIR__ . "/../days/day{$day}/demo_cases.php";

if (!file_exists($demoCasesFile)) {
    echo "Error: Demo cases file not found: $demoCasesFile\n";
    exit(1);
}

// Detect if this is a CLI or web-based day
$isCli = file_exists($cliFile);
$isWeb = file_exists($webFile);

require $demoCasesFile;

if (!isset($demoCases) || empty($demoCases)) {
    echo "Error: No demo cases found\n";
    exit(1);
}

echo "=== AI Advent Recording (Day {$day}) ===\n\n";

// Step 1: Dry run to measure demo duration
echo "[1/4] Measuring demo duration (dry run)...\n";

$dryStart = microtime(true);

if ($isCli) {
    // CLI-based day: run via PHP
    foreach ($demoCases as $idx => $case) {
        $caseNum = $idx + 1;
        echo "   Running case {$caseNum}/" . count($demoCases) . "...\r";

        $process = new Process(['php', $cliFile, "--case={$caseNum}"]);
        $process->setTimeout(120);
        $process->setEnv($env);
        $process->run();

        if (!$process->isSuccessful()) {
            echo "\n[-] Case {$caseNum} failed: " . $process->getErrorOutput() . "\n";
            exit(1);
        }

        if ($idx < count($demoCases) - 1) {
            sleep(3);
        }
    }
} elseif ($isWeb) {
    // Web-based day: start server, call API endpoints
    echo "   Starting web server...\n";

    // Start the web server
    $serverProcess = new Process(['php', 'tools/serve.php']);
    $serverProcess->setTimeout(300);
    $serverProcess->start();
    sleep(3);

    // Get port from .server.port file
    $port = @file_get_contents(__DIR__ . '/../.server.port');
    if (!$port) {
        echo "[-] Failed to get server port\n";
        $serverProcess->stop();
        exit(1);
    }
    $port = trim($port);

    // Run demo cases via HTTP API
    foreach ($demoCases as $idx => $case) {
        $caseNum = $idx + 1;
        echo "   Running case {$caseNum}/" . count($demoCases) . "...\r";

        $url = "http://localhost:{$port}/days/day{$day}/api/demo/run";
        $data = json_encode(['case' => $caseNum]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $data,
                'timeout' => 120
            ]
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            echo "\n[-] Case {$caseNum} failed\n";
            $serverProcess->stop();
            exit(1);
        }

        if ($idx < count($demoCases) - 1) {
            sleep(3);
        }
    }

    $serverProcess->stop();
} else {
    echo "[-] Cannot find CLI or web file for day {$day}\n";
    exit(1);
}

$dryDuration = microtime(true) - $dryStart;
$recordDuration = (int)ceil($dryDuration * 1.1);
// Minimum 10 seconds, add 5 for startup buffer
$recordDuration = max($recordDuration, 10) + 5;

echo "   Demo took " . round($dryDuration, 1) . "s, recording for {$recordDuration}s\n\n";

// Step 2: Prepare recording
if (!is_dir(__DIR__ . '/../recordings')) {
    mkdir(__DIR__ . '/../recordings', 0755, true);
}

$timestamp = date('Y-m-d_His');
$recordingFile = __DIR__ . "/../recordings/day{$day}_{$timestamp}.mp4";

echo "[2/4] Move terminal to TOP-LEFT corner, press ENTER to start recording:\n";
echo "      ";
fgets(STDIN);

echo "\n[+] Starting in 3 seconds...\n";
sleep(3);

// Step 3: Record
echo "[3/4] Recording ({$recordDuration}s)...\n";

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
        '-t', (string)$recordDuration,
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
        '-t', (string)$recordDuration,
        '-c:v', 'libx264',
        '-preset', 'fast',
        '-crf', '18',
        $recordingFile
    ];
}

$recordProcess = new Process($ffmpegCmd);
$recordProcess->setTimeout($recordDuration + 30);
$recordProcess->start();
sleep(2);

// Step 3b: Run demo cases (recorded this time)
if ($isCli) {
    // CLI-based day
    foreach ($demoCases as $idx => $case) {
        $caseNum = $idx + 1;
        echo "   [Case {$caseNum}] {$case['name']}\n";

        $process = new Process(['php', $cliFile, "--case={$caseNum}"]);
        $process->setTimeout(120);
        $process->setEnv($env);
        $process->run();

        echo $process->getOutput();

        if (!$process->isSuccessful()) {
            echo "   Warning: Case {$caseNum} failed: " . $process->getErrorOutput() . "\n";
        }

        if ($idx < count($demoCases) - 1) {
            sleep(3);
        }
    }
} elseif ($isWeb) {
    // Web-based day: start server and run API calls
    echo "   Starting web server...\n";

    $serverProcess = new Process(['php', 'tools/serve.php']);
    $serverProcess->setTimeout(300);
    $serverProcess->start();
    sleep(3);

    $port = @file_get_contents(__DIR__ . '/../.server.port');
    if (!$port) {
        echo "   [-] Failed to get server port\n";
        $serverProcess->stop();
        $recordProcess->stop();
        exit(1);
    }
    $port = trim($port);

    // Run demo cases via HTTP API
    foreach ($demoCases as $idx => $case) {
        $caseNum = $idx + 1;
        echo "   [Case {$caseNum}] {$case['name']}\n";

        $url = "http://localhost:{$port}/days/day{$day}/api/demo/run";
        $data = json_encode(['case' => $caseNum]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $data,
                'timeout' => 120
            ]
        ]);

        @file_get_contents($url, false, $ctx);

        if ($idx < count($demoCases) - 1) {
            sleep(3);
        }
    }

    $serverProcess->stop();
}

// Wait for ffmpeg to finish
echo "\n   Waiting for recording to finish...\n";
try {
    $recordProcess->wait();
} catch (Exception $e) {
    $recordProcess->stop(5);
}

if (!file_exists($recordingFile)) {
    echo "Error: Recording failed - file not created\n";
    exit(1);
}

// Step 4: Done
echo "\n[4/4] Done!\n";
echo str_repeat("=", 60) . "\n";
echo "[+] Video: {$recordingFile}\n";
echo "[+] Size: " . round(filesize($recordingFile) / 1024 / 1024, 2) . " MB\n";
echo "[+] Duration: ~{$recordDuration}s\n";
echo "\nNext: review the video, then run 'make upload'\n";
