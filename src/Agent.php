<?php

namespace AiAdvent;

class Agent
{
    private LLMClient $client;
    private array $options;

    public function __construct(LLMClient $client, array $options = [])
    {
        $this->client = $client;
        $this->options = $options;
    }

    /**
     * Run a single-turn agent interaction
     */
    public function run(string $userMessage): string
    {
        $response = $this->client->chatWithMetrics($userMessage, $this->options);
        return $response['text'];
    }
}
