<?php

// phpcs:disable PSR1.Files.SideEffects

/**
 * Thin wrapper: runs upload_latest.php and writes status to a JSON file.
 *
 * Usage: php tools/upload_progress.php <day> <storageDir>
 *
 * This uses the exact same upload path as `make upload`.
 */

// ── Args ──────────────────────────────────────────────────────────
$day        = $argv[1] ?? null;
$storageDir = $argv[2] ?? null;

if (!$day || !$storageDir) {
    fwrite(STDERR, "Usage: php upload_progress.php <day> <storageDir>\n");
    exit(1);
}
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$statusFile = $storageDir . '/upload_progress.json';
$pidFile    = $storageDir . '/upload_progress.pid';

file_put_contents($pidFile, (string) getmypid());

$status = ['status' => 'uploading', 'startedAt' => time(), 'lastUpdated' => time()];
file_put_contents($statusFile, json_encode($status), LOCK_EX);

// Run the exact same command as `make upload`
$script = __DIR__ . '/upload_latest.php';
$output = [];
$exitCode = 0;
exec('php ' . escapeshellarg($script) . ' ' . escapeshellarg($day) . ' 2>&1', $output, $exitCode);

$text = implode("\n", $output);

if ($exitCode === 0 && (strpos($text, 'successful') !== false || strpos($text, 'Видео:') !== false)) {
    // Extract video link
    $videoLink = null;
    if (preg_match('/Видео:\s*(.+)/', $text, $m)) {
        $videoLink = trim($m[1]);
    }
    $codeLink = null;
    if (preg_match('/Код:\s*(.+)/', $text, $m)) {
        $codeLink = trim($m[1]);
    }

    $status = [
        'status'      => 'done',
        'videoLink'   => $videoLink,
        'codeLink'    => $codeLink,
        'startedAt'   => $status['startedAt'],
        'lastUpdated' => time(),
    ];
} else {
    $status = [
        'status'      => 'error',
        'error'       => $text ?: "Exit code {$exitCode}",
        'startedAt'   => $status['startedAt'],
        'lastUpdated' => time(),
    ];
}

file_put_contents($statusFile, json_encode($status), LOCK_EX);
@unlink($pidFile);
