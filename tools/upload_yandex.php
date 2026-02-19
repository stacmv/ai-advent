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

    // SSL certificate path for Windows
    $certPath = __DIR__ . '/../cacert.pem';
    $clientOptions = file_exists($certPath) ? ['verify' => $certPath] : [];

    echo "   Uploading to Yandex.Disk: {$fileName} ({$fileSize} bytes)\n";

    try {
        $client = new Client($clientOptions);

        // Step 1: Create directory if not exists
        echo "   Creating directory...\n";
        $client->request('MKCOL', "https://webdav.yandex.ru{$uploadDir}", [
            'headers' => [
                'Authorization' => 'OAuth ' . $token,
            ],
            'http_errors' => false,
        ]);

        // Step 2: Upload file via WebDAV
        echo "   Uploading file...\n";
        $fileHandle = fopen($filePath, 'r');
        $response = $client->put("https://webdav.yandex.ru{$uploadDir}/{$fileName}", [
            'headers' => [
                'Authorization' => 'OAuth ' . $token,
            ],
            'body' => $fileHandle,
        ]);
        if (is_resource($fileHandle)) {
            fclose($fileHandle);
        }

        if ($response->getStatusCode() >= 400) {
            return ['error' => 'Upload failed: HTTP ' . $response->getStatusCode()];
        }

        echo "   Upload successful!\n";

        // Step 3: Publish the file (make it public)
        echo "   Generating public link...\n";
        $resourcePath = "{$uploadDir}/{$fileName}";

        $client->put(
            "https://cloud-api.yandex.net/v1/disk/resources/publish",
            [
                'headers' => [
                    'Authorization' => 'OAuth ' . $token,
                ],
                'query' => [
                    'path' => $resourcePath,
                ],
                'http_errors' => false,
            ]
        );

        // Step 4: Get resource metadata to retrieve public_url
        $metaResponse = $client->get(
            "https://cloud-api.yandex.net/v1/disk/resources",
            [
                'headers' => [
                    'Authorization' => 'OAuth ' . $token,
                ],
                'query' => [
                    'path' => $resourcePath,
                ],
                'http_errors' => false,
            ]
        );

        $metaData = json_decode($metaResponse->getBody(), true);

        if (!empty($metaData['public_url'])) {
            $publicUrl = $metaData['public_url'];
            echo "   Public link: {$publicUrl}\n";
            return ['shareLink' => $publicUrl];
        }

        echo "   File uploaded to: {$resourcePath} (could not get public link)\n";
        return ['uploaded' => true, 'path' => $resourcePath];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
};
