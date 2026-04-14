<?php

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../tools/record_upload_handlers.php';

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

class MemoryModel
{
    private $storageDir;
    private $shortTermFile;
    private $workingMemoryFile;
    private $longTermFile;

    public function __construct()
    {
        $this->storageDir = $this->getStorageDir();
        $this->shortTermFile = $this->storageDir . '/day11_shortterm.json';
        $this->workingMemoryFile = $this->storageDir . '/day11_working.json';
        $this->longTermFile = $this->storageDir . '/day11_longterm.json';

        // Initialize files if not exist
        if (!file_exists($this->shortTermFile)) {
            $this->save($this->shortTermFile, ['messages' => []]);
        }
        if (!file_exists($this->workingMemoryFile)) {
            $this->save($this->workingMemoryFile, ['currentTask' => null, 'facts' => [], 'progress' => []]);
        }
        if (!file_exists($this->longTermFile)) {
            $this->save($this->longTermFile, ['userProfile' => [], 'knowledgeBase' => [], 'completedTasks' => []]);
        }
    }

    private function getStorageDir()
    {
        $dir = __DIR__ . '/../../storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function save($file, $data)
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function load($file)
    {
        if (!file_exists($file)) {
            return null;
        }
        return json_decode(file_get_contents($file), true);
    }

    // SHORT-TERM MEMORY: Recent messages (current dialog)
    public function addShortTermMessage($role, $text)
    {
        $data = $this->load($this->shortTermFile);
        $data['messages'][] = [
            'role' => $role,
            'text' => $text,
            'timestamp' => time()
        ];

        // Keep only last 10 messages (sliding window)
        if (count($data['messages']) > 10) {
            $data['messages'] = array_slice($data['messages'], -10);
        }

        $this->save($this->shortTermFile, $data);
    }

    public function getShortTermMessages()
    {
        $data = $this->load($this->shortTermFile);
        return $data['messages'] ?? [];
    }

    public function clearShortTermMemory()
    {
        $this->save($this->shortTermFile, ['messages' => []]);
    }

    // WORKING MEMORY: Current task and task-specific facts
    public function setCurrentTask($taskName, $description)
    {
        $data = $this->load($this->workingMemoryFile);
        $data['currentTask'] = [
            'name' => $taskName,
            'description' => $description,
            'startedAt' => time(),
            'progress' => 0
        ];
        $data['facts'] = [];
        $data['progress'] = [];
        $this->save($this->workingMemoryFile, $data);
    }

    public function addWorkingMemoryFact($key, $value)
    {
        $data = $this->load($this->workingMemoryFile);
        $data['facts'][$key] = $value;
        $this->save($this->workingMemoryFile, $data);
    }

    public function addProgressItem($item)
    {
        $data = $this->load($this->workingMemoryFile);
        $data['progress'][] = [
            'text' => $item,
            'timestamp' => time()
        ];
        if ($data['currentTask']) {
            $data['currentTask']['progress'] = count($data['progress']);
        }
        $this->save($this->workingMemoryFile, $data);
    }

    public function getWorkingMemory()
    {
        return $this->load($this->workingMemoryFile);
    }

    public function completeTask()
    {
        $workingData = $this->load($this->workingMemoryFile);
        $longTermData = $this->load($this->longTermFile);

        if ($workingData['currentTask']) {
            $completedTask = [
                'name' => $workingData['currentTask']['name'],
                'description' => $workingData['currentTask']['description'],
                'facts' => $workingData['facts'],
                'progress' => $workingData['progress'],
                'completedAt' => time()
            ];
            $longTermData['completedTasks'][] = $completedTask;
        }

        $this->save($this->longTermFile, $longTermData);
        $this->save($this->workingMemoryFile, ['currentTask' => null, 'facts' => [], 'progress' => []]);
    }

    public function clearWorkingMemory()
    {
        $this->save($this->workingMemoryFile, ['currentTask' => null, 'facts' => [], 'progress' => []]);
    }

    // LONG-TERM MEMORY: User profile and knowledge base
    public function setUserProfile($profile)
    {
        $data = $this->load($this->longTermFile);
        $data['userProfile'] = array_merge($data['userProfile'] ?? [], $profile);
        $this->save($this->longTermFile, $data);
    }

    public function getUserProfile()
    {
        $data = $this->load($this->longTermFile);
        return $data['userProfile'] ?? [];
    }

    public function addKnowledge($domain, $knowledge)
    {
        $data = $this->load($this->longTermFile);
        if (!isset($data['knowledgeBase'][$domain])) {
            $data['knowledgeBase'][$domain] = [];
        }
        $data['knowledgeBase'][$domain][] = [
            'content' => $knowledge,
            'addedAt' => time()
        ];
        $this->save($this->longTermFile, $data);
    }

    public function getKnowledgeBase()
    {
        $data = $this->load($this->longTermFile);
        return $data['knowledgeBase'] ?? [];
    }

    public function getCompletedTasks()
    {
        $data = $this->load($this->longTermFile);
        return $data['completedTasks'] ?? [];
    }

    public function clearAllMemory()
    {
        $this->save($this->shortTermFile, ['messages' => []]);
        $this->save($this->workingMemoryFile, ['currentTask' => null, 'facts' => [], 'progress' => []]);
        $this->save($this->longTermFile, ['userProfile' => [], 'knowledgeBase' => [], 'completedTasks' => []]);
    }

    // Helper: Build context for LLM from all three memory layers
    public function buildContext()
    {
        $context = [];

        // Long-term memory: User profile
        $profile = $this->getUserProfile();
        if (!empty($profile)) {
            $context[] = "## User Profile (Long-Term Memory)";
            foreach ($profile as $key => $value) {
                $context[] = "- $key: $value";
            }
        }

        // Knowledge base
        $kb = $this->getKnowledgeBase();
        if (!empty($kb)) {
            $context[] = "\n## Knowledge Base (Long-Term Memory)";
            foreach ($kb as $domain => $items) {
                $context[] = "### $domain";
                foreach ($items as $item) {
                    $context[] = "- " . $item['content'];
                }
            }
        }

        // Working memory: Current task and facts
        $working = $this->getWorkingMemory();
        if ($working['currentTask']) {
            $context[] = "\n## Current Task (Working Memory)";
            $context[] = "- Task: " . $working['currentTask']['name'];
            $context[] = "- Description: " . $working['currentTask']['description'];
            $context[] = "- Progress: " . $working['currentTask']['progress'] . " steps completed";

            if (!empty($working['facts'])) {
                $context[] = "\n### Task-Specific Facts";
                foreach ($working['facts'] as $key => $value) {
                    $context[] = "- $key: $value";
                }
            }
        }

        // Short-term memory: Recent messages
        $messages = $this->getShortTermMessages();
        if (!empty($messages)) {
            $context[] = "\n## Recent Conversation (Short-Term Memory)";
            foreach ($messages as $msg) {
                $role = strtoupper($msg['role']);
                $context[] = "$role: " . substr($msg['text'], 0, 100) . (strlen($msg['text']) > 100 ? '...' : '');
            }
        }

        return implode("\n", $context);
    }
}

$env = loadEnv(__DIR__ . '/../../.env');

// Verify required env vars for YandexGPT
$required = ['YANDEX_API_KEY', 'YANDEX_FOLDER_ID'];
foreach ($required as $key) {
    if (empty($env[$key])) {
        http_response_code(500);
        die(json_encode(['error' => "Missing environment variable: $key"]));
    }
}

$memory = new MemoryModel();
$client = new LLMClient(
    'yandexgpt',
    $env['YANDEX_API_KEY'],
    $env['YANDEX_FOLDER_ID']
);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/days/day11', '', $path);

header('Content-Type: application/json');

if ($method === 'GET' && $path === '/') {
    // Serve HTML UI
    header('Content-Type: text/html; charset=utf-8');
    include __DIR__ . '/web.php.html';
} elseif ($method === 'POST' && $path === '/api/chat') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'] ?? '';

