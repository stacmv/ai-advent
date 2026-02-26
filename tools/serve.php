<?php

/**
 * Start the Day 6 web client on a free port and open the browser.
 */

// Find a free port by letting the OS assign one (port 0 = OS picks)
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$name = stream_socket_get_name($server, false);
$port = (int) substr($name, strrpos($name, ':') + 1);
fclose($server);

$url = "http://localhost:{$port}";
$root = realpath(__DIR__ . '/../days/day6/web.php');
$projectRoot = realpath(__DIR__ . '/..');

// Write port to file for Makefile to read
file_put_contents($projectRoot . '/.server.port', (string)$port);

echo "Starting Day 6 web client at {$url} ...\n";
echo "Press Ctrl+C to stop.\n\n";

// Open the browser after a 1-second delay (background command, non-blocking)
pclose(popen('cmd /c "timeout /t 1 >NUL 2>&1 & start ' . $url . '"', 'r'));

// Start the PHP built-in server (blocks until Ctrl+C)
passthru('php -S localhost:' . $port . ' "' . $root . '"');
