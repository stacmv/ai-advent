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
1. Create Yandex.Disk OAuth app at https://console.yandex.cloud/
   - Navigate to **App registrations** → **Create application**
   - Copy the **Client ID** and **Client Secret** from your app
   - Add to `.env`:
     ```
     YANDEX_DISK_CLIENT_ID=your_client_id
     YANDEX_DISK_CLIENT_SECRET=your_client_secret
     ```
2. Run `make get-token` to obtain and save the access token
3. The token will be saved as `YANDEX_DISK_TOKEN` in your `.env`

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

### Full Orchestration (Record + Upload)

```bash
php tools/record.php --day=1
```

This will:
1. Start screen recording via ffmpeg
2. Run all demo cases for the selected day
3. Stop recording and compress the video
4. Upload to Yandex.Disk
5. Generate and output public links

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
- Test all 3 APIs across 3 temperatures (0.0, 0.7, 1.2)
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