    if (empty($userMessage)) {
        http_response_code(400);
        die(json_encode(['error' => 'Message required']));
    }

    // Add to short-term memory
    $memory->addShortTermMessage('user', $userMessage);

    // Build system prompt that includes all memory layers
    $memoryContext = $memory->buildContext();
    $systemPrompt = "You are a helpful AI assistant. Use the following context about the user and their work:\n\n$memoryContext\n\nRespond helpfully based on all available context.";

    // Prepare messages for LLM (use short-term messages)
    $messages = [];
    foreach ($memory->getShortTermMessages() as $msg) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['text']
        ];
    }

    try {
        $response = $client->chat($userMessage, [
            'system' => $systemPrompt,
            'messages' => $messages,
            'max_tokens' => 500
        ]);

        $assistantText = is_array($response) ? ($response['text'] ?? $response['message'] ?? json_encode($response)) : $response;

        // Add assistant response to short-term memory
        $memory->addShortTermMessage('assistant', $assistantText);

        echo json_encode([
            'success' => true,
            'response' => $assistantText,
            'memory' => [
                'shortTerm' => count($memory->getShortTermMessages()) . ' messages',
                'working' => $memory->getWorkingMemory()['currentTask'] ? 'Task: ' . $memory->getWorkingMemory()['currentTask']['name'] : 'None',
                'longTerm' => count($memory->getUserProfile()) . ' profile fields'
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'GET' && $path === '/api/memory') {
    // Get all memory layers
    echo json_encode([
        'shortTerm' => [
            'messages' => $memory->getShortTermMessages(),
            'count' => count($memory->getShortTermMessages())
        ],
        'working' => $memory->getWorkingMemory(),
        'longTerm' => [
            'userProfile' => $memory->getUserProfile(),
            'knowledgeBase' => $memory->getKnowledgeBase(),
            'completedTasks' => count($memory->getCompletedTasks()) . ' tasks'
        ]
    ]);
} elseif ($method === 'POST' && $path === '/api/memory/set-profile') {
    $input = json_decode(file_get_contents('php://input'), true);
    $memory->setUserProfile($input['profile'] ?? []);
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $path === '/api/memory/add-knowledge') {
    $input = json_decode(file_get_contents('php://input'), true);
    $memory->addKnowledge($input['domain'] ?? 'general', $input['knowledge'] ?? '');
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $path === '/api/task/set') {
    $input = json_decode(file_get_contents('php://input'), true);
    $memory->setCurrentTask($input['name'] ?? '', $input['description'] ?? '');
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $path === '/api/task/complete') {
    $memory->completeTask();
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $path === '/api/memory/clear-shortterm') {
    $memory->clearShortTermMemory();
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $path === '/api/memory/clear-working') {
    $memory->clearWorkingMemory();
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $path === '/api/memory/clear-all') {
    $memory->clearAllMemory();
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $path === '/api/demo/run') {
    // Run automated demo
    $input = json_decode(file_get_contents('php://input'), true);
    $caseNum = $input['case'] ?? 1;

    include __DIR__ . '/demo_cases.php';

    $caseIndex = $caseNum - 1; // Convert 1-based to 0-based indexing
    if (!isset($demoCases[$caseIndex])) {
        http_response_code(400);
        die(json_encode(['error' => "Demo case $caseNum not found"]));
    }

    $case = $demoCases[$caseIndex];

    // Clear memory for fresh start
    $memory->clearAllMemory();

    $results = [];

    // Execute demo steps
    foreach ($case['steps'] as $step) {
        if ($step['type'] === 'set-profile') {
            $memory->setUserProfile($step['data']);
            $results[] = ['type' => 'profile', 'data' => $step['data']];
        } elseif ($step['type'] === 'add-knowledge') {
            foreach ($step['items'] as $item) {
                $memory->addKnowledge($step['domain'] ?? 'general', $item);
            }
            $results[] = ['type' => 'knowledge', 'domain' => $step['domain'] ?? 'general', 'items' => $step['items']];
        } elseif ($step['type'] === 'set-task') {
            $memory->setCurrentTask($step['name'], $step['description']);
            $results[] = ['type' => 'task', 'name' => $step['name'], 'description' => $step['description']];
        } elseif ($step['type'] === 'chat') {
            $userMsg = $step['message'];
            $memory->addShortTermMessage('user', $userMsg);

            $memoryContext = $memory->buildContext();
            $systemPrompt = "You are a helpful AI assistant. Use the following context about the user and their work:\n\n$memoryContext\n\nRespond helpfully based on all available context.";

            $messages = [];
            foreach ($memory->getShortTermMessages() as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['text']
                ];
            }

            try {
                $response = $client->chat($userMsg, [
                    'system' => $systemPrompt,
                    'messages' => $messages,
                    'max_tokens' => 400
                ]);

                $assistantText = is_array($response) ? ($response['text'] ?? $response['message'] ?? json_encode($response)) : $response;
                $memory->addShortTermMessage('assistant', $assistantText);

                $results[] = [
                    'type' => 'chat',
                    'user' => $userMsg,
                    'assistant' => $assistantText
                ];
            } catch (Exception $e) {
                $results[] = [
                    'type' => 'chat',
                    'user' => $userMsg,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Small delay to avoid rate limiting
        usleep(500000);
    }

    echo json_encode([
        'success' => true,
        'case' => $caseNum,
        'results' => $results,
        'memory' => [
            'shortTerm' => count($memory->getShortTermMessages()),
            'working' => $memory->getWorkingMemory()['currentTask'] ? 'Active' : 'None',
            'longTerm' => count($memory->getUserProfile())
        ]
    ]);
} elseif ($method === 'POST' && $path === '/api/record/start') {
    handleRecordStart(11);
} elseif ($method === 'POST' && $path === '/api/record/stop') {
    handleRecordStop();
} elseif ($method === 'POST' && $path === '/api/upload') {
    handleUpload(11);
} elseif ($method === 'GET' && $path === '/api/upload/status') {
    handleUploadStatus();
} elseif ($method === 'POST' && $path === '/api/upload/cancel') {
    handleUploadCancel();
} elseif ($method === 'GET' && $path === '/api/upload/reset') {
    handleUploadReset();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
