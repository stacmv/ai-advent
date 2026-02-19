# Make Upload Command Guide

The `make upload` command uploads the **latest video** for the current day branch automatically.

## Quick Start

```bash
# On day1 branch
git checkout day1

# Record your demo
make record

# Review the video (in recordings/ directory)

# When ready, upload the latest day1 video
make upload
```

## How It Works

### Automatic Day Detection

The `make upload` command automatically detects which day branch you're on and uploads the latest video for that day.

```bash
git checkout day1
make upload        # Uploads latest day1_*.mp4

git checkout day2
make upload        # Uploads latest day2_*.mp4

git checkout day3
make upload        # Uploads latest day3_*.mp4

git checkout day4
make upload        # Uploads latest day4_*.mp4
```

### What the Command Does

1. **Detects day** from current git branch (day1, day2, day3, or day4)
2. **Finds latest video** matching the pattern `dayN_*.mp4` in `recordings/`
3. **Shows video info** (filename, size, last modified)
4. **Checks for token** (YANDEX_DISK_TOKEN in .env)
5. **Auto-generates token** if client credentials exist
6. **Uploads to Yandex.Disk**
7. **Generates public share link**

## Complete Workflow

### Step 1: Record
```bash
git checkout day1
make record
```

Output:
```
[*] Recording will capture top-left 1200x700 area for 20 seconds
[*] Move terminal to TOP-LEFT corner and make sure it's FOCUSED
[*] When ready, press ENTER to start:

[+] Starting in 3 seconds...

1. Starting screen capture...
2. Loading demo cases...
3. Running demo cases...
...
[+] Video saved: recordings/day1_2024-02-19_143022.mp4
[+] Size: 15234567 bytes
[+] Duration: ~20 seconds
```

### Step 2: Review
```bash
# Open the video file from recordings/ directory
# Watch and verify quality before uploading
```

### Step 3: Setup Token (One Time)
```bash
make get-token
```

Follows interactive prompts to get your Yandex.Disk OAuth token.

### Step 4: Upload
```bash
make upload
```

Output:
```
Uploading latest Day 1 video...
=== Upload Latest Video for Day 1 ===

[*] Latest video for day 1: day1_2024-02-19_143022.mp4
[*] Size: 14.56 MB
[*] Modified: 2024-02-19 14:30:22

[*] Uploading to Yandex.Disk...
   Creating directory...
   Uploading file...
   Upload successful!
   Generating public link...
   Public link: https://disk.yandex.ru/d/abcDEF123456

[+] Upload successful!
[+] Public link: https://disk.yandex.ru/d/abcDEF123456
[+] Done!
```

## Features

✅ **Automatic day detection** - No need to specify which day
✅ **Latest video selection** - Always uploads most recent
✅ **One command** - `make upload` does everything
✅ **Token auto-generation** - Can use client credentials
✅ **Public share links** - Ready to share immediately
✅ **File info display** - Shows size and modification time

## Alternative: Manual Specification

If you need to upload a specific day's video from a different branch:

```bash
# On day1 branch, but want to upload day2 video
php tools/upload_latest.php 2

# Or on any branch
php tools/upload_latest.php 3
```

## Troubleshooting

### "No recordings found for day X"
- You haven't recorded any videos for that day yet
- Run `make record` first

### "YANDEX_DISK_TOKEN not set"
- Get token: `make get-token`
- Or set directly in `.env`: `YANDEX_DISK_TOKEN=y0_AgAA...`

### "Token validation failed"
- Token may have expired
- Get a new one: `make get-token`

### "Upload failed"
- Check internet connection
- Verify token is still valid
- Check Yandex.Disk quota

## Complete Day Workflow Example

```bash
# Day 1: Record and Upload
git checkout day1
make install          # First time only
make setup            # First time only
make get-token        # First time only

make demo             # Test the demo works
make record           # Record the video
# ... review video ...
make upload           # Upload to Yandex.Disk

# Share the public link
# Video: https://disk.yandex.ru/d/SHARE_ID
# Code: https://github.com/stacmv/ai-advent/tree/day1
```

## Files Involved

- **Makefile** - Contains `make upload` target
- **tools/upload_latest.php** - Does the actual work
- **tools/get_yandex_token.php** - Gets/generates token
- **tools/upload_yandex.php** - WebDAV upload logic
- **recordings/** - Where videos are saved
