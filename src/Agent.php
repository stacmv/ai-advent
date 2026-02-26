<?php

namespace AiAdvent;

class Agent
{
    private LLMClient $client;
    private array $messages = [];
    private string $historyFile;
    private array $options;

    public function __construct(LLMClient $client, string $historyFile, array $options = [])
    {
        $this->client = $client;
        $this->historyFile = $historyFile;
        $this->options = $options;
        $this->loadHistory();
    }

    /**
     * Run an agent interaction with conversation history
     */
    public function run(string $userMessage): string
    {
        // Add user message to history
        $this->messages[] = ['role' => 'user', 'text' => $userMessage];

        // Get response from LLM with full conversation history
        $response = $this->client->chatHistoryWithMetrics($this->messages, $this->options);

        // Add assistant response to history
        $this->messages[] = ['role' => 'assistant', 'text' => $response['text']];

        // Save history to disk
        $this->saveHistory();

        return $response['text'];
    }

    /**
     * Clear conversation history
     */
    public function clearHistory(): void
    {
        $this->messages = [];
        @unlink($this->historyFile);
    }

    /**
     * Get the number of messages in history
     */
    public function getMessageCount(): int
    {
        return count($this->messages);
    }

    /**
     * Get all messages
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Load history from file
     */
    private function loadHistory(): void
    {
        if (!file_exists($this->historyFile)) {
            $this->messages = [];
            return;
        }

        $content = file_get_contents($this->historyFile);
        if ($content === false) {
            $this->messages = [];
            return;
        }

        $data = json_decode($content, true);
        $this->messages = is_array($data) ? $data : [];
    }

    /**
     * Save history to file
     */
    private function saveHistory(): void
    {
        $dir = dirname($this->historyFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->historyFile,
            json_encode($this->messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
