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
    private int $maxRecentMessages = 6;
    private ?string $summary = null;

    public function __construct(LLMClient $client, string $historyFile, array $options = [])
    {
        $this->client = $client;
        $this->historyFile = $historyFile;
        $this->options = $options;
        $this->loadHistory();
    }

    /**
     * Run an agent interaction with conversation history, with token tracking and compression
     * Returns array with 'text' and token metrics
     */
    public function run(string $userMessage)
    {
        // Add user message to history
        $this->messages[] = ['role' => 'user', 'text' => $userMessage];

        // Check if compression is needed
        $wasCompressed = false;
        if ($this->shouldCompress()) {
            $this->compress();
            $wasCompressed = true;
        }

        // Build context messages (includes summary if available)
        $contextMessages = $this->buildContextMessages();

        // Get response from LLM with conversation context
        $response = $this->client->chatHistoryWithMetrics($contextMessages, $this->options);

        // Add assistant response to stored messages
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
            'was_compressed' => $wasCompressed,
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
     * Check if compression is enabled
     */
    public function hasCompressionEnabled(): bool
    {
        return $this->maxRecentMessages < 999;
    }

    /**
     * Get the summary (if any)
     */
    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /**
     * Check if compression should be triggered
     */
    private function shouldCompress(): bool
    {
        return count($this->messages) > ($this->maxRecentMessages * 2);
    }

    /**
     * Compress earlier messages into a summary
     */
    private function compress(): void
    {
        // Number of messages to keep (3 user+assistant pairs = 6 messages)
        $keepCount = $this->maxRecentMessages;
        $compressCount = count($this->messages) - $keepCount;

        if ($compressCount <= 0) {
            return;
        }

        // Get messages to compress
        $toCompress = array_slice($this->messages, 0, $compressCount);

        // Create summarization prompt
        $conversationText = '';
        foreach ($toCompress as $msg) {
            $conversationText .= "[{$msg['role']}] {$msg['text']}\n";
        }

        $summarizePrompt = "Please provide a brief summary of the following conversation:\n\n" . $conversationText
            . "\n\nProvide a concise summary (2-3 sentences) capturing the key points discussed.";

        // Get summary from LLM
        $summaryResponse = $this->client->chatWithMetrics($summarizePrompt, $this->options);
        $this->summary = $summaryResponse['text'];

        // Keep only recent messages
        $this->messages = array_slice($this->messages, $compressCount);
    }

    /**
     * Build context messages including summary
     */
    private function buildContextMessages(): array
    {
        $context = [];

        // Add summary as system message if available
        if ($this->summary !== null) {
            $context[] = [
                'role' => 'system',
                'text' => "Previous conversation summary: {$this->summary}"
            ];
        }

        // Add recent messages
        $context = array_merge($context, $this->messages);

        return $context;
    }

    /**
     * Load history from file
     */
    private function loadHistory(): void
    {
        if (!file_exists($this->historyFile)) {
            $this->messages = [];
            $this->summary = null;
            return;
        }

        $content = file_get_contents($this->historyFile);
        if ($content === false) {
            $this->messages = [];
            $this->summary = null;
            return;
        }

        $data = json_decode($content, true);
        if (is_array($data)) {
            $this->messages = $data['messages'] ?? [];
            $this->summary = $data['summary'] ?? null;
        } else {
            $this->messages = [];
            $this->summary = null;
        }
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

        $data = [
            'messages' => $this->messages,
            'summary' => $this->summary,
        ];

        file_put_contents(
            $this->historyFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
