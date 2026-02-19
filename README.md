# AI Advent Challenge

PHP 8 CLI project for the AI Advent educational course. Each day explores a different aspect of working with LLMs, comparing responses across three providers: **Claude** (Anthropic), **Deepseek**, and **YandexGPT**.

## Quick Start

```bash
git clone https://github.com/stacmv/ai-advent.git
cd ai-advent
make install
make setup        # creates .env from template
# fill in API keys in .env
```

## Requirements

- PHP 8.0+
- Composer
- ffmpeg (for screen recording)

## API Keys Setup

Copy `.env.example` to `.env` and configure:

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
2. Add permissions: `cloud_api:disk.write`, `cloud_api:disk.info`, `cloud_api:disk.app_folder`
3. `.env`: `YANDEX_DISK_CLIENT_ID=your_client_id`
4. Run `make get-token` — script uses device authorization flow, no manual token copying needed

## Git Branches

Each day lives on its own branch. Shared code (LLMClient, tools) is on `main`.

| Branch | Topic |
|--------|-------|
| `main` | Shared code, tools, infrastructure |
| `day1` | Basic API Call — send same prompt to all 3 APIs, compare responses and timing |
| `day2` | Format Control — constrained vs unconstrained outputs, JSON formatting, stop sequences |
| `day3` | Reasoning — solve river crossing puzzle 4 ways: direct, step-by-step, prompt-first, expert group |
| `day4` | Temperature — 3x3 comparison table across 3 APIs x 3 temperatures |

## Day Workflow

Each day follows the same cycle:

```bash
git checkout dayN
make demo             # test the demo
make record           # screen capture + run demo (saves to recordings/)
# review the video
make upload           # upload to Yandex.Disk, prints submission message
git push -u origin dayN
```

After `make upload`, the script prints the submission message:
```
========================================
Код: https://github.com/stacmv/ai-advent/tree/dayN
Видео: https://disk.yandex.ru/d/XXXXX
========================================
```

## Bootstrapping a New Day

From any branch:

```bash
make next-day N=5 T="Function Calling"
```

This creates a `day5` branch from `main` with boilerplate:
- `days/day5/cli.php` — LLMClient setup, provider loop, loadEnv
- `days/day5/demo_cases.php` — demo prompt template
- `Makefile` — all targets pre-configured for day 5

Supports any day number — skip weekends as needed.

## Available Commands

### On any branch
```bash
make install          # composer install
make setup            # create .env from template
make get-token        # Yandex.Disk OAuth token (device flow)
make lint             # PSR-12 code style check
make next-day N=5     # bootstrap new day
make clean            # remove recordings/
```

### On day branches
```bash
make test             # run interactively (prompts for input)
make demo             # run with pre-set demo cases
make record           # ffmpeg screen capture + demo
make upload           # upload latest video, print submission links
```

## Architecture

### LLMClient (`src/LLMClient.php`)

Unified interface for all three providers:

```php
$client = new LLMClient('claude', $apiKey);
$response = $client->chat($prompt, ['temperature' => 0.7, 'max_tokens' => 200]);
```

Providers: `claude` (claude-haiku-4-5), `deepseek` (deepseek-chat), `yandexgpt` (yandexgpt-lite/latest).

### Tools

| Script | Purpose |
|--------|---------|
| `tools/record.php` | ffmpeg screen capture (1200x700, 20s) + run demo cases |
| `tools/upload_latest.php` | Find latest dayN video, upload to Yandex.Disk via REST API |
| `tools/upload_video.php` | Interactive — choose which video to upload |
| `tools/upload_yandex.php` | Yandex.Disk REST API: create dir, upload, publish, get share link |
| `tools/get_yandex_token.php` | OAuth device flow — request code, user authorizes, script polls for token |
| `tools/bootstrap_day.php` | Scaffold new day branch with cli.php, demo_cases.php, Makefile |

### Environment

All scripts parse `.env` directly via a `loadEnv()` helper (no Dotenv library). This ensures reliable behavior in subprocesses.

## License

Educational project for AI Advent Challenge.
