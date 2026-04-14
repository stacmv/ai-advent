<?php

/**
 * FFmpeg wrapper that manages recording lifecycle.
 * Started as a background process by the web UI.
 * Watches for a stop signal file, then sends 'q' to ffmpeg stdin
 * for graceful shutdown (proper MP4 finalization).
 *
 * Usage: php tools/ffmpeg_wrapper.php <recording_file> <signal_dir>
 */

$recordingFile = $argv[1] ?? null;
$signalDir = $argv[2] ?? null;

if (!$recordingFile || !$signalDir) {
    echo "Usage: php tools/ffmpeg_wrapper.php <recording_file> <signal_dir>\n";
    exit(1);
}

$stopFile = $signalDir . '/.ffmpeg_stop';
$readyFile = $signalDir . '/.ffmpeg_ready';

// Start ffmpeg with proc_open so we have stdin access
$ffmpegCmd = 'ffmpeg -f gdigrab -framerate 30 -offset_x 0 -offset_y 0 -video_size 1200x700 '
    . '-i desktop -c:v libx264 -preset fast -crf 18 ' . escapeshellarg($recordingFile);

$ffmpegPipes = [];
$ffmpegProc = proc_open(
    $ffmpegCmd,
    [
        0 => ['pipe', 'r'],  // stdin — we'll write 'q' to stop
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ],
    $ffmpegPipes
);

if (!is_resource($ffmpegProc)) {
    exit(1);
}

// Make stdout/stderr non-blocking so they don't deadlock
stream_set_blocking($ffmpegPipes[1], false);
stream_set_blocking($ffmpegPipes[2], false);

// Signal that ffmpeg is ready
touch($readyFile);

// Poll for stop signal
while (proc_get_status($ffmpegProc)['running']) {
    if (file_exists($stopFile)) {
        // Send 'q' to ffmpeg stdin — graceful quit, writes MP4 trailer
        fwrite($ffmpegPipes[0], "q");
        fclose($ffmpegPipes[0]);

        // Wait for ffmpeg to finish (up to 10 seconds)
        $waitStart = time();
        while (proc_get_status($ffmpegProc)['running'] && (time() - $waitStart) < 10) {
            usleep(200000); // 200ms
        }

        break;
    }
    usleep(500000); // 500ms
}

// Clean up
if (is_resource($ffmpegPipes[0] ?? null)) {
    fclose($ffmpegPipes[0]);
}
fclose($ffmpegPipes[1]);
fclose($ffmpegPipes[2]);
proc_close($ffmpegProc);

// Remove signal files
@unlink($stopFile);
@unlink($readyFile);
