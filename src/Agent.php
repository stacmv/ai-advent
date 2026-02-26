<?php

namespace AiAdvent;

class Agent
{
    private LLMClient $client;
    private array $messages = [];
    private string $historyFile;
    private array $options;
    private int $turnInputTokens = 0;
    private int $turnOutputTokens = 0;
    private int $totalInputTokens = 0;
    private int $totalOutputTokens = 0;
    private int $warnThreshold = 4000;

    public function __construct(LLMClient $client, string $historyFile, array $options = [])
    {
        $this->client = $client;
        $this->historyFile = $historyFile;
        $this->options = $options;
        $this->loadHistory();
    }

    /**
     * Run an agent interaction with conversation history, with token tracking
     * Returns array with 'text' and token metrics
     */
    public function run(string $userMessage)
    {
        // Add user message to history
        $this->messages[] = ['role' => 'user', 'text' => $userMessage];

        // Get response from LLM with full conversation history
        $response = $this->client->chatHistoryWithMetrics($this->messages, $this->options);

        // Add assistant response to history
        $this->messages[] = ['role' => 'assistant', 'text' => $response['text']];

        // Track tokens
        $this->turnInputTokens = $response['input_tokens'];
        $this->turnOutputTokens = $response['output_tokens'];
        $this->totalInputTokens += $this->turnInputTokens;
        $this->totalOutputTokens += $this->turnOutputTokens;

        // Save history to disk
        $this->saveHistory();

        // Return result as array (for token display) or string (for backward compat)
        return [
            'text' => $response['text'],
            'turn_input_tokens' => $this->turnInputTokens,
            'turn_output_tokens' => $this->turnOutputTokens,
            'turn_total_tokens' => $this->turnInputTokens + $this->turnOutputTokens,
            'total_input_tokens' => $this->totalInputTokens,
            'total_output_tokens' => $this->totalOutputTokens,
            'total_tokens' => $this->totalInputTokens + $this->totalOutputTokens,
        ];
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
     * Get token statistics
     */
    public function getStats(): array
    {
        return [
            'turn_input' => $this->turnInputTokens,
            'turn_output' => $this->turnOutputTokens,
            'total_input' => $this->totalInputTokens,
            'total_output' => $this->totalOutputTokens,
            'total' => $this->totalInputTokens + $this->totalOutputTokens,
        ];
    }

    /**
     * Check if approaching token limit
     */
    public function isApproachingLimit(): bool
    {
        return ($this->totalInputTokens + $this->totalOutputTokens) >= ($this->warnThreshold * 0.8);
    }

    /**
     * Get percentage of token limit used
     */
    public function getTokenPercentage(): int
    {
        if ($this->warnThreshold <= 0) {
            return 0;
        }
        return (int)(100 * ($this->totalInputTokens + $this->totalOutputTokens) / $this->warnThreshold);
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
