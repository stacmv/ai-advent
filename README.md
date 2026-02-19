# AI Advent Challenge

A PHP 8 CLI project exploring LLM capabilities across Claude, Deepseek, and YandexGPT APIs.

## Project Structure

```
ai-advent/
├── src/
│   └── LLMClient.php         # Unified LLM API client
├── days/
│   ├── day1/                 # Basic API calls
│   ├── day2/                 # Format control
│   ├── day3/                 # Reasoning approaches
│   └── day4/                 # Temperature comparison
├── tools/
│   ├── record.php            # Orchestration script
│   └── upload_yandex.php     # Yandex.Disk upload helper
├── composer.json
├── .env.example
└── README.md
```

## Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure API Keys

Copy `.env.example` to `.env` and fill in your API keys:

```bash
cp .env.example .env
```

#### Claude (Anthropic)
1. Go to https://console.anthropic.com/
2. Navigate to **Settings → API Keys**
3. Create a key and add to `.env`: `ANTHROPIC_API_KEY=sk-ant-...`

#### Deepseek
1. Go to https://platform.deepseek.com/
2. Create API key and add to `.env`: `DEEPSEEK_API_KEY=sk-...`
3. Top up balance (very affordable, ~$0.14/million tokens)

#### YandexGPT
1. Create account at https://console.yandex.cloud/
2. Create a folder and get **Folder ID**
3. Create service account with `ai.languageModels.user` role
4. Generate API key and add to `.env`:
   - `YANDEX_API_KEY=AQVN...`
   - `YANDEX_FOLDER_ID=b1g...`

#### Yandex.Disk (for video upload)

**Option 1: Automatic Token Generation (Recommended)**
```bash
make get-token
```
Or:
```bash
php tools/get_yandex_token.php
```

This will guide you through getting a token and automatically save it to `.env`.

**Option 2: Manual Token (Implicit Flow)**
1. Visit: `https://oauth.yandex.ru/authorize?response_type=token&client_id=04d700d432884c4381c926e166bc5be8`
2. Authorize and copy the `access_token` from URL
3. Add to `.env`: `YANDEX_DISK_TOKEN=y0_AgAA...`

**Option 3: Using Client Credentials (if you have a registered app)**
1. Register your app at https://oauth.yandex.ru/
2. Add to `.env`:
   ```
   YANDEX_CLIENT_ID=your_client_id
   YANDEX_CLIENT_SECRET=your_client_secret
   ```
3. Token will be auto-generated when needed (during recording)

## Usage

### Run Individual Days

```bash
# Day 1: Basic API calls
php days/day1/cli.php

# Day 2: Format control
php days/day2/cli.php

# Day 3: Reasoning approaches
php days/day3/cli.php

# Day 4: Temperature comparison
php days/day4/cli.php
```

### Run with Demo Cases (for recording)

```bash
php days/day1/cli.php --case=1
php days/day2/cli.php --case=1
php days/day3/cli.php --case=1
php days/day4/cli.php --case=1
```

### Recording and Upload (Two-Step Process)

**Step 1: Record the demo**
```bash
php tools/record.php --day=1
```

This will:
1. Start screen recording via ffmpeg (1200x700 top-left corner for 20 seconds)
2. Run all demo cases for the selected day
3. Save video to `recordings/day1_TIMESTAMP.mp4`

The video is saved and you can review it before uploading.

**Step 2: Upload to Yandex.Disk (when ready)**

First, get a token (one time):
```bash
make get-token
```

Then upload your video:
```bash
php tools/upload_video.php
```

This will:
1. Show list of available recordings
2. Let you select which video to upload
3. Upload to Yandex.Disk
4. Generate public share link

**Or specify file directly:**
```bash
php tools/upload_video.php day1_2024-02-19_143022.mp4
```

## Day Descriptions

### Day 1: Basic API Call
- Send identical prompt to all 3 APIs
- Display responses with timing comparison
- Explore basic LLM capabilities

### Day 2: Response Format Control
- Compare constrained vs unconstrained outputs
- Test JSON formatting, max_tokens, and stop sequences
- Show how instruction following varies by provider

### Day 3: Reasoning Approaches
- Solve a logic puzzle (river crossing) 4 ways:
  1. Direct answer
  2. Step-by-step reasoning
  3. Generate-prompt-first approach
  4. Expert group discussion
- Compare response depth and approach effectiveness

### Day 4: Temperature Comparison
- Test YandexGPT across 3 temperatures (0.0, 0.5, 1.0)
- 3×3 table showing how temperature affects creativity
- Analysis of deterministic vs creative outputs

## Git Branches

- `main` — Shared code and orchestration scripts
- `day1` — Day 1 implementation
- `day2` — Day 2 implementation
- `day3` — Day 3 implementation
- `day4` — Day 4 implementation

## Requirements

- PHP 8.0+
- Composer
- ffmpeg (for recording)
- Windows GDI support (for screen capture on Windows)

## License

Educational project for AI Advent Challenge.
