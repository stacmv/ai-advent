<?php

namespace AiAdvent;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class LLMClient
{
    private string $provider;
    private string $apiKey;
    private ?string $folderId;
    private Client $httpClient;

    public function __construct(string $provider, string $apiKey, ?string $folderId = null)
    {
        $this->provider = $provider;
        $this->apiKey = $apiKey;
        $this->folderId = $folderId;

        // Configure Guzzle with CA certificate for SSL verification
        $clientConfig = [];
        $certPath = __DIR__ . '/../cacert.pem';
        if (file_exists($certPath)) {
            $clientConfig['verify'] = $certPath;
        }

        $this->httpClient = new Client($clientConfig);
    }

    /**
     * Send a chat message to the LLM
     */
    public function chat(string $prompt, array $options = []): string
    {
        try {
            return match ($this->provider) {
                'claude' => $this->callClaude($prompt, $options),
                'deepseek' => $this->callDeepseek($prompt, $options),
                'yandexgpt' => $this->callYandexGPT($prompt, $options),
                default => throw new \InvalidArgumentException("Unknown provider: {$this->provider}")
            };
        } catch (GuzzleException $e) {
            return "Error: " . $e->getMessage();
        }
    }

    private function callClaude(string $prompt, array $options): string
    {
        $systemPrompt = $options['system'] ?? '';
        $temperature = $options['temperature'] ?? 1.0;
        $maxTokens = $options['max_tokens'] ?? 1024;

        $body = [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];

        if ($systemPrompt) {
            $body['system'] = $systemPrompt;
        }

        $response = $this->httpClient->post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ],
            'json' => $body
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['content'][0]['text'] ?? 'No response';
    }

    private function callDeepseek(string $prompt, array $options): string
    {
        $systemPrompt = $options['system'] ?? '';
        $temperature = $options['temperature'] ?? 1.0;
        $maxTokens = $options['max_tokens'] ?? 1024;

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = [
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];

        if (isset($options['stop'])) {
            $body['stop'] = $options['stop'];
        }

        $response = $this->httpClient->post('https://api.deepseek.com/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'content-type' => 'application/json'
            ],
            'json' => $body
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? 'No response';
    }

    private function callYandexGPT(string $prompt, array $options): string
    {
        $temperature = $options['temperature'] ?? 1.0;
        $maxTokens = $options['max_tokens'] ?? 1024;

        // Debug: Check if folder ID is set
        if (empty($this->folderId)) {
            return "Error: YANDEX_FOLDER_ID is not set";
        }

        // Yandex model URI format: gpt://folder_id/model_name/version
        $body = [
            'modelUri' => "gpt://{$this->folderId}/yandexgpt-lite/latest",
            'completionOptions' => [
                'stream' => false,
                'temperature' => $temperature,
                'maxTokens' => $maxTokens
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'text' => $prompt
                ]
            ]
        ];

        $response = $this->httpClient->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
            'headers' => [
                'Authorization' => 'Api-Key ' . $this->apiKey,
                'content-type' => 'application/json'
            ],
            'json' => $body
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['result']['alternatives'][0]['message']['text'] ?? 'No response';
    }
}
