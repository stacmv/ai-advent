# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**AI Advent Challenge** — A PHP 8 project demonstrating LLM capabilities across experiments:
- Day 1: Basic API comparison
- Day 2: Response format control
- Day 3: Reasoning approaches
- Day 4: Temperature comparison
- Day 6: Agent Architecture (Web UI)

Supports 3 LLM APIs: Claude (Anthropic), Deepseek, and YandexGPT.

## Architecture & Git Strategy

This is a **multi-branch repository** with a shared main branch and day-specific branches:

```
main branch (shared code)
├── src/LLMClient.php          # Unified interface for all 3 APIs
├── tools/                     # Recording, uploading, token generation
├── Makefile, README.md, .env.example, cacert.pem
└── days/ (empty directory structure only)

day1–day5 branches (CLI-based)
├── all of main
└── days/dayN/cli.php, demo_cases.php

day6+ branches (Web UI-based)
├── all of main
└── days/dayN/web.php, demo_cases.php
```

**Key principle**: Day-specific code lives only on respective branches. Each branch includes all shared infrastructure from main.

## Critical Implementation Details

### Environment Variable Handling
**IMPORTANT**: Do NOT use the Dotenv library. Always parse `.env` directly using the `loadEnv()` helper function.

This is critical because subprocess environment variable propagation is unreliable with Dotenv. The `loadEnv()` function is implemented in every CLI file:

```php
function loadEnv($filePath) {
    $config = [];
    if (!file_exists($filePath)) return $config;
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        $config[$key] = $value;
    }
    return $config;
}
```

Load with: `$env = loadEnv(__DIR__ . '/../.env');` then access via `$env['KEY_NAME']`.

### SSL Certificate Verification
All HTTPS calls must verify SSL certificates using `cacert.pem`. This file is committed to the repo and must be configured in Guzzle clients:

```php
$client = new Client([
    'verify' => __DIR__ . '/../cacert.pem'
]);
```

Without this, you'll get "cURL error 60: SSL certificate verification failed" on Windows.

### OAuth Token Generation (Yandex.Disk)
Uses **implicit OAuth flow** (not authorization code flow) because this is a CLI app with no web server for redirects.

In `tools/get_yandex_token.php`:
- Reads `YANDEX_DISK_CLIENT_ID` from .env (required, no hardcoded fallbacks)
- Generates implicit OAuth URL: `https://oauth.yandex.ru/authorize?response_type=token&client_id=[CLIENT_ID]`
- User authorizes in browser, copies `access_token` from URL fragment
- Script saves token to .env as `YANDEX_DISK_TOKEN`

**Never** use hardcoded client IDs. Always require them from .env.

### API Key Support
Support both naming conventions for consistency:
- `YANDEX_CLIENT_ID` and `YANDEX_DISK_CLIENT_ID` (prefer the DISK variant)
- Check both with fallback: `$env['YANDEX_DISK_CLIENT_ID'] ?? ($env['YANDEX_CLIENT_ID'] ?? null)`

## LLMClient Usage

The unified `src/LLMClient.php` class wraps all 3 APIs with a consistent interface:

```php
$client = new LLMClient($env['ANTHROPIC_API_KEY'], $env['DEEPSEEK_API_KEY'],
                        $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);
$response = $client->chat($prompt, ['temperature' => 0.7, 'max_tokens' => 100]);
```

Methods: `chatClaude()`, `chatDeepseek()`, `chatYandexGPT()` are called internally.

**Note**: Response parsing varies by provider — YandexGPT returns `result.alternatives[0].message.text`.

## Common Development Commands

```bash
# Setup
make install              # Install composer dependencies
make setup               # Copy .env.example to .env
make get-token           # Generate Yandex.Disk OAuth token

# Day 1–5 (CLI-based branches)
make test                # Run interactive CLI (prompts for input)
make demo                # Run automated demo with pre-set cases
make record              # Start ffmpeg recording + run demo

# Day 6+ (Web UI-based branches)
make up                  # Start web server (auto-finds free port, opens browser)
make down                # Stop web server
make status              # Show server status
make serve               # Alias for 'make up'

# Shared
make upload              # Upload latest video for this day
make lint                # Check PSR-12 style
make clean               # Remove recordings directory
```

## Recording & Video Upload Workflow

### Day 6+ (Web UI): Interactive Recording

Recording is controlled from the web UI via Record/Stop buttons. Uses a **wrapper script** architecture:

1. Click **Record** → `tools/ffmpeg_wrapper.php` spawned as background process
2. Wrapper starts ffmpeg with `proc_open` (keeps stdin pipe open)
3. Click **Stop** → web API creates `.ffmpeg_stop` flag file
4. Wrapper detects flag, sends `'q'` to ffmpeg's stdin (graceful quit)
5. ffmpeg finalizes MP4 trailer and exits cleanly

Click **Upload** in the web UI to upload to Yandex.Disk.

### Day 1–5 (CLI): Automated Recording

1. `make record` — Starts ffmpeg screen capture (~1200x700px at 30fps), runs demo, stops recording
2. Review the video manually
3. `make upload` — Uploads latest dayN video to Yandex.Disk

### FFmpeg Graceful Shutdown (Critical)

