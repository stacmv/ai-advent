<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;
use AiAdvent\Agent;

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
        if (strpos($line, '=') === false) {
            continue;
        }
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
    return $config;
}

$env = loadEnv(__DIR__ . '/../../.env');

// Route API requests
$action = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === '/api/demo/cases') {
    header('Content-Type: application/json; charset=utf-8');
    handleDemoCases();
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ($action === '/api/chat') {
        handleChat($env);
    } elseif ($action === '/api/record/start') {
        handleRecordStart($env);
    } elseif ($action === '/api/record/stop') {
        handleRecordStop($env);
    } elseif ($action === '/api/upload') {
        handleUpload($env);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
    exit;
}

function handleChat($env)
{
    if (empty($env['YANDEX_API_KEY']) || empty($env['YANDEX_FOLDER_ID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing YANDEX_API_KEY or YANDEX_FOLDER_ID']);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $message = trim($body['message'] ?? '');

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Empty message']);
        return;
    }

    try {
        $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
        $agent = new Agent($client);
        $response = $agent->run($message);
        echo json_encode(['response' => $response]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleDemoCases()
{
    require __DIR__ . '/demo_cases.php';
    echo json_encode(['cases' => $demoCases]);
}

function handleRecordStart($env)
{
    $timestamp = date('Y-m-d_His');
    $recordDir = realpath(__DIR__ . '/../../recordings') ?: __DIR__ . '/../../recordings';
    if (!is_dir($recordDir)) {
        @mkdir($recordDir, 0755, true);
        $recordDir = realpath($recordDir);
    }

    $recordingFile = $recordDir . DIRECTORY_SEPARATOR . 'day6_' . $timestamp . '.mp4';
    $wrapperScript = realpath(__DIR__ . '/../../tools/ffmpeg_wrapper.php');

    // Clean up any leftover signal files
    @unlink($recordDir . '/.ffmpeg_stop');
    @unlink($recordDir . '/.ffmpeg_ready');

    // Start the wrapper script in background (it manages ffmpeg lifecycle)
    $cmd = 'php ' . escapeshellarg($wrapperScript)
        . ' ' . escapeshellarg($recordingFile)
        . ' ' . escapeshellarg($recordDir);

    pclose(popen('start /B ' . $cmd, 'r'));

    // Wait for wrapper to signal that ffmpeg is ready
    $waited = 0;
    while (!file_exists($recordDir . '/.ffmpeg_ready') && $waited < 5) {
        usleep(200000);
        $waited += 0.2;
    }

    echo json_encode(['status' => 'Recording started', 'file' => $recordingFile]);
}

function handleRecordStop($env)
{
    $recordDir = realpath(__DIR__ . '/../../recordings') ?: __DIR__ . '/../../recordings';

    // Create stop signal file — wrapper script will send 'q' to ffmpeg
    touch($recordDir . '/.ffmpeg_stop');

    // Wait for wrapper to finish graceful shutdown
    $waited = 0;
    while (file_exists($recordDir . '/.ffmpeg_ready') && $waited < 12) {
        usleep(500000);
        $waited += 0.5;
    }

    echo json_encode(['status' => 'Recording stopped']);
}

function handleUpload($env)
{
    try {
        $result = shell_exec('php ' . escapeshellarg(__DIR__ . '/../../tools/upload_latest.php') . ' 6 2>&1');
        echo json_encode(['status' => 'Upload completed', 'output' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Serve HTML
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day 6: Agent — Web UI</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        header {
            background: #252526;
            border-bottom: 1px solid #3c3c3c;
            padding: 10px 20px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-title { color: #9cdcfe; }
        .header-title span { color: #569cd6; }
        .header-buttons {
            display: flex;
            gap: 8px;
        }
        button {
            background: #0e639c;
            color: #fff;
            border: none;
            padding: 6px 12px;
            font-family: inherit;
            font-size: 12px;
            border-radius: 3px;
            cursor: pointer;
            white-space: nowrap;
        }
        button:hover { background: #1177bb; }
        button:disabled { background: #3c3c3c; color: #6a6a6a; cursor: not-allowed; }
        #record-stop-btn:not(:disabled) { background: #d32f2f; }
        #record-stop-btn:not(:disabled):hover { background: #f44747; }
        #log {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        .msg { margin-bottom: 12px; line-height: 1.5; }
        .msg .label {
            font-weight: bold;
            margin-right: 4px;
        }
        .msg.user .label { color: #4ec9b0; }
        .msg.agent .label { color: #ce9178; }
        .msg.demo .label { color: #dcdcaa; }
        .msg.error .label { color: #f44747; }
        .msg .text { white-space: pre-wrap; word-break: break-word; }
        .msg.thinking .text { color: #6a9955; font-style: italic; }
        .demo-case {
            background: #2d2d30;
            border-left: 2px solid #569cd6;
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 2px;
        }
        .demo-case .case-name { color: #4ec9b0; font-weight: bold; }
        .demo-case .case-text { color: #d4d4d4; margin-top: 4px; }
        #form {
            display: flex;
            border-top: 1px solid #3c3c3c;
            background: #252526;
            padding: 12px 20px;
            gap: 10px;
        }
        #input {
            flex: 1;
            background: #1e1e1e;
            border: 1px solid #3c3c3c;
            color: #d4d4d4;
            font-family: inherit;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 3px;
            outline: none;
        }
        #input:focus { border-color: #569cd6; }
        #send {
            background: #0e639c;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-family: inherit;
            font-size: 14px;
            border-radius: 3px;
            cursor: pointer;
        }
        #send:hover { background: #1177bb; }
        #send:disabled { background: #3c3c3c; color: #6a6a6a; cursor: not-allowed; }
    </style>
</head>
<body>
    <header>
        <div class="header-title">
            === Day 6: <span>Agent Architecture</span> ===
        </div>
        <div class="header-buttons">
            <button id="demo-btn" title="Run all demo cases">Demo</button>
            <button id="record-start-btn" title="Start screen recording">Record</button>
            <button id="record-stop-btn" title="Stop screen recording" disabled>Stop</button>
            <button id="upload-btn" title="Upload latest video">Upload</button>
            <button id="clear-btn" title="Clear chat">Clear</button>
        </div>
    </header>
    <div id="log"></div>
    <form id="form" onsubmit="return false;">
        <input id="input" type="text" placeholder="Type your message in any language…" autofocus autocomplete="off">
        <button id="send" type="submit">Send</button>
    </form>

    <script>
        const log = document.getElementById('log');
        const input = document.getElementById('input');
        const send = document.getElementById('send');
        const demoBtn = document.getElementById('demo-btn');
        const recordStartBtn = document.getElementById('record-start-btn');
        const recordStopBtn = document.getElementById('record-stop-btn');
        const uploadBtn = document.getElementById('upload-btn');
        const clearBtn = document.getElementById('clear-btn');

        function addMsg(role, text) {
            const div = document.createElement('div');
            div.className = 'msg ' + role;
            const labels = { user: 'You:', agent: 'Agent:', error: 'Error:', thinking: '…', demo: 'Demo:' };
            const label = labels[role] || role + ':';
            div.innerHTML = '<span class="label">' + label + '</span>'
                + '<span class="text">' + escapeHtml(text) + '</span>';
            log.appendChild(div);
            log.scrollTop = log.scrollHeight;
            return div;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function sendMessage() {
            const text = input.value.trim();
            if (!text) return;
            input.value = '';
            send.disabled = true;

            addMsg('user', text);
            const thinking = addMsg('thinking', 'waiting for response…');

            try {
                const res = await fetch('/api/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: text })
                });
                const data = await res.json();
                thinking.remove();
                if (data.error) {
                    addMsg('error', data.error);
                } else {
                    addMsg('agent', data.response);
                }
            } catch (e) {
                thinking.remove();
                addMsg('error', e.message);
            }

            send.disabled = false;
            input.focus();
        }

        async function runDemo() {
            demoBtn.disabled = true;
            input.disabled = true;
            send.disabled = true;

            addMsg('demo', 'Loading demo cases…');

            try {
                const res = await fetch('/api/demo/cases');
                const data = await res.json();

                if (data.error) {
                    addMsg('error', data.error);
                    demoBtn.disabled = false;
                    input.disabled = false;
                    send.disabled = false;
                    return;
                }

                // Run each case interactively
                for (const c of data.cases) {
                    input.value = c.prompt;
                    addMsg('user', c.prompt);

                    const thinking = addMsg('thinking', 'waiting for response…');

                    try {
                        const chatRes = await fetch('/api/chat', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ message: c.prompt })
                        });
                        const chatData = await chatRes.json();
                        thinking.remove();

                        if (chatData.error) {
                            addMsg('error', chatData.error);
                        } else {
                            addMsg('agent', chatData.response);
                        }
                    } catch (e) {
                        thinking.remove();
                        addMsg('error', e.message);
                    }

                    // 2 second delay between cases
                    await new Promise(resolve => setTimeout(resolve, 2000));
                }

                addMsg('demo', 'Demo completed');
            } catch (e) {
                addMsg('error', e.message);
            }

            demoBtn.disabled = false;
            input.disabled = false;
            send.disabled = false;
            input.value = '';
            input.focus();
        }

        async function startRecording() {
            recordStartBtn.disabled = true;
            addMsg('demo', 'Starting ffmpeg (1200x700 from top-left)…');
            try {
                const res = await fetch('/api/record/start', { method: 'POST' });
                const data = await res.json();
                if (data.error) {
                    addMsg('error', data.error);
                    recordStartBtn.disabled = false;
                } else {
                    recordStopBtn.disabled = false;
                    addMsg('demo', 'Recording started – click Demo or type to interact');
                }
            } catch (e) {
                addMsg('error', e.message);
                recordStartBtn.disabled = false;
            }
        }

        async function stopRecording() {
            recordStopBtn.disabled = true;
            recordStartBtn.disabled = true;
            addMsg('demo', 'Stopping recording…');
            try {
                const res = await fetch('/api/record/stop', { method: 'POST' });
                const data = await res.json();
                recordStartBtn.disabled = false;
                recordStopBtn.disabled = true;
                addMsg('demo', 'Recording stopped');
            } catch (e) {
                addMsg('error', e.message);
                recordStartBtn.disabled = false;
                recordStopBtn.disabled = false;
            }
        }

        async function upload() {
            uploadBtn.disabled = true;
            addMsg('demo', 'Uploading latest video…');
            try {
                const res = await fetch('/api/upload', { method: 'POST' });
                const data = await res.json();
                uploadBtn.disabled = false;
                if (data.error) {
                    addMsg('error', data.error);
                } else {
                    addMsg('demo', 'Upload completed: ' + data.output);
                }
            } catch (e) {
                addMsg('error', e.message);
                uploadBtn.disabled = false;
            }
        }

        send.addEventListener('click', sendMessage);
        input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) sendMessage(); });
        demoBtn.addEventListener('click', runDemo);
        recordStartBtn.addEventListener('click', startRecording);
        recordStopBtn.addEventListener('click', stopRecording);
        uploadBtn.addEventListener('click', upload);
        clearBtn.addEventListener('click', () => { log.innerHTML = ''; input.focus(); });
    </script>
</body>
</html>
