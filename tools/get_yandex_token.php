<?php

// phpcs:disable PSR1.Files.SideEffects

/**
 * Get Yandex.Disk OAuth Token via Device Authorization Flow
 *
 * Requires YANDEX_DISK_CLIENT_ID in .env.
 * Optionally uses YANDEX_DISK_CLIENT_SECRET if set.
 *
 * Flow:
 * 1. Script requests a device code from Yandex
 * 2. User visits URL and enters the code shown
 * 3. Script polls for the token automatically
 */

require __DIR__ . '/../vendor/autoload.php';

// Load .env
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

$env = loadEnv(__DIR__ . '/../.env');

echo "=== Yandex.Disk OAuth Token Generator ===\n\n";

// Check if we already have a token
if (!empty($env['YANDEX_DISK_TOKEN'])) {
    echo "[+] YANDEX_DISK_TOKEN already set in .env\n";
    echo "[?] Generate a new one? (yes/no): ";
    $input = trim(fgets(STDIN));
    if ($input !== 'yes' && $input !== 'y') {
        echo "Keeping existing token.\n";
        exit(0);
    }
}

// Check if we have client ID in .env
$clientId = $env['YANDEX_DISK_CLIENT_ID'] ?? ($env['YANDEX_CLIENT_ID'] ?? null);
$clientSecret = $env['YANDEX_DISK_CLIENT_SECRET'] ?? ($env['YANDEX_CLIENT_SECRET'] ?? null);

if (empty($clientId)) {
    echo "Error: YANDEX_DISK_CLIENT_ID not found in .env\n\n";
    echo "Add your client ID to .env:\n";
    echo "    YANDEX_DISK_CLIENT_ID=your_app_client_id\n\n";
    echo "Then run: make get-token\n";
    exit(1);
}

// SSL certificate for Windows
$certPath = __DIR__ . '/../cacert.pem';
$clientOptions = file_exists($certPath) ? ['verify' => $certPath] : [];
$client = new \GuzzleHttp\Client($clientOptions);

// Step 1: Request device code
echo "[*] Requesting device code...\n";

$codeParams = ['client_id' => $clientId];
$codeResponse = $client->post('https://oauth.yandex.ru/device/code', [
    'form_params' => $codeParams,
    'http_errors' => false,
]);

$codeData = json_decode($codeResponse->getBody(), true);

if (empty($codeData['device_code']) || empty($codeData['user_code'])) {
    echo "[-] Failed to get device code\n";
    echo "[-] Response: " . $codeResponse->getBody() . "\n";
    exit(1);
}

$deviceCode = $codeData['device_code'];
$userCode = $codeData['user_code'];
$verificationUrl = $codeData['verification_url'] ?? 'https://oauth.yandex.ru/verification_code';
$interval = $codeData['interval'] ?? 5;
$expiresIn = $codeData['expires_in'] ?? 300;

// Step 2: Show user instructions
echo "\n[*] Step 1: Visit this URL:\n";
echo "    {$verificationUrl}\n\n";
echo "[*] Step 2: Enter this code:\n";
echo "    {$userCode}\n\n";
echo "[*] Waiting for authorization (expires in {$expiresIn}s)...\n";

// Step 3: Poll for token
$tokenParams = [
    'grant_type' => 'device_code',
    'code' => $deviceCode,
    'client_id' => $clientId,
];
if (!empty($clientSecret)) {
    $tokenParams['client_secret'] = $clientSecret;
}

$deadline = time() + $expiresIn;
$token = null;

while (time() < $deadline) {
    sleep($interval);

    $tokenResponse = $client->post('https://oauth.yandex.ru/token', [
        'form_params' => $tokenParams,
        'http_errors' => false,
    ]);

    $tokenData = json_decode($tokenResponse->getBody(), true);

    if (!empty($tokenData['access_token'])) {
        $token = $tokenData['access_token'];
        break;
    }

    $error = $tokenData['error'] ?? 'unknown';
    if ($error === 'authorization_pending') {
        echo ".";
        continue;
    }

    if ($error === 'slow_down') {
        $interval += 1;
        continue;
    }

    // Any other error is fatal
    echo "\n[-] Error: {$error}\n";
    if (!empty($tokenData['error_description'])) {
        echo "[-] {$tokenData['error_description']}\n";
    }
    exit(1);
}

if (!$token) {
    echo "\n[-] Authorization timed out. Run again.\n";
    exit(1);
}

echo "\n[+] Token received!\n";

// Step 4: Test the token via REST API (cloud_api:disk.info)
echo "[*] Testing token...\n";
try {
    $testResponse = $client->get('https://cloud-api.yandex.net/v1/disk/', [
        'headers' => ['Authorization' => 'OAuth ' . $token],
        'http_errors' => false,
    ]);

    $code = $testResponse->getStatusCode();
    if ($code === 200) {
        echo "[+] Token is valid!\n";
    } else {
        echo "[!] Warning: Disk API returned HTTP {$code} (token may still work)\n";
    }
} catch (Exception $e) {
    echo "[!] Warning: Could not test token: " . $e->getMessage() . "\n";
}

// Save regardless — the OAuth server gave us a valid token
saveToken($token);
exit(0);

function saveToken($token)
{
    $envFile = __DIR__ . '/../.env';

    $lines = file($envFile, FILE_IGNORE_NEW_LINES);

    $found = false;
    foreach ($lines as $i => $line) {
        if (strpos($line, 'YANDEX_DISK_TOKEN=') === 0) {
            $lines[$i] = "YANDEX_DISK_TOKEN={$token}";
            $found = true;
            break;
        }
    }

    if (!$found) {
        $lines[] = "YANDEX_DISK_TOKEN={$token}";
    }

    file_put_contents($envFile, implode("\n", $lines) . "\n");

    echo "[+] Token saved to .env\n";
    echo "[+] You can now use: make upload\n";
}