**NEVER kill ffmpeg with `taskkill /F`** — this corrupts the MP4 file (no trailer written, video won't play).

The correct approach on Windows is to send `'q'` to ffmpeg's stdin via `proc_open`:

```php
// Start ffmpeg with stdin access
$proc = proc_open($cmd, [
    0 => ['pipe', 'r'],  // stdin — write 'q' here to stop
    1 => ['pipe', 'w'],  // stdout
    2 => ['pipe', 'w'],  // stderr
], $pipes);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

// To stop gracefully:
fwrite($pipes[0], "q");
fclose($pipes[0]);

// Wait for ffmpeg to finish writing MP4 trailer (up to 10s)
$waitStart = time();
while (proc_get_status($proc)['running'] && (time() - $waitStart) < 10) {
    usleep(200000);
}
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);
```

Previous failed approaches on Windows:
- `taskkill /F` — hard kill, no MP4 trailer
- `SIGINT`/`SIGTERM` — not available on Windows
- Symfony Process `stop()` — sends hard kill
- `setTty(true)` — not supported on Windows

## Branch-Specific Development

When working on a day branch:

**Day 1–5 (CLI-based):**
1. Implement in `days/dayN/cli.php` — interactive mode that prompts user for input
2. Implement in `days/dayN/demo_cases.php` — array of automated test cases

**Day 6+ (Web UI-based):**
1. Implement in `days/dayN/web.php` — combined server + UI (routes API + serves HTML)
2. Implement in `days/dayN/demo_cases.php` — loaded via `/api/demo/cases` endpoint
3. Demo runs interactively: prompts inserted into input field, sent via chat API

**All days:**
- Load .env using `loadEnv()` — no Dotenv
- Must pass `make lint` (PSR-12 standard)
- Day-specific code should NOT be committed to main or other day branches

When updating shared code (src/LLMClient.php, tools/, Makefile):

1. Make changes on the branch you're currently on
2. Merge to main once tested
3. Merge main into all other day branches to keep them in sync

## Testing

Each day has demo cases defined in `demo_cases.php`:

```php
// days/dayN/demo_cases.php
return [
    1 => ['prompt' => 'Tell me a fun fact', 'options' => []],
    2 => ['prompt' => 'Write a poem', 'options' => ['temperature' => 0.7]],
];
```

Run with: `php days/dayN/cli.php --case=1` (used by make record).

## Important Gotchas

1. **Do NOT use Dotenv** — parse .env directly. Subprocess environment propagation fails otherwise.
2. **Do NOT hardcode API keys or client IDs** — always read from .env, fail with clear error if missing.
3. **Do NOT commit .env** — only commit .env.example. Use .gitignore to prevent accidents.
4. **Use cacert.pem for SSL verification** — configure in all Guzzle clients to avoid error 60 on Windows.
5. **OAuth is implicit flow** — no redirect URI, user copies token from browser URL.
6. **Subprocess environment passing** — when spawning child processes with Symfony Process, use `$process->setEnv($env)` to pass the loaded .env variables.
7. **Do NOT kill ffmpeg with taskkill** — send `'q'` to stdin via `proc_open` for graceful shutdown. See "FFmpeg Graceful Shutdown" section.
8. **PHP `exec()` blocks** — never use `exec()` to start long-running processes from a web server. Use `pclose(popen('start /B ...', 'r'))` or a background wrapper script.
9. **PHP resources can't be serialized** — `proc_open` handles, file handles, and pipes cannot be stored in files/sessions. Use a wrapper process to keep them alive across requests.
10. **Free port discovery** — use `stream_socket_server('tcp://127.0.0.1:0')` to let the OS assign a free port. Don't hardcode ports.

## Web Server Management (Day 6+)

The web server uses PID-file tracking for reliable start/stop:

```bash
make up       # Start: finds free port, writes .server.pid/.server.port, opens browser
make down     # Stop: reads PID from .server.pid, kills process, cleans up
make status   # Check: verifies PID is still running
```

Key files (all in `.gitignore`):
- `.server.pid` — server process ID
- `.server.port` — dynamically assigned port number
- `.server.log` — server output log

The `tools/serve.php` script:
1. Finds a free port via `stream_socket_server('tcp://127.0.0.1:0')`
2. Writes port to `.server.port`
3. Opens browser after 1-second delay (non-blocking: `pclose(popen('cmd /c "timeout /t 1 & start URL"', 'r'))`)
4. Starts PHP built-in server (blocking, shows logs until Ctrl+C)

## File Structure Reference

- `src/LLMClient.php` — Unified API client (shared)
- `src/Agent.php` — Basic agent wrapper (Day 6+)
- `src/TerminalIO.php` — Terminal UTF-8 handling for Windows (Day 6+)
- `days/dayN/cli.php` — Interactive CLI for day N (Day 1–5)
- `days/dayN/web.php` — Web UI for day N (Day 6+, routes API + serves HTML)
- `days/dayN/demo_cases.php` — Automated test cases for day N
- `tools/serve.php` — Web server launcher (free port + browser open)
- `tools/ffmpeg_wrapper.php` — FFmpeg lifecycle manager (graceful quit via stdin 'q')
- `tools/record.php` — FFmpeg orchestration (CLI-based, Day 1–5)
- `tools/upload_latest.php` — Upload latest video (auto-detects day from git branch)
- `tools/upload_video.php` — Interactive video uploader
- `tools/get_yandex_token.php` — OAuth token generation
- `tools/upload_yandex.php` — WebDAV upload implementation
- `composer.json` — Dependencies: guzzlehttp/guzzle, symfony/process
- `cacert.pem` — SSL certificate bundle (committed, required for HTTPS)
- `Makefile` — Shared (main branch) and day-specific (day branches) targets
