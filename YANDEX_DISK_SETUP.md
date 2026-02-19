# Yandex.Disk Setup Guide

This guide explains how to configure Yandex.Disk for uploading recorded videos.

## Quick Start (Recommended)

The easiest way is to use the automated token generator:

```bash
make get-token
```

This will guide you through the process interactively.

## Three Ways to Get a Token

### Option 1: Automatic with No App Registration (Simplest)

```bash
make get-token
```

Or:

```bash
php tools/get_yandex_token.php
```

The script will:
1. Ask you to visit an authorization URL
2. Guide you to copy the token from the URL
3. Save it automatically to `.env`

**No setup needed!** This works immediately.

---

### Option 2: Using Client ID + Client Secret (For Apps)

If you have a registered Yandex application:

```bash
php tools/get_yandex_token.php --client-id=YOUR_ID --client-secret=YOUR_SECRET
```

Or add to `.env`:

```bash
YANDEX_CLIENT_ID=your_app_client_id
YANDEX_CLIENT_SECRET=your_app_secret
```

Then the recording script will auto-generate the token when needed.

**How to register an app:**
1. Visit: https://oauth.yandex.ru/
2. Create a new application
3. Get your Client ID and Secret
4. Set redirect URI to: `http://localhost:8888/callback`

---

### Option 3: Manual Token Entry

If you already have a token:

1. Visit: `https://oauth.yandex.ru/authorize?response_type=token&client_id=04d700d432884c4381c926e166bc5be8`
2. Click Authorize
3. Copy the `access_token` from the URL
4. Add to `.env`:
   ```
   YANDEX_DISK_TOKEN=y0_AgAA...
   ```

---

## Verify Your Token

After setting up, verify it works:

```bash
php tools/get_yandex_token.php
```

If you already have a token, it will ask if you want to generate a new one.

---

## Using with Recording

Once you have a token configured, you can record and automatically upload:

```bash
# Single day
php tools/record.php --day=1

# Or using make
make record-day1
```

The script will:
1. Record your screen
2. Run demo cases
3. Upload to Yandex.Disk
4. Output public share links

---

## Troubleshooting

### "Token validation failed"
- Your token may be expired
- Run `make get-token` again to get a fresh one

### "HTTP 401 Unauthorized"
- Token is invalid or revoked
- Visit the authorization URL again and get a new token

### "Could not get public link"
- File uploaded successfully but sharing failed
- Check your Yandex.Disk account directly
- The file is at: `/ai-advent/day{N}_TIMESTAMP.mp4`

### "Client error: 400 Bad Request"
- Your credentials are incorrect
- Verify CLIENT_ID and CLIENT_SECRET match your registered app

---

## Token Expiration

OAuth tokens from Yandex typically expire after some time. If you get authentication errors:

```bash
make get-token
```

This will generate a fresh token.

---

## Security Notes

- **Never commit** `.env` file to git (it's in `.gitignore`)
- Keep your tokens private
- Regenerate tokens if you think they're compromised
- Tokens are stored locally in `.env` only

---

## Manual Upload

If token generation fails, you can still upload manually:

1. Upload video to Yandex.Disk manually via web interface
2. Right-click file → Share → Get link
3. Share the link

The video file will be saved locally in `recordings/` directory regardless of upload success.
