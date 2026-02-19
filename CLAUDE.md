# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**AI Advent Challenge** — A PHP 8 CLI project demonstrating LLM capabilities across 4 different experiments:
- Day 1: Basic API comparison
- Day 2: Response format control
- Day 3: Reasoning approaches
- Day 4: Temperature comparison

Supports 3 LLM APIs: Claude (Anthropic), Deepseek, and YandexGPT.

## Architecture & Git Strategy

This is a **multi-branch repository** with a shared main branch and day-specific branches:

```
main branch (shared code)
├── src/LLMClient.php          # Unified interface for all 3 APIs
├── tools/                     # Recording, uploading, token generation
├── Makefile, README.md, .env.example, cacert.pem
└── days/ (empty directory structure only)

day1 branch (includes main + day1-specific code)
├── all of main
└── days/day1/cli.php, demo_cases.php

day2, day3, day4 branches (same pattern)
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

# On day branches
make test                # Run interactive CLI (prompts for input)
make demo                # Run automated demo with pre-set cases
make record              # Start ffmpeg recording + run demo (separate from upload)
make upload              # Upload latest video for this day

# Code quality
make lint                # Check PSR-12 style
make lint-fix            # Auto-fix PSR-12 violations

# Cleanup
make clean               # Remove recordings directory
```

## Recording & Video Upload Workflow

Recording and upload are **intentionally separate commands**:

1. `make record` — Starts ffmpeg screen capture (~1200x700px at 30fps), runs demo, stops recording
   - Creates `recordings/dayN_TIMESTAMP.mp4`
   - Does NOT automatically upload

2. Review the video manually

3. `make upload` — Uploads latest dayN video to Yandex.Disk, generates public share link
   - Detects day from git branch name automatically
   - Auto-generates token if `YANDEX_DISK_CLIENT_ID` and `YANDEX_DISK_CLIENT_SECRET` exist

This separation allows manual review before sharing.

## Branch-Specific Development

When working on a day branch:

1. Implement in `days/dayN/cli.php` — interactive mode that prompts user for input
2. Implement in `days/dayN/demo_cases.php` — array of automated test cases for `make demo`
3. Both files load .env using `loadEnv()` — no Dotenv
4. Both must pass `make lint` (PSR-12 standard)
5. Day-specific code should NOT be committed to main or other day branches

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

## File Structure Reference

- `src/LLMClient.php` — Unified API client (shared)
- `days/dayN/cli.php` — Interactive CLI for day N
- `days/dayN/demo_cases.php` — Automated test cases for day N
- `tools/record.php` — FFmpeg orchestration
- `tools/upload_latest.php` — Upload latest video (auto-detects day from git branch)
- `tools/upload_video.php` — Interactive video uploader
- `tools/get_yandex_token.php` — OAuth token generation
- `tools/upload_yandex.php` — WebDAV upload implementation
- `composer.json` — Dependencies: guzzlehttp/guzzle, symfony/process
- `cacert.pem` — SSL certificate bundle (committed, required for HTTPS)
- `Makefile` — Shared (main branch) and day-specific (day branches) targets
