# AI Advent Challenge

PHP 8 CLI project for the AI Advent educational course. Each day explores a different aspect of working with LLMs, comparing responses across three providers: **Claude** (Anthropic), **Deepseek**, and **YandexGPT**.

## Quick Start

```bash
git clone https://github.com/stacmv/ai-advent.git
cd ai-advent
make install
make setup            # creates .env from template
# fill in API keys in .env (see API Keys Setup below)
make get-token        # one-time: get Yandex.Disk OAuth token
```

## Requirements

- PHP 8.0+
- Composer
- ffmpeg (for screen recording)

## Make Commands

All operations are done via `make`. Run `make` or `make help` on any branch to see available commands.

### Setup (run once)

| Command | Description |
|---------|-------------|
| `make install` | Install PHP dependencies via Composer |
| `make setup` | Create `.env` from `.env.example` template |
| `make get-token` | Get Yandex.Disk OAuth token via device authorization flow. Opens a URL, you enter a short code, token is saved automatically |

### Working on a Day (CLI days: 1–5)

| Command | Description |
|---------|-------------|
| `make test` | Run the day's CLI interactively (prompts for input) |
| `make demo` | Run with pre-configured demo cases (non-interactive) |
| `make record` | Dry-run demo to measure timing, then screen-record the demo with ffmpeg |
| `make upload` | Upload latest recorded video to Yandex.Disk, print submission message |
| `make lint` | Check code style (PSR-12) |
| `make clean` | Remove `recordings/` directory |

### Working on a Day (Web UI days: 6+)

| Command | Description |
|---------|-------------|
| `make up` / `make serve` | Start PHP dev server, auto-open browser |
| `make down` | Stop the server |
| `make status` | Show server status (running/stopped, port, PID) |
| `make upload` | Upload latest recorded video to Yandex.Disk |
| `make lint` | Check code style (PSR-12) |

Demo, Record, Stop, and Upload are available as buttons in the Web UI header.

### Bootstrapping a New Day

| Command | Description |
|---------|-------------|
| `make next-day N=5 T="Topic"` | Create `day5` branch from `main` with all boilerplate |

The `T` parameter (title) is optional. Any day number is accepted — skip weekends as needed (e.g. day4 → day7).

This creates:
- `days/dayN/cli.php` — boilerplate with loadEnv, LLMClient, provider loop
- `days/dayN/demo_cases.php` — demo prompt template (edit before recording)
- `Makefile` — all targets pre-configured for that day

#### Complete new day workflow

```bash
# From any branch, bootstrap day 5
make next-day N=5 T="Function Calling"

# You're now on day5 branch with boilerplate committed
# Edit the implementation
vim days/day5/cli.php
vim days/day5/demo_cases.php

# Test it
make demo

# Record screencast (auto-calculates duration)
make record

# Review the video in recordings/ directory, then upload
make upload

# Push and submit
git push -u origin day5
```

After `make upload` you get the submission message:
```
========================================
Код: https://github.com/stacmv/ai-advent/tree/day5
Видео: https://disk.yandex.ru/d/XXXXX
========================================
```

## Git Branches

Each day lives on its own branch. Shared code (LLMClient, tools) is on `main`.

| Branch | Topic |
|--------|-------|
| `main` | Shared code, tools, infrastructure |
| `day1` | Basic API Call — send same prompt to all 3 APIs, compare timing |
| `day2` | Format Control — constrained vs unconstrained, JSON, stop sequences |
| `day3` | Reasoning — river crossing puzzle solved 4 ways |
| `day4` | Temperature — 3x3 table across APIs x temperatures |
| `day5` | Model Versions — compare 4 YandexGPT tiers (Lite, Pro, Pro RC, Alice AI LLM) |
| `day6` | Agent Architecture — YandexGPT agent with Web UI |
| `day7` | Persistent History — multi-turn conversation saved across sessions |
| `day8` | Token Counting — per-turn and cumulative usage with limit warnings |
| `day9` | Context Compression — auto-summarize old context to stay within limits |

## API Keys Setup

Copy `.env.example` to `.env` (`make setup`) and configure:

### Claude (Anthropic)
1. https://console.anthropic.com/ → **Settings → API Keys**
2. `.env`: `ANTHROPIC_API_KEY=sk-ant-...`

### Deepseek
1. https://platform.deepseek.com/ → **API Keys**
2. `.env`: `DEEPSEEK_API_KEY=sk-...`
3. Top up balance (~$0.14/million tokens)

### YandexGPT
1. https://console.yandex.cloud/ → create folder → copy **Folder ID**
2. Create service account with `ai.languageModels.user` role → generate API key
3. `.env`:
   ```
   YANDEX_API_KEY=AQVN...
   YANDEX_FOLDER_ID=b1g...
   ```

### Yandex.Disk (for video upload)
1. Register an OAuth app at https://oauth.yandex.ru/
2. Add permissions: `cloud_api:disk.write`, `cloud_api:disk.info`, `cloud_api:disk.app_folder`, `cloud_api:disk.read`
3. `.env`: `YANDEX_DISK_CLIENT_ID=your_client_id`
4. `make get-token` — enter a short code on Yandex page, token is saved automatically

## Architecture

### LLMClient (`src/LLMClient.php`)

Unified interface for all three providers:

```php
$client = new LLMClient('claude', $apiKey);
$response = $client->chat($prompt, ['temperature' => 0.7, 'max_tokens' => 200]);
```

Providers: `claude` (claude-haiku-4-5), `deepseek` (deepseek-chat), `yandexgpt` (yandexgpt-lite/latest).

### Environment

All scripts parse `.env` directly via a `loadEnv()` helper (no Dotenv library). This ensures reliable behavior in subprocesses.

## License

Educational project for AI Advent Challenge.
