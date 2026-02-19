<?php

/**
 * Upload file to Yandex.Disk and return share link
 *
 * Returns closure that can be called with: uploadFile(filePath, fileName, token)
 */

use GuzzleHttp\Client;

return function (string $filePath, string $fileName, string $token): array {
    if (!file_exists($filePath)) {
        return ['error' => 'File not found: ' . $filePath];
    }

    $fileSize = filesize($filePath);
    $uploadDir = '/ai-advent';

    echo "   Uploading to Yandex.Disk: {$fileName} ({$fileSize} bytes)\n";

    try {
        $client = new Client();

        // Step 1: Create directory if not exists
        echo "   Creating directory...\n";
        $client->request('MKCOL', "https://webdav.yandex.ru{$uploadDir}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'http_errors' => false,  // Don't throw on 4xx/5xx
        ]);

        // Step 2: Upload file via WebDAV
        echo "   Uploading file...\n";
        $filePath = fopen($filePath, 'r');
        $response = $client->put("https://webdav.yandex.ru{$uploadDir}/{$fileName}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => $filePath,
        ]);

        if ($response->getStatusCode() >= 400) {
            return ['error' => 'Upload failed: HTTP ' . $response->getStatusCode()];
        }

        echo "   Upload successful!\n";

        // Step 3: Get public share link
        echo "   Generating public link...\n";
        $resourcePath = "{$uploadDir}/{$fileName}";

        $publishResponse = $client->put(
            "https://cloud-api.yandex.net/v1/disk/resources/publish",
            [
                'headers' => [
                    'Authorization' => 'OAuth ' . $token,
                ],
                'query' => [
                    'path' => $resourcePath,
                ],
            ]
        );

        $publishData = json_decode($publishResponse->getBody(), true);

        if (isset($publishData['public_url'])) {
            $publicUrl = $publishData['public_url'];
            echo "   Public link: {$publicUrl}\n";
            return ['shareLink' => $publicUrl];
        } else {
            // If publish fails, try to construct share link directly
            // Yandex.Disk public links follow pattern: https://disk.yandex.ru/d/SHAREID
            echo "   Could not get public link via API, file uploaded to: {$resourcePath}\n";
            return ['uploaded' => true, 'path' => $resourcePath];
        }
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
};
