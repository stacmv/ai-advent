<?php

require __DIR__ . '/../../vendor/autoload.php';

use AiAdvent\LLMClient;

function loadEnv($filePath)
{
    $config = [];
    if (!file_exists($filePath)) {
        return $config;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (
            (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
        ) {
            $value = substr($value, 1, -1);
        }
        $config[$key] = $value;
    }
    return $config;
}

class SlidingWindowStrategy
{
    private $storageFile;
    private $tokensFile;
    private $messages = [];
    private $totalInputTokens = 0;
    private $totalOutputTokens = 0;
    private const WINDOW_SIZE = 10;

    public function __construct()
    {
        $this->storageFile = $this->getStorageDir() . '/day10_window.json';
        $this->tokensFile = $this->getStorageDir() . '/day10_window_tokens.json';
        $this->load();
    }

    private function getStorageDir()
    {
        $dir = __DIR__ . '/../../storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public function addMessage($role, $text)
    {
        $this->messages[] = ['role' => $role, 'text' => $text];
    }

    public function getContextMessages()
    {
        $windowMessages = array_slice($this->messages, -self::WINDOW_SIZE);
        return array_map(fn($m) => ['role' => $m['role'], 'text' => $m['text']], $windowMessages);
    }

    public function getStats()
    {
        return [
            'windowSize' => self::WINDOW_SIZE,
            'totalMessages' => count($this->messages),
            'inContext' => min(count($this->messages), self::WINDOW_SIZE)
        ];
    }

    public function updateTokens($inputTokens, $outputTokens)
    {
        $this->totalInputTokens += $inputTokens;
        $this->totalOutputTokens += $outputTokens;
    }

    public function getTotalTokens()
    {
        return $this->totalInputTokens + $this->totalOutputTokens;
    }

    public function getTurnTokens($inputTokens, $outputTokens)
    {
        return $inputTokens + $outputTokens;
    }

    public function getAllMessages()
    {
        return $this->messages;
    }

    public function save()
    {
        file_put_contents(
            $this->storageFile,
            json_encode(['messages' => $this->messages], JSON_PRETTY_PRINT)
        );
        file_put_contents(
            $this->tokensFile,
            json_encode([
                'totalInputTokens' => $this->totalInputTokens,
                'totalOutputTokens' => $this->totalOutputTokens
            ], JSON_PRETTY_PRINT)
        );
    }

    public function load()
    {
        if (file_exists($this->storageFile)) {
            $data = json_decode(file_get_contents($this->storageFile), true);
            $this->messages = $data['messages'] ?? [];
        }
        if (file_exists($this->tokensFile)) {
            $data = json_decode(file_get_contents($this->tokensFile), true);
            $this->totalInputTokens = $data['totalInputTokens'] ?? 0;
            $this->totalOutputTokens = $data['totalOutputTokens'] ?? 0;
        }
    }

    public function clear()
    {
        $this->messages = [];
        $this->totalInputTokens = 0;
        $this->totalOutputTokens = 0;
        @unlink($this->storageFile);
        @unlink($this->tokensFile);
    }
}

class StickyFactsStrategy
{
    private $storageFile;
    private $tokensFile;
    private $messages = [];
    private $facts = [];
    private $totalInputTokens = 0;
    private $totalOutputTokens = 0;
    private $factsInputTokens = 0;
    private $factsOutputTokens = 0;
    private const CONTEXT_SIZE = 6;

    public function __construct()
    {
        $this->storageFile = $this->getStorageDir() . '/day10_facts.json';
        $this->tokensFile = $this->getStorageDir() . '/day10_facts_tokens.json';
        $this->initializeFacts();
        $this->load();
    }

    private function getStorageDir()
    {
        $dir = __DIR__ . '/../../storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function initializeFacts()
    {
        $this->facts = [
            'goal' => '',
            'audience' => '',
            'budget' => '',
            'features' => '',
            'timeline' => '',
            'tech_stack' => '',
            'decisions' => ''
        ];
    }

    public function addMessage($role, $text)
    {
        $this->messages[] = ['role' => $role, 'text' => $text];
    }

    public function updateFacts(LLMClient $client)
    {
        $conversationText = '';
        foreach ($this->messages as $msg) {
            $conversationText .= ($msg['role'] === 'user' ? 'User: ' : 'Agent: ') . $msg['text'] . "\n\n";
        }

        $prompt = "Extract key project facts from this conversation as JSON with keys:\n"
            . "goal, audience, budget, features, timeline, tech_stack, decisions.\n"
            . "Use empty string for unknown fields. One sentence per value. Reply with JSON only.\n\n"
            . "Conversation:\n" . $conversationText;

        try {
            $result = $client->chatHistoryWithMetrics([
                ['role' => 'user', 'text' => $prompt]
            ], ['temperature' => 0.3, 'max_tokens' => 500]);

            $response = $result['text'];
            $jsonMatch = null;
            if (preg_match('/\{[^}]+\}/', $response, $jsonMatch)) {
                $extracted = json_decode($jsonMatch[0], true);
                if (is_array($extracted)) {
                    foreach ($this->facts as $key => &$value) {
                        if (isset($extracted[$key])) {
                            $value = $extracted[$key];
                        }
                    }
                }
            }

            $this->factsInputTokens += $result['input_tokens'] ?? 0;
            $this->factsOutputTokens += $result['output_tokens'] ?? 0;
        } catch (Exception $e) {
            // Extraction failed, keep existing facts
        }
    }

    public function getContextMessages()
    {
        $factsText = "Current project facts:\n";
        foreach ($this->facts as $key => $value) {
            $factsText .= "- " . ucfirst(str_replace('_', ' ', $key)) . ": " . ($value ?: "(not yet determined)") . "\n";
        }

        $recentMessages = array_slice($this->messages, -self::CONTEXT_SIZE);
        $contextMessages = [
            ['role' => 'system', 'text' => $factsText]
        ];

        foreach ($recentMessages as $msg) {
            $contextMessages[] = ['role' => $msg['role'], 'text' => $msg['text']];
        }

        return $contextMessages;
    }

    public function getFacts()
    {
        return $this->facts;
    }

    public function getStats()
    {
        return [
            'facts' => $this->facts,
            'factsTokens' => $this->factsInputTokens + $this->factsOutputTokens
        ];
    }

    public function updateTokens($inputTokens, $outputTokens)
    {
        $this->totalInputTokens += $inputTokens;
        $this->totalOutputTokens += $outputTokens;
    }

    public function getTotalTokens()
    {
        return $this->totalInputTokens + $this->totalOutputTokens;
    }

    public function getTurnTokens($inputTokens, $outputTokens)
    {
        return $inputTokens + $outputTokens;
    }

    public function save()
    {
        file_put_contents(
            $this->storageFile,
            json_encode(['messages' => $this->messages, 'facts' => $this->facts], JSON_PRETTY_PRINT)
        );
        file_put_contents(
            $this->tokensFile,
            json_encode([
                'totalInputTokens' => $this->totalInputTokens,
                'totalOutputTokens' => $this->totalOutputTokens,
                'factsInputTokens' => $this->factsInputTokens,
                'factsOutputTokens' => $this->factsOutputTokens
            ], JSON_PRETTY_PRINT)
        );
    }

    public function load()
    {
        if (file_exists($this->storageFile)) {
            $data = json_decode(file_get_contents($this->storageFile), true);
            $this->messages = $data['messages'] ?? [];
            if (isset($data['facts'])) {
                $this->facts = array_merge($this->facts, $data['facts']);
            }
        }
        if (file_exists($this->tokensFile)) {
            $data = json_decode(file_get_contents($this->tokensFile), true);
            $this->totalInputTokens = $data['totalInputTokens'] ?? 0;
            $this->totalOutputTokens = $data['totalOutputTokens'] ?? 0;
            $this->factsInputTokens = $data['factsInputTokens'] ?? 0;
            $this->factsOutputTokens = $data['factsOutputTokens'] ?? 0;
        }
    }

    public function clear()
    {
        $this->messages = [];
        $this->initializeFacts();
        $this->totalInputTokens = 0;
        $this->totalOutputTokens = 0;
        $this->factsInputTokens = 0;
        $this->factsOutputTokens = 0;
        @unlink($this->storageFile);
        @unlink($this->tokensFile);
    }
}

class BranchingStrategy
{
    private $storageFile;
    private $tokensFile;
    private $trunk = [];
    private $checkpoint = null;
    private $branches = [];
    private $activeBranch = null;
    private $totalInputTokens = 0;
    private $totalOutputTokens = 0;

    public function __construct()
    {
        $this->storageFile = $this->getStorageDir() . '/day10_branching.json';
        $this->tokensFile = $this->getStorageDir() . '/day10_branching_tokens.json';
        $this->load();
    }

    private function getStorageDir()
    {
        $dir = __DIR__ . '/../../storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public function addMessage($role, $text)
    {
        if ($this->activeBranch === null) {
            $this->trunk[] = ['role' => $role, 'text' => $text];
        } else {
            if (!isset($this->branches[$this->activeBranch])) {
                $this->branches[$this->activeBranch] = [];
            }
            $this->branches[$this->activeBranch][] = ['role' => $role, 'text' => $text];
        }
    }

    public function createCheckpoint()
    {
        $this->checkpoint = $this->trunk;
        return true;
    }

    public function createBranch($name)
    {
        if (!isset($this->branches[$name])) {
            $this->branches[$name] = [];
            $this->activeBranch = $name;
            return true;
        }
        return false;
    }

    public function switchBranch($name)
    {
        if ($name === null) {
            $this->activeBranch = null;
            return true;
        }
        if (isset($this->branches[$name])) {
            $this->activeBranch = $name;
            return true;
        }
        return false;
    }

    public function getContextMessages()
    {
        if ($this->activeBranch === null) {
            // On trunk: return all trunk
            return array_map(fn($m) => ['role' => $m['role'], 'text' => $m['text']], $this->trunk);
        } else {
            // On branch: return checkpoint + branch messages
            $contextMessages = [];
            if ($this->checkpoint !== null) {
                foreach ($this->checkpoint as $msg) {
                    $contextMessages[] = ['role' => $msg['role'], 'text' => $msg['text']];
                }
            }
            if (isset($this->branches[$this->activeBranch])) {
                foreach ($this->branches[$this->activeBranch] as $msg) {
                    $contextMessages[] = ['role' => $msg['role'], 'text' => $msg['text']];
                }
            }
            return $contextMessages;
        }
    }

    public function getStats()
    {
        return [
            'activeBranch' => $this->activeBranch,
            'hasCheckpoint' => $this->checkpoint !== null,
            'trunkSize' => count($this->trunk),
            'branchSize' => $this->activeBranch !== null && isset($this->branches[$this->activeBranch])
                ? count($this->branches[$this->activeBranch])
                : 0,
            'branches' => array_keys($this->branches)
        ];
    }

    public function updateTokens($inputTokens, $outputTokens)
    {
        $this->totalInputTokens += $inputTokens;
        $this->totalOutputTokens += $outputTokens;
    }

    public function getTotalTokens()
    {
        return $this->totalInputTokens + $this->totalOutputTokens;
    }

    public function getTurnTokens($inputTokens, $outputTokens)
    {
        return $inputTokens + $outputTokens;
    }

    public function save()
    {
        file_put_contents(
            $this->storageFile,
            json_encode([
                'trunk' => $this->trunk,
                'checkpoint' => $this->checkpoint,
                'branches' => $this->branches,
                'active_branch' => $this->activeBranch
            ], JSON_PRETTY_PRINT)
        );
        file_put_contents(
            $this->tokensFile,
            json_encode([
                'totalInputTokens' => $this->totalInputTokens,
                'totalOutputTokens' => $this->totalOutputTokens
            ], JSON_PRETTY_PRINT)
        );
    }

    public function load()
    {
        if (file_exists($this->storageFile)) {
            $data = json_decode(file_get_contents($this->storageFile), true);
            $this->trunk = $data['trunk'] ?? [];
            $this->checkpoint = $data['checkpoint'] ?? null;
            $this->branches = $data['branches'] ?? [];
            $this->activeBranch = $data['active_branch'] ?? null;
        }
        if (file_exists($this->tokensFile)) {
            $data = json_decode(file_get_contents($this->tokensFile), true);
            $this->totalInputTokens = $data['totalInputTokens'] ?? 0;
            $this->totalOutputTokens = $data['totalOutputTokens'] ?? 0;
        }
    }

    public function clear()
    {
        $this->trunk = [];
        $this->checkpoint = null;
        $this->branches = [];
        $this->activeBranch = null;
        $this->totalInputTokens = 0;
        $this->totalOutputTokens = 0;
        @unlink($this->storageFile);
        @unlink($this->tokensFile);
    }
}

// Global state
$env = loadEnv(__DIR__ . '/../../.env');
$currentStrategy = 'window';
$configFile = __DIR__ . '/../../storage/day10_config.json';

function loadConfig()
{
    global $configFile;
    if (file_exists($configFile)) {
        $data = json_decode(file_get_contents($configFile), true);
        return $data['strategy'] ?? 'window';
    }
    return 'window';
}

function saveConfig($strategy)
{
    global $configFile;
    $dir = dirname($configFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($configFile, json_encode(['strategy' => $strategy]));
}

function getStrategy($name)
{
    switch ($name) {
        case 'facts':
            return new StickyFactsStrategy();
        case 'branching':
            return new BranchingStrategy();
        default:
            return new SlidingWindowStrategy();
    }
}

// Route API requests
$action = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === '/api/demo/cases') {
        header('Content-Type: application/json; charset=utf-8');
        handleDemoCases();
        exit;
    } elseif ($action === '/api/strategy') {
        header('Content-Type: application/json; charset=utf-8');
        handleStrategyGet();
        exit;
    } elseif ($action === '/api/facts') {
        header('Content-Type: application/json; charset=utf-8');
        handleFactsGet();
        exit;
    } elseif ($action === '/api/branches') {
        header('Content-Type: application/json; charset=utf-8');
        handleBranchesGet();
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if ($action === '/api/strategy') {
        handleStrategySet();
    } elseif ($action === '/api/chat') {
        handleChat();
    } elseif ($action === '/api/chat/clear') {
        handleChatClear();
    } elseif ($action === '/api/branch/checkpoint') {
        handleBranchCheckpoint();
    } elseif ($action === '/api/branch/create') {
        handleBranchCreate();
    } elseif ($action === '/api/branch/switch') {
        handleBranchSwitch();
    } elseif ($action === '/api/record/start') {
        handleRecordStart();
    } elseif ($action === '/api/record/stop') {
        handleRecordStop();
    } elseif ($action === '/api/upload') {
        handleUpload();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
    exit;
}

function handleStrategyGet()
{
    $strategy = loadConfig();
    echo json_encode(['strategy' => $strategy]);
}

function handleStrategySet()
{
    $body = json_decode(file_get_contents('php://input'), true);
    $strategy = $body['strategy'] ?? 'window';

    if (!in_array($strategy, ['window', 'facts', 'branching'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid strategy']);
        return;
    }

    saveConfig($strategy);
    echo json_encode(['strategy' => $strategy]);
}

function handleChat()
{
    global $env;

    if (empty($env['YANDEX_API_KEY']) || empty($env['YANDEX_FOLDER_ID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing YANDEX_API_KEY or YANDEX_FOLDER_ID']);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $message = trim($body['message'] ?? '');

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Empty message']);
        return;
    }

    try {
        $strategy = loadConfig();
        $strategyObj = getStrategy($strategy);
        $client = new LLMClient('yandexgpt', $env['YANDEX_API_KEY'], $env['YANDEX_FOLDER_ID']);

        // Add user message
        $strategyObj->addMessage('user', $message);

        // Get context and chat
        $contextMessages = $strategyObj->getContextMessages();
        $result = $client->chatHistoryWithMetrics($contextMessages, ['temperature' => 0.7, 'max_tokens' => 1000]);

        $response = $result['text'];
        $inputTokens = $result['input_tokens'] ?? 0;
        $outputTokens = $result['output_tokens'] ?? 0;

        // Add agent response
        $strategyObj->addMessage('assistant', $response);

        // Update facts for sticky facts strategy
        if ($strategy === 'facts') {
            $strategyObj->updateFacts($client);
        }

        // Update tokens
        $strategyObj->updateTokens($inputTokens, $outputTokens);
        $strategyObj->save();

        // Build strategy info
        $strategyInfo = match ($strategy) {
            'window' => $strategyObj->getStats(),
            'facts' => $strategyObj->getStats(),
            'branching' => $strategyObj->getStats(),
            default => []
        };

        echo json_encode([
            'response' => $response,
            'tokens' => [
                'turn_tot' => $strategyObj->getTurnTokens($inputTokens, $outputTokens),
                'total_tot' => $strategyObj->getTotalTokens(),
            ],
            'messageCount' => count($strategyObj->getContextMessages()),
            'strategy' => $strategy,
            'strategyInfo' => $strategyInfo
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleChatClear()
{
    $strategy = loadConfig();
    $strategyObj = getStrategy($strategy);
    $strategyObj->clear();
    echo json_encode(['status' => 'History cleared']);
}

function handleFactsGet()
{
    $strategy = loadConfig();
    if ($strategy !== 'facts') {
        http_response_code(400);
        echo json_encode(['error' => 'Facts strategy not active']);
        return;
    }

    $strategyObj = getStrategy('facts');
    echo json_encode(['facts' => $strategyObj->getFacts()]);
}

function handleBranchesGet()
{
    $strategy = loadConfig();
    if ($strategy !== 'branching') {
        http_response_code(400);
        echo json_encode(['error' => 'Branching strategy not active']);
        return;
    }

    $strategyObj = getStrategy('branching');
    $stats = $strategyObj->getStats();
    echo json_encode([
        'activeBranch' => $stats['activeBranch'],
        'branches' => $stats['branches'],
        'hasCheckpoint' => $stats['hasCheckpoint'],
        'trunkSize' => $stats['trunkSize'],
        'branchSize' => $stats['branchSize']
    ]);
}

function handleBranchCheckpoint()
{
    $strategy = loadConfig();
    if ($strategy !== 'branching') {
        http_response_code(400);
        echo json_encode(['error' => 'Branching strategy not active']);
        return;
    }

    $strategyObj = getStrategy('branching');
    $strategyObj->createCheckpoint();
    $strategyObj->save();

    echo json_encode(['status' => 'Checkpoint created']);
}

function handleBranchCreate()
{
    $strategy = loadConfig();
    if ($strategy !== 'branching') {
        http_response_code(400);
        echo json_encode(['error' => 'Branching strategy not active']);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $name = $body['name'] ?? '';

    if (!$name || !in_array($name, ['A', 'B'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid branch name']);
        return;
    }

    $strategyObj = getStrategy('branching');
    $success = $strategyObj->createBranch($name);
    $strategyObj->save();

    if ($success) {
        echo json_encode(['status' => 'Branch created', 'name' => $name]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Branch already exists']);
    }
}

function handleBranchSwitch()
{
    $strategy = loadConfig();
    if ($strategy !== 'branching') {
        http_response_code(400);
        echo json_encode(['error' => 'Branching strategy not active']);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $branch = $body['branch'] ?? null;

    $strategyObj = getStrategy('branching');
    $success = $strategyObj->switchBranch($branch);
    $strategyObj->save();

    if ($success) {
        $branchName = $branch === null ? 'trunk' : $branch;
        echo json_encode(['status' => 'Switched to ' . $branchName]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Branch not found']);
    }
}

function handleDemoCases()
{
    require __DIR__ . '/demo_cases.php';
    echo json_encode(['cases' => $demoCases]);
}

function handleRecordStart()
{
    $timestamp = date('Y-m-d_His');
    $recordDir = realpath(__DIR__ . '/../../recordings') ?: __DIR__ . '/../../recordings';
    if (!is_dir($recordDir)) {
        @mkdir($recordDir, 0755, true);
        $recordDir = realpath($recordDir);
    }

    $recordingFile = $recordDir . DIRECTORY_SEPARATOR . 'day10_' . $timestamp . '.mp4';
    $wrapperScript = realpath(__DIR__ . '/../../tools/ffmpeg_wrapper.php');

    @unlink($recordDir . '/.ffmpeg_stop');
    @unlink($recordDir . '/.ffmpeg_ready');

    $cmd = 'php ' . escapeshellarg($wrapperScript)
        . ' ' . escapeshellarg($recordingFile)
        . ' ' . escapeshellarg($recordDir);

    pclose(popen('start /B ' . $cmd, 'r'));

    $waited = 0;
    while (!file_exists($recordDir . '/.ffmpeg_ready') && $waited < 5) {
        usleep(200000);
        $waited += 0.2;
    }

    echo json_encode(['status' => 'Recording started', 'file' => $recordingFile]);
}

function handleRecordStop()
{
    $recordDir = realpath(__DIR__ . '/../../recordings') ?: __DIR__ . '/../../recordings';

    touch($recordDir . '/.ffmpeg_stop');

    $waited = 0;
    while (file_exists($recordDir . '/.ffmpeg_ready') && $waited < 12) {
        usleep(500000);
        $waited += 0.5;
    }

    echo json_encode(['status' => 'Recording stopped']);
}

function handleUpload()
{
    try {
        $result = shell_exec('php ' . escapeshellarg(__DIR__ . '/../../tools/upload_latest.php') . ' 10 2>&1');
        echo json_encode(['status' => 'Upload completed', 'output' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Serve HTML
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day 10: Context Management Strategies — Web UI</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        header {
            background: #252526;
            border-bottom: 1px solid #3c3c3c;
            padding: 10px 20px;
            font-size: 13px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .header-title { color: #9cdcfe; }
        .header-title span { color: #569cd6; }
        .header-buttons { display: flex; gap: 8px; }
        .strategy-buttons {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #3c3c3c;
        }
        button {
            background: #0e639c;
            color: #fff;
            border: none;
            padding: 6px 12px;
            font-family: inherit;
            font-size: 12px;
            border-radius: 3px;
            cursor: pointer;
            white-space: nowrap;
        }
        button:hover { background: #1177bb; }
        button:disabled { background: #3c3c3c; color: #6a6a6a; cursor: not-allowed; }
        button.active { background: #569cd6; color: #fff; }
        #record-stop-btn:not(:disabled) { background: #d32f2f; }
        #record-stop-btn:not(:disabled):hover { background: #f44747; }
        .strategy-panel {
            background: #2d2d30;
            border-left: 3px solid #569cd6;
            padding: 10px 20px;
            margin-top: 10px;
            font-size: 12px;
            display: none;
        }
        .strategy-panel.active { display: block; }
        .facts-table {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 10px;
            margin-top: 8px;
        }
        .fact-label { color: #ce9178; font-weight: bold; }
        .fact-value { color: #b4cea8; }
        .branch-buttons { display: flex; gap: 6px; margin-top: 8px; }
        .branch-buttons button { padding: 4px 8px; font-size: 11px; }
        #log {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        .msg { margin-bottom: 12px; line-height: 1.5; }
        .msg .label { font-weight: bold; margin-right: 4px; }
        .msg.user .label { color: #4ec9b0; }
        .msg.agent .label { color: #ce9178; }
        .msg.demo .label { color: #dcdcaa; }
        .msg.error .label { color: #f44747; }
        .msg.info .label { color: #569cd6; }
        .msg .text { white-space: pre-wrap; word-break: break-word; }
        .msg.thinking .text { color: #6a9955; font-style: italic; }
        .msg-meta { font-size: 11px; color: #6a6a6a; margin-top: 2px; }
        #form {
            display: flex;
            border-top: 1px solid #3c3c3c;
            background: #252526;
            padding: 12px 20px;
            gap: 10px;
        }
        #input {
            flex: 1;
            background: #1e1e1e;
            border: 1px solid #3c3c3c;
            color: #d4d4d4;
            font-family: inherit;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 3px;
            outline: none;
        }
        #input:focus { border-color: #569cd6; }
        #send {
            background: #0e639c;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-family: inherit;
            font-size: 14px;
            border-radius: 3px;
            cursor: pointer;
        }
        #send:hover { background: #1177bb; }
        #send:disabled { background: #3c3c3c; color: #6a6a6a; cursor: not-allowed; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="header-title">
                === Day 10: <span>Context Management Strategies</span> ===
            </div>
            <div class="header-buttons">
                <button id="demo-btn" title="Run all demo cases">Demo</button>
                <button id="clear-history-btn" title="Clear server-side conversation history">Clear History</button>
                <button id="record-start-btn" title="Start screen recording">Record</button>
                <button id="record-stop-btn" title="Stop screen recording" disabled>Stop</button>
                <button id="upload-btn" title="Upload latest video">Upload</button>
                <button id="clear-btn" title="Clear chat log">Clear Log</button>
            </div>
        </div>
        <div class="strategy-buttons">
            <button id="strategy-window-btn" class="active" title="Sliding Window strategy">Window (10)</button>
            <button id="strategy-facts-btn" title="Sticky Facts strategy">Facts</button>
            <button id="strategy-branching-btn" title="Branching strategy">Branching</button>
        </div>
        <div id="strategy-panel" class="strategy-panel active">
            <!-- Populated by JavaScript -->
        </div>
    </header>
    <div id="log"></div>
    <form id="form" onsubmit="return false;">
        <input id="input" type="text" placeholder="Type your message…" autofocus autocomplete="off">
        <button id="send" type="submit">Send</button>
    </form>

    <script>
        const log = document.getElementById('log');
        const input = document.getElementById('input');
        const send = document.getElementById('send');
        const demoBtn = document.getElementById('demo-btn');
        const clearHistoryBtn = document.getElementById('clear-history-btn');
        const recordStartBtn = document.getElementById('record-start-btn');
        const recordStopBtn = document.getElementById('record-stop-btn');
        const uploadBtn = document.getElementById('upload-btn');
        const clearBtn = document.getElementById('clear-btn');
        const strategyPanel = document.getElementById('strategy-panel');

        let currentStrategy = 'window';

        function addMsg(role, text, meta) {
            const div = document.createElement('div');
            div.className = 'msg ' + role;
            const labels = { user: 'You:', agent: 'Agent:', error: 'Error:', thinking: '…', demo: 'Demo:', info: 'Info:' };
            const label = labels[role] || role + ':';
            div.innerHTML = '<span class="label">' + label + '</span>'
                + '<span class="text">' + escapeHtml(text) + '</span>';
            if (meta) {
                const m = document.createElement('div');
                m.className = 'msg-meta';
                m.textContent = meta;
                div.appendChild(m);
            }
            log.appendChild(div);
            log.scrollTop = log.scrollHeight;
            return div;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function updateStrategyPanel() {
            try {
                const res = await fetch('/api/strategy');
                const data = await res.json();
                currentStrategy = data.strategy;

                const panel = document.getElementById('strategy-panel');
                panel.innerHTML = '';

                if (currentStrategy === 'window') {
                    panel.innerHTML = '<div>Sliding Window: last 10 messages of all stored conversations</div>';
                } else if (currentStrategy === 'facts') {
                    const factsRes = await fetch('/api/facts').catch(() => null);
                    if (factsRes && factsRes.ok) {
                        const factsData = await factsRes.json();
                        const facts = factsData.facts || {};
                        let html = '<div style="margin-bottom: 8px;">Sticky Facts:</div><div class="facts-table">';
                        for (const [key, value] of Object.entries(facts)) {
                            html += '<div class="fact-label">' + escapeHtml(key.replace(/_/g, ' ')) + ':</div>';
                            html += '<div class="fact-value">' + escapeHtml(value || '(unknown)') + '</div>';
                        }
                        html += '</div>';
                        panel.innerHTML = html;
                    }
                } else if (currentStrategy === 'branching') {
                    const branchRes = await fetch('/api/branches').catch(() => null);
                    if (branchRes && branchRes.ok) {
                        const branchData = await branchRes.json();
                        let html = '<div style="margin-bottom: 8px;">Branching Strategy:</div>';
                        html += '<div>Active: <strong>' + (branchData.activeBranch || 'Trunk') + '</strong></div>';
                        html += '<div>Trunk: ' + branchData.trunkSize + ' messages | ';
                        if (branchData.branchSize > 0) {
                            html += branchData.activeBranch + ': ' + branchData.branchSize + ' messages | ';
                        }
                        html += 'Checkpoint: ' + (branchData.hasCheckpoint ? 'Yes' : 'No') + '</div>';
                        html += '<div class="branch-buttons">';
                        html += '<button onclick="branchAction(\'checkpoint\')">Checkpoint</button>';
                        html += '<button onclick="branchAction(\'createA\')">Create Branch A</button>';
                        html += '<button onclick="branchAction(\'createB\')">Create Branch B</button>';
                        html += '<button onclick="branchAction(\'switchTrunk\')">→ Trunk</button>';
                        if (branchData.branches.includes('A')) {
                            html += '<button onclick="branchAction(\'switchA\')">→ A</button>';
                        }
                        if (branchData.branches.includes('B')) {
                            html += '<button onclick="branchAction(\'switchB\')">→ B</button>';
                        }
                        html += '</div>';
                        panel.innerHTML = html;
                    }
                }
            } catch (e) {
                // Ignore errors during panel update
            }
        }

        async function switchStrategy(strategy) {
            try {
                await fetch('/api/strategy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ strategy: strategy })
                });

                currentStrategy = strategy;
                document.querySelectorAll('.strategy-buttons button').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.getElementById('strategy-' + strategy + '-btn').classList.add('active');

                await updateStrategyPanel();
            } catch (e) {
                addMsg('error', e.message);
            }
        }

        async function branchAction(action) {
            try {
                if (action === 'checkpoint') {
                    await fetch('/api/branch/checkpoint', { method: 'POST' });
                    addMsg('info', 'Checkpoint created');
                } else if (action === 'createA') {
                    await fetch('/api/branch/create', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: 'A' })
                    });
                    addMsg('info', 'Branch A created');
                } else if (action === 'createB') {
                    await fetch('/api/branch/create', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: 'B' })
                    });
                    addMsg('info', 'Branch B created');
                } else if (action === 'switchTrunk') {
                    await fetch('/api/branch/switch', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ branch: null })
                    });
                    addMsg('info', 'Switched to trunk');
                } else if (action === 'switchA') {
                    await fetch('/api/branch/switch', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ branch: 'A' })
                    });
                    addMsg('info', 'Switched to branch A');
                } else if (action === 'switchB') {
                    await fetch('/api/branch/switch', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ branch: 'B' })
                    });
                    addMsg('info', 'Switched to branch B');
                }
                await updateStrategyPanel();
            } catch (e) {
                addMsg('error', e.message);
            }
        }

        async function sendMessage(messageText) {
            const text = messageText !== undefined ? messageText : input.value.trim();
            if (!text) return;
            if (messageText === undefined) {
                input.value = '';
                send.disabled = true;
            }

            addMsg('user', text);
            const thinking = addMsg('thinking', 'waiting for response…');

            try {
                const res = await fetch('/api/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: text })
                });
                const data = await res.json();
                thinking.remove();
                if (data.error) {
                    addMsg('error', data.error);
                } else {
                    const meta = 'Tokens this turn: ' + data.tokens.turn_tot
                        + '  |  Cumulative: ' + data.tokens.total_tot;
                    addMsg('agent', data.response, meta);
                    await updateStrategyPanel();
                }
            } catch (e) {
                thinking.remove();
                addMsg('error', e.message);
            }

            if (messageText === undefined) {
                send.disabled = false;
                input.focus();
            }
        }

        async function clearHistory() {
            await fetch('/api/chat/clear', { method: 'POST' });
            addMsg('info', 'History cleared');
            await updateStrategyPanel();
        }

        async function runDemo() {
            demoBtn.disabled = true;
            input.disabled = true;
            send.disabled = true;

            addMsg('demo', 'Loading demo cases…');

            try {
                const res = await fetch('/api/demo/cases');
                const data = await res.json();

                if (data.error) {
                    addMsg('error', data.error);
                    return;
                }

                let demoStopped = false;

                for (let i = 0; i < data.cases.length && !demoStopped; i++) {
                    const c = data.cases[i];
                    addMsg('demo', '=== Case ' + (i + 1) + ': ' + c.name + ' [' + c.strategy + '] ===');

                    await switchStrategy(c.strategy);
                    await fetch('/api/chat/clear', { method: 'POST' });

                    for (const step of c.steps) {
                        if (demoStopped) break;

                        try {
                            if (step.type === 'checkpoint') {
                                await fetch('/api/branch/checkpoint', { method: 'POST' });
                                addMsg('info', 'Checkpoint created');
                            } else if (step.type === 'create_branch') {
                                await fetch('/api/branch/create', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ name: step.name })
                                });
                                addMsg('info', 'Branch ' + step.name + ' created');
                            } else if (step.type === 'switch_branch') {
                                await fetch('/api/branch/switch', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ branch: step.branch })
                                });
                                addMsg('info', 'Switched to ' + (step.branch || 'trunk'));
                            } else {
                                input.value = step;
                                const msgRes = await fetch('/api/chat', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ message: step })
                                });
                                const msgData = await msgRes.json();

                                if (msgData.error) {
                                    addMsg('error', 'API Error: ' + msgData.error);
                                    addMsg('demo', '⚠ Demo stopped due to API error. Check your API credentials and folder ID.');
                                    demoStopped = true;
                                    break;
                                }

                                if (msgData.response && msgData.response.includes('Error:')) {
                                    addMsg('error', 'LLM Error: ' + msgData.response.substring(0, 150));
                                    addMsg('demo', '⚠ Demo stopped due to LLM error.');
                                    demoStopped = true;
                                    break;
                                }

                                const meta = 'Tokens this turn: ' + msgData.tokens.turn_tot
                                    + '  |  Cumulative: ' + msgData.tokens.total_tot;
                                addMsg('agent', msgData.response, meta);
                                await updateStrategyPanel();
                                await new Promise(resolve => setTimeout(resolve, 1200));
                            }
                        } catch (stepError) {
                            addMsg('error', 'Step error: ' + stepError.message);
                            addMsg('demo', '⚠ Demo stopped due to error.');
                            demoStopped = true;
                            break;
                        }
                    }

                    if (!demoStopped) {
                        await new Promise(resolve => setTimeout(resolve, 2000));
                    }
                }

                if (!demoStopped) {
                    addMsg('demo', 'Demo completed successfully');
                }
            } catch (e) {
                addMsg('error', 'Fatal error: ' + e.message);
            }

            demoBtn.disabled = false;
            input.disabled = false;
            send.disabled = false;
            input.value = '';
            input.focus();
        }

        async function startRecording() {
            recordStartBtn.disabled = true;
            addMsg('demo', 'Starting ffmpeg (1200x700 from top-left)…');
            try {
                const res = await fetch('/api/record/start', { method: 'POST' });
                const data = await res.json();
                if (data.error) {
                    addMsg('error', data.error);
                    recordStartBtn.disabled = false;
                } else {
                    recordStopBtn.disabled = false;
                    addMsg('demo', 'Recording started – click Demo or type to interact');
                }
            } catch (e) {
                addMsg('error', e.message);
                recordStartBtn.disabled = false;
            }
        }

        async function stopRecording() {
            recordStopBtn.disabled = true;
            recordStartBtn.disabled = true;
            addMsg('demo', 'Stopping recording…');
            try {
                await fetch('/api/record/stop', { method: 'POST' });
                recordStartBtn.disabled = false;
                recordStopBtn.disabled = true;
                addMsg('demo', 'Recording stopped');
            } catch (e) {
                addMsg('error', e.message);
                recordStartBtn.disabled = false;
                recordStopBtn.disabled = false;
            }
        }

        async function upload() {
            uploadBtn.disabled = true;
            addMsg('demo', 'Uploading latest video…');
            try {
                const res = await fetch('/api/upload', { method: 'POST' });
                const data = await res.json();
                uploadBtn.disabled = false;
                if (data.error) {
                    addMsg('error', data.error);
                } else {
                    addMsg('demo', 'Upload completed: ' + data.output);
                }
            } catch (e) {
                addMsg('error', e.message);
                uploadBtn.disabled = false;
            }
        }

        send.addEventListener('click', () => sendMessage());
        input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) sendMessage(); });
        demoBtn.addEventListener('click', runDemo);
        clearHistoryBtn.addEventListener('click', clearHistory);
        recordStartBtn.addEventListener('click', startRecording);
        recordStopBtn.addEventListener('click', stopRecording);
        uploadBtn.addEventListener('click', upload);
        clearBtn.addEventListener('click', () => { log.innerHTML = ''; input.focus(); });

        document.getElementById('strategy-window-btn').addEventListener('click', () => switchStrategy('window'));
        document.getElementById('strategy-facts-btn').addEventListener('click', () => switchStrategy('facts'));
        document.getElementById('strategy-branching-btn').addEventListener('click', () => switchStrategy('branching'));

        window.branchAction = branchAction;

        // Initialize
        updateStrategyPanel();
    </script>
</body>
</html>
