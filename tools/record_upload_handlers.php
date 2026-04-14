<?php

/**
 * Shared record/upload handlers for all days
 * Based on day10's working implementation
 */

function handleRecordStart($day)
{
    $timestamp = date('Y-m-d_His');
    $recordDir = realpath(__DIR__ . '/../recordings') ?: __DIR__ . '/../recordings';
    if (!is_dir($recordDir)) {
        @mkdir($recordDir, 0755, true);
        $recordDir = realpath($recordDir);
    }

    $recordingFile = $recordDir . DIRECTORY_SEPARATOR . 'day' . (int)$day . '_' . $timestamp . '.mp4';
    $wrapperScript = realpath(__DIR__ . '/ffmpeg_wrapper.php');

    @unlink($recordDir . '/.ffmpeg_stop');
    @unlink($recordDir . '/.ffmpeg_ready');

    $cmd = 'php ' . escapeshellarg($wrapperScript)
        . ' ' . escapeshellarg($recordingFile)
        . ' ' . escapeshellarg($recordDir);

    pclose(popen('start /B ' . $cmd, 'r'));

    $waited = 0;
    while (!file_exists($recordDir . '/.ffmpeg_ready') && $waited < 5) {
        usleep(200000);
        $waited += 0.2;
    }

    echo json_encode(['status' => 'Recording started', 'file' => $recordingFile]);
}

function handleRecordStop()
{
    $recordDir = realpath(__DIR__ . '/../recordings') ?: __DIR__ . '/../recordings';

    touch($recordDir . '/.ffmpeg_stop');

    $waited = 0;
    while (file_exists($recordDir . '/.ffmpeg_ready') && $waited < 12) {
        usleep(500000);
        $waited += 0.5;
    }

    echo json_encode(['status' => 'Recording stopped']);
}

function handleUpload($day)
{
    try {
        $recordingsDir = realpath(__DIR__ . '/../recordings') ?: __DIR__ . '/../recordings';
        $storageDir = realpath(__DIR__ . '/../storage') ?: __DIR__ . '/../storage';
        $progressFile = $storageDir . '/upload_progress.json';
        $pidFile = $storageDir . '/upload_progress.pid';

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        if (!is_dir($recordingsDir)) {
            http_response_code(400);
            echo json_encode(['error' => 'No recordings directory found']);
            return;
        }

        // Check if upload already in progress
        if (file_exists($pidFile)) {
            $pid = (int) @file_get_contents($pidFile);
            if ($pid > 0) {
                $out = [];
                exec('tasklist /FI "PID eq ' . $pid . '" /NH 2>NUL', $out);
                if (!empty($out) && strpos(implode('', $out), (string) $pid) !== false) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Upload already in progress']);
                    return;
                }
            }
            @unlink($pidFile);
            @unlink($progressFile);
        }

        // Find latest day video
        $files = scandir($recordingsDir, SCANDIR_SORT_DESCENDING);
        $dayVideos = array_filter($files, function ($f) use ($day) {
            return preg_match("/day{$day}_.*\.mp4$/", $f);
        });

        if (empty($dayVideos)) {
            http_response_code(400);
            echo json_encode(['error' => "No recordings found for day {$day}"]);
            return;
        }

        $latestFile = reset($dayVideos);
        $filePath = "{$recordingsDir}/{$latestFile}";
        $fSize = filesize($filePath);
        $units = ['B', 'KB', 'MB', 'GB'];
        $b = max($fSize, 0);
        $p = floor(($b ? log($b) : 0) / log(1024));
        $p = min($p, count($units) - 1);
        $b /= (1 << (10 * $p));
        $fileSizeFormatted = round($b, 2) . ' ' . $units[$p];

        @unlink($progressFile);
        @unlink($pidFile);

        // Spawn background upload process
        $script = __DIR__ . '/upload_progress.php';
        $cmd = 'php ' . escapeshellarg($script) . ' ' . (int)$day . ' ' . escapeshellarg($storageDir);
        pclose(popen('start /B ' . $cmd . ' >NUL 2>&1', 'r'));

        echo json_encode([
            'status' => 'started',
            'fileName' => $latestFile,
            'fileSizeFormatted' => $fileSizeFormatted,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleUploadStatus()
{
    try {
        $storageDir = realpath(__DIR__ . '/../storage') ?: __DIR__ . '/../storage';
        $progressFile = $storageDir . '/upload_progress.json';

        if (file_exists($progressFile)) {
            $data = json_decode(file_get_contents($progressFile), true);
            echo json_encode($data);
        } else {
            echo json_encode(['status' => 'idle']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function killUploadProcess()
{
    $storageDir = realpath(__DIR__ . '/../storage') ?: __DIR__ . '/../storage';
    $pidFile = $storageDir . '/upload_progress.pid';
    $progressFile = $storageDir . '/upload_progress.json';

    $pid = @file_get_contents($pidFile);
    if ($pid) {
        exec('taskkill /F /T /PID ' . (int) $pid . ' 2>NUL');
    }
    @unlink($pidFile);
    @unlink($progressFile);
}

function handleUploadCancel()
{
    killUploadProcess();
    echo json_encode(['status' => 'cancelled']);
}

function handleUploadReset()
{
    killUploadProcess();
    echo json_encode(['status' => 'reset']);
}
