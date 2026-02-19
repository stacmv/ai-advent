<?php

/**
 * Get Yandex.Disk OAuth Token
 *
 * Usage:
 *   php tools/get_yandex_token.php
 *   php tools/get_yandex_token.php --client-id=YOUR_ID --client-secret=YOUR_SECRET
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

// Load .env
function loadEnv($filePath) {
    $config = [];
    if (!file_exists($filePath)) {
        return $config;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        $config[$key] = $value;
    }
    return $config;
}

$env = loadEnv(__DIR__ . '/../.env');

// Get client ID and secret from args or .env
$clientId = null;
$clientSecret = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--client-id=') === 0) {
        $clientId = str_replace('--client-id=', '', $arg);
    }
    if (strpos($arg, '--client-secret=') === 0) {
        $clientSecret = str_replace('--client-secret=', '', $arg);
    }
}

$clientId = $clientId ?: ($env['YANDEX_CLIENT_ID'] ?? null);
$clientSecret = $clientSecret ?: ($env['YANDEX_CLIENT_SECRET'] ?? null);

echo "=== Yandex.Disk OAuth Token Generator ===\n\n";

// Check if we already have a token
if (!empty($env['YANDEX_DISK_TOKEN'])) {
    echo "[*] YANDEX_DISK_TOKEN already set in .env\n";
    echo "[?] Do you want to generate a new one? (yes/no): ";
    $input = trim(fgets(STDIN));
    if ($input !== 'yes' && $input !== 'y') {
        echo "Exiting.\n";
        exit(0);
    }
}

// Check if we have client credentials
if (empty($clientId) || empty($clientSecret)) {
    echo "[!] Option 1: Using Yandex OAuth App\n";
    echo "    If you have a registered Yandex app with Client ID and Secret:\n";
    echo "    php tools/get_yandex_token.php --client-id=YOUR_ID --client-secret=YOUR_SECRET\n\n";

    echo "[*] Option 2: Using Implicit Flow (Recommended for personal use)\n";
    echo "    This requires no app registration.\n";
    echo "    Visit this URL and authorize:\n\n";

    $implicitUrl = 'https://oauth.yandex.ru/authorize?response_type=token&client_id=04d700d432884c4381c926e166bc5be8';
    echo "    {$implicitUrl}\n\n";
    echo "    After authorizing, copy the 'access_token' from the URL and paste it here:\n";
    echo "    Token: ";
    $token = trim(fgets(STDIN));

    if (empty($token)) {
        echo "Error: No token provided\n";
        exit(1);
    }

    // Test the token
    echo "\n[*] Testing token...\n";
    try {
        $client = new Client();
        $response = $client->get('https://cloud-api.yandex.net/v1/disk', [
            'headers' => ['Authorization' => 'OAuth ' . $token],
            'http_errors' => false
        ]);

        if ($response->getStatusCode() === 200) {
            echo "[+] Token is valid!\n";
            saveToken($token);
            exit(0);
        } else {
            echo "[-] Token validation failed (HTTP " . $response->getStatusCode() . ")\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "[-] Error testing token: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// If we have client credentials, use authorization code flow
echo "[*] Using OAuth Authorization Code Flow\n";
echo "[*] Client ID: {$clientId}\n\n";

// For this, we need a redirect URI
$redirectUri = 'http://localhost:8888/callback';

echo "[*] Step 1: Visiting authorization URL...\n";
$authUrl = 'https://oauth.yandex.ru/authorize?' . http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => 'disk'
]);

echo "    {$authUrl}\n\n";
echo "[*] Step 2: Please visit the URL above and authorize.\n";
echo "[*] Step 3: After authorizing, you'll be redirected to localhost.\n";
echo "[*] Copy the 'code' parameter from the redirect URL and paste it here:\n";
echo "    Authorization Code: ";
$authCode = trim(fgets(STDIN));

if (empty($authCode)) {
    echo "Error: No authorization code provided\n";
    exit(1);
}

// Exchange authorization code for access token
echo "\n[*] Exchanging authorization code for access token...\n";
try {
    $client = new Client();
    $response = $client->post('https://oauth.yandex.ru/token', [
        'form_params' => [
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ],
        'http_errors' => false
    ]);

    $data = json_decode($response->getBody(), true);

    if (!isset($data['access_token'])) {
        echo "[-] Failed to get token: " . json_encode($data) . "\n";
        exit(1);
    }

    $token = $data['access_token'];
    echo "[+] Successfully obtained access token!\n";

    // Test the token
    echo "[*] Testing token...\n";
    $testResponse = $client->get('https://cloud-api.yandex.net/v1/disk', [
        'headers' => ['Authorization' => 'OAuth ' . $token],
        'http_errors' => false
    ]);

    if ($testResponse->getStatusCode() === 200) {
        echo "[+] Token is valid!\n";
        saveToken($token);
        exit(0);
    } else {
        echo "[-] Token validation failed (HTTP " . $testResponse->getStatusCode() . ")\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "[-] Error: " . $e->getMessage() . "\n";
    exit(1);
}

function saveToken($token) {
    $envFile = __DIR__ . '/../.env';

    // Read current .env
    $lines = file($envFile, FILE_IGNORE_NEW_LINES);

    // Update or add YANDEX_DISK_TOKEN
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

    // Write back to .env
    file_put_contents($envFile, implode("\n", $lines) . "\n");

    echo "[+] Token saved to .env\n";
    echo "[+] You can now use: php tools/record.php --day=1\n";
}
