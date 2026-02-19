<?php

/**
 * Upload file to Yandex.Disk via REST API and return share link
 *
 * Returns closure that can be called with: uploadFile(filePath, fileName, token)
 *
 * Uses REST API (cloud-api.yandex.net) — requires cloud_api:disk.write permission.
 */

use GuzzleHttp\Client;

return function (string $filePath, string $fileName, string $token): array {
    if (!file_exists($filePath)) {
        return ['error' => 'File not found: ' . $filePath];
    }

    $fileSize = filesize($filePath);
    $uploadDir = 'ai-advent';
    $diskPath = "/{$uploadDir}/{$fileName}";

    // SSL certificate path for Windows
    $certPath = __DIR__ . '/../cacert.pem';
    $clientOptions = file_exists($certPath) ? ['verify' => $certPath] : [];
    $authHeader = ['Authorization' => 'OAuth ' . $token];

    echo "   Uploading to Yandex.Disk: {$fileName} ({$fileSize} bytes)\n";

    try {
        $client = new Client($clientOptions);

        // Step 1: Create directory (ignore 409 = already exists)
        echo "   Creating directory...\n";
        $mkdirResponse = $client->put(
            "https://cloud-api.yandex.net/v1/disk/resources",
            [
                'headers' => $authHeader,
                'query' => ['path' => "/{$uploadDir}"],
                'http_errors' => false,
            ]
        );
        $mkdirCode = $mkdirResponse->getStatusCode();
        if ($mkdirCode >= 400 && $mkdirCode !== 409) {
            return ['error' => "Failed to create directory (HTTP {$mkdirCode}): "
                . $mkdirResponse->getBody()];
        }

        // Step 2: Get upload URL
        echo "   Getting upload URL...\n";
        $uploadUrlResponse = $client->get(
            "https://cloud-api.yandex.net/v1/disk/resources/upload",
            [
                'headers' => $authHeader,
                'query' => [
                    'path' => $diskPath,
                    'overwrite' => 'true',
                ],
                'http_errors' => false,
            ]
        );

        $uploadUrlData = json_decode($uploadUrlResponse->getBody(), true);
        if (empty($uploadUrlData['href'])) {
            return ['error' => 'Failed to get upload URL: ' . $uploadUrlResponse->getBody()];
        }

        // Step 3: Upload file to the provided URL
        echo "   Uploading file...\n";
        $fileHandle = fopen($filePath, 'r');
        $uploadResponse = $client->put($uploadUrlData['href'], [
            'body' => $fileHandle,
            'http_errors' => false,
        ]);
        if (is_resource($fileHandle)) {
            fclose($fileHandle);
        }

        $uploadCode = $uploadResponse->getStatusCode();
        if ($uploadCode >= 400) {
            return ['error' => "Upload failed (HTTP {$uploadCode}): "
                . $uploadResponse->getBody()];
        }

        echo "   Upload successful!\n";

        // Step 4: Publish the file (make it public)
        echo "   Generating public link...\n";
        $publishResponse = $client->put(
            "https://cloud-api.yandex.net/v1/disk/resources/publish",
            [
                'headers' => $authHeader,
                'query' => ['path' => $diskPath],
                'http_errors' => false,
            ]
        );

        $publishCode = $publishResponse->getStatusCode();
        $publishData = json_decode($publishResponse->getBody(), true);

        if ($publishCode >= 300) {
            echo "   Publish failed (HTTP {$publishCode}): " . ($publishData['message'] ?? '') . "\n";
            echo "   File uploaded to: disk:{$diskPath}\n";
            echo "   Add cloud_api:disk.read permission to your OAuth app, then re-run.\n";
            return ['uploaded' => true, 'path' => $diskPath];
        }

        // Publish returns a Link with href pointing to resource metadata
        // Follow it to get public_url
        $metaUrl = $publishData['href'] ?? null;
        if ($metaUrl) {
            $metaResponse = $client->get($metaUrl, [
                'headers' => $authHeader,
                'http_errors' => false,
            ]);
            $metaData = json_decode($metaResponse->getBody(), true);

            if (!empty($metaData['public_url'])) {
                echo "   Public link: {$metaData['public_url']}\n";
                return ['shareLink' => $metaData['public_url']];
            }
        }

        // Fallback: query resource directly
        $metaResponse = $client->get(
            "https://cloud-api.yandex.net/v1/disk/resources",
            [
                'headers' => $authHeader,
                'query' => ['path' => $diskPath],
                'http_errors' => false,
            ]
        );
        $metaData = json_decode($metaResponse->getBody(), true);

        if (!empty($metaData['public_url'])) {
            echo "   Public link: {$metaData['public_url']}\n";
            return ['shareLink' => $metaData['public_url']];
        }

        echo "   File uploaded but could not get public link.\n";
        echo "   Add cloud_api:disk.read to your OAuth app permissions.\n";
        return ['uploaded' => true, 'path' => $diskPath];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
};
