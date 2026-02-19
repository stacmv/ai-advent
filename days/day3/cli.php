<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$caseNum = $argv[1] ?? null;

if ($caseNum === '--case=1' || $caseNum === '1') {
    require __DIR__ . '/demo_cases.php';
    $case = $demoCases[0] ?? null;
    if (!$case) {
        echo "No demo case found\n";
        exit(1);
    }
    $prompt = $case['prompt'];
} else {
    $prompt = 'A farmer needs to cross a river with a fox, chicken, and grain. The boat holds only the farmer + one item. Fox eats chicken, chicken eats grain if left alone. How does the farmer solve this?';
}

echo "=== Day 3: Reasoning Approaches ===\n";
echo "Puzzle: {$prompt}\n";
echo str_repeat("=", 80) . "\n\n";

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
if (!$apiKey) {
    echo "Error: ANTHROPIC_API_KEY not set\n";
    exit(1);
}

$client = new LLMClient('claude', $apiKey);

// Approach 1: Direct answer
echo "1. DIRECT ANSWER (no instructions)\n";
echo str_repeat("-", 80) . "\n";
$result1 = $client->chat($prompt);
echo $result1 . "\n\n";

// Approach 2: Step by step
echo "2. STEP-BY-STEP REASONING\n";
echo str_repeat("-", 80) . "\n";
$prompt2 = "{$prompt}\n\nSolve this step by step, explaining each decision.";
$result2 = $client->chat($prompt2);
echo $result2 . "\n\n";

// Approach 3: Generate prompt first, then solve
echo "3. PROMPT GENERATION FIRST\n";
echo str_repeat("-", 80) . "\n";
$metaPrompt = "Create a detailed prompt that would help someone solve this puzzle: {$prompt}\n\nGenerate the prompt, then solve the puzzle using that prompt as guidance.";
$result3 = $client->chat($metaPrompt);
echo $result3 . "\n\n";

// Approach 4: Expert group
echo "4. EXPERT GROUP DISCUSSION\n";
echo str_repeat("-", 80) . "\n";
$expertPrompt = "You are three experts discussing a puzzle. Each gives their solution:\n\n";
$expertPrompt .= "ANALYST: Analyze the constraints and logical requirements.\n";
$expertPrompt .= "ENGINEER: Propose a step-by-step solution.\n";
$expertPrompt .= "CRITIC: Review and validate the solution.\n\n";
$expertPrompt .= "Puzzle: {$prompt}\n\n";
$expertPrompt .= "Each expert should provide their viewpoint.";
$result4 = $client->chat($expertPrompt);
echo $result4 . "\n\n";

// Summary
echo str_repeat("=", 80) . "\n";
echo "COMPARISON SUMMARY\n";
echo str_repeat("=", 80) . "\n";

$approaches = [
    '1. Direct' => strlen($result1),
    '2. Step-by-Step' => strlen($result2),
    '3. Prompt Gen' => strlen($result3),
    '4. Expert Group' => strlen($result4)
];

arsort($approaches);

echo "Response lengths:\n";
foreach ($approaches as $approach => $length) {
    echo sprintf("  %s: %d chars\n", $approach, $length);
}

echo "\nMost detailed approach: " . key($approaches) . "\n";
