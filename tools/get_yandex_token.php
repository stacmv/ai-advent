<?php

/**
 * Get Yandex.Disk OAuth Token
 *
 * If YANDEX_CLIENT_ID and YANDEX_CLIENT_SECRET are set in .env,
 * uses them automatically to get token.
 *
 * Otherwise, uses implicit flow (no app registration needed).
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
// Support both YANDEX_CLIENT_ID and YANDEX_DISK_CLIENT_ID
$clientId = $env['YANDEX_DISK_CLIENT_ID'] ?? ($env['YANDEX_CLIENT_ID'] ?? null);

// If we have client ID, use it for implicit flow
if (!empty($clientId)) {
    echo "[*] Found client ID in .env\n";
    echo "[*] Using implicit flow with your client ID...\n\n";
    useImplicitFlow($clientId);
    exit(0);
}

// Otherwise show options
echo "[*] Set client ID in .env to auto-generate token:\n";
echo "    Add this line to .env:\n";
echo "    YANDEX_DISK_CLIENT_ID=your_app_client_id\n\n";

echo "[*] Or use Implicit Flow (No app registration needed):\n";
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

function useImplicitFlow($clientId) {
    echo "[*] Step 1: Visit this authorization URL:\n";
    $authUrl = 'https://oauth.yandex.ru/authorize?' . http_build_query([
        'response_type' => 'token',
        'client_id' => $clientId
    ]);

    echo "    {$authUrl}\n\n";
    echo "[*] Step 2: After authorizing, copy the 'access_token' from the URL:\n";
    echo "    Token: ";
    $token = trim(fgets(STDIN));

    if (empty($token)) {
        echo "Error: No token provided\n";
        exit(1);
    }

    // Test the token
    echo "\n[*] Testing token...\n";
    try {
        $client = new \GuzzleHttp\Client();
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
    echo "[+] You can now use: make upload\n";
}
