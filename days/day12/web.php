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
        $this->shortTermFile = $this->storageDir . '/day12_shortterm.json';
        $this->workingMemoryFile = $this->storageDir . '/day12_working.json';
        $this->longTermFile = $this->storageDir . '/day12_longterm.json';

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

    protected function save($file, $data)
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function load($file)
    {
        if (!file_exists($file)) {
            return null;
        }
        return json_decode(file_get_contents($file), true);
    }

    public function addShortTermMessage($role, $text)
    {
        $data = $this->load($this->shortTermFile);
        $data['messages'][] = [
            'role' => $role,
            'text' => $text,
            'timestamp' => time()
        ];

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

    public function getWorkingMemory()
    {
        return $this->load($this->workingMemoryFile);
    }

    public function clearWorkingMemory()
    {
        $this->save($this->workingMemoryFile, ['currentTask' => null, 'facts' => [], 'progress' => []]);
    }

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

    public function clearAllMemory()
    {
        $this->save($this->shortTermFile, ['messages' => []]);
        $this->save($this->workingMemoryFile, ['currentTask' => null, 'facts' => [], 'progress' => []]);
        $this->save($this->longTermFile, ['userProfile' => [], 'knowledgeBase' => [], 'completedTasks' => []]);
    }

    public function buildContext()
    {
        $context = [];

        $profile = $this->getUserProfile();
        if (!empty($profile)) {
            $context[] = "## User Profile (Long-Term Memory)";
            foreach ($profile as $key => $value) {
                $context[] = "- $key: $value";
            }
        }

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

        $working = $this->getWorkingMemory();
        if ($working['currentTask']) {
            $context[] = "\n## Current Task (Working Memory)";
            $context[] = "- Task: " . $working['currentTask']['name'];
            $context[] = "- Description: " . $working['currentTask']['description'];
        }

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

class PersonalizedAgent extends MemoryModel
{
    private $profilesFile;
    private $activeProfileFile;
    private $storageDir;

    public function __construct()
    {
        parent::__construct();
        $this->storageDir = __DIR__ . '/../../storage';
        $this->profilesFile = $this->storageDir . '/day12_profiles.json';
        $this->activeProfileFile = $this->storageDir . '/day12_active.json';

        // Initialize profile files
        if (!file_exists($this->profilesFile)) {
            $this->saveFile($this->profilesFile, $this->getDefaultProfiles());
        }
        if (!file_exists($this->activeProfileFile)) {
            $this->saveFile($this->activeProfileFile, ['name' => 'Default', 'profile' => null]);
        }
    }

    private function getDefaultProfiles()
    {
        return [
            'Beginner' => [
                'name' => 'Beginner',
                'role' => 'Student',
                'expertise' => 'beginner',
                'style' => 'friendly',
                'format' => 'prose',
                'depth' => 'standard',
                'language' => 'en',
                'avoid' => ['jargon', 'advanced concepts'],
                'always_include' => ['simple examples', 'step-by-step explanations']
            ],
            'Expert' => [
                'name' => 'Expert',
                'role' => 'Senior Engineer',
                'expertise' => 'expert',
                'style' => 'technical',
                'format' => 'code_first',
                'depth' => 'detailed',
                'language' => 'en',
                'avoid' => ['oversimplifications', 'basic explanations'],
                'always_include' => ['performance implications', 'edge cases']
            ],
            'Business' => [
                'name' => 'Business',
                'role' => 'Manager',
                'expertise' => 'intermediate',
                'style' => 'formal',
                'format' => 'bullets',
                'depth' => 'brief',
                'language' => 'en',
                'avoid' => ['technical jargon', 'implementation details'],
                'always_include' => ['business impact', 'ROI considerations']
            ],
            'Casual' => [
                'name' => 'Casual',
                'role' => 'Learner',
                'expertise' => 'beginner',
                'style' => 'casual',
                'format' => 'prose',
                'depth' => 'standard',
                'language' => 'en',
                'avoid' => [],
                'always_include' => ['analogies', 'real-world examples']
            ]
        ];
    }

    private function saveFile($file, $data)
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function loadFile($file)
    {
        if (!file_exists($file)) {
            return null;
        }
        return json_decode(file_get_contents($file), true);
    }

    public function saveProfile($name, $data)
    {
        $profiles = $this->loadFile($this->profilesFile);
        $profiles[$name] = array_merge(['name' => $name], $data);
        $this->saveFile($this->profilesFile, $profiles);
    }

    public function loadProfileByName($name)
    {
        $profiles = $this->loadFile($this->profilesFile);
        if (isset($profiles[$name])) {
            $this->saveFile($this->activeProfileFile, ['name' => $name, 'profile' => $profiles[$name]]);
            return $profiles[$name];
        }
        return null;
    }

    public function listProfiles()
    {
        return $this->loadFile($this->profilesFile) ?? [];
    }

    public function deleteProfile($name)
    {
        $profiles = $this->loadFile($this->profilesFile);
        unset($profiles[$name]);
        $this->saveFile($this->profilesFile, $profiles);

        // Clear active profile if it was deleted
        $active = $this->loadFile($this->activeProfileFile);
        if ($active['name'] === $name) {
            $this->saveFile($this->activeProfileFile, ['name' => null, 'profile' => null]);
        }
    }

    public function getActiveProfile()
    {
        $active = $this->loadFile($this->activeProfileFile);
        return $active['profile'] ?? null;
    }

    public function buildPersonalizationPrompt($profile)
    {
        if (!$profile) {
            return '';
        }

        $directives = [];

        // Expertise level
        if ($profile['expertise'] === 'beginner') {
            $directives[] = "Explain all concepts from scratch, assume no prior knowledge.";
        } elseif ($profile['expertise'] === 'intermediate') {
            $directives[] = "Assume basic domain familiarity, skip fundamentals.";
        } elseif ($profile['expertise'] === 'expert') {
            $directives[] = "Use expert-level terminology, skip basics entirely.";
        }

        // Style
        if ($profile['style'] === 'formal') {
            $directives[] = "Use formal, professional language.";
        } elseif ($profile['style'] === 'casual') {
            $directives[] = "Use casual, friendly, conversational tone.";
        } elseif ($profile['style'] === 'technical') {
            $directives[] = "Use precise technical terminology and jargon.";
        } elseif ($profile['style'] === 'friendly') {
            $directives[] = "Use friendly, approachable, encouraging tone.";
        }

        // Format
        if ($profile['format'] === 'bullets') {
            $directives[] = "Always use bullet points for structure.";
        } elseif ($profile['format'] === 'code_first') {
            $directives[] = "Start with code example, then explain.";
        } elseif ($profile['format'] === 'concise') {
            $directives[] = "Be extremely concise, one paragraph max unless asked.";
        } elseif ($profile['format'] === 'prose') {
            $directives[] = "Write in flowing prose paragraphs.";
        }

        // Depth
        if ($profile['depth'] === 'brief') {
            $directives[] = "Keep responses brief and to the point.";
        } elseif ($profile['depth'] === 'detailed') {
            $directives[] = "Provide comprehensive detailed answers.";
        }

        // Language
        if ($profile['language'] && $profile['language'] !== 'en') {
            $directives[] = "Respond ONLY in {$profile['language']} language.";
        }

        // Avoid
        if (!empty($profile['avoid'])) {
            $avoidList = implode(', ', $profile['avoid']);
            $directives[] = "Avoid: $avoidList.";
        }

        // Always include
        if (!empty($profile['always_include'])) {
            $includeList = implode(', ', $profile['always_include']);
            $directives[] = "Always include: $includeList.";
        }

        return "## Personalization Directives\n" . implode("\n", $directives);
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

$agent = new PersonalizedAgent();
$client = new LLMClient(
    'yandexgpt',
    $env['YANDEX_API_KEY'],
    $env['YANDEX_FOLDER_ID']
);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/days/day12', '', $path);

header('Content-Type: application/json');

if ($method === 'GET' && $path === '/') {
    header('Content-Type: text/html; charset=utf-8');
    include __DIR__ . '/web.php.html';
} elseif ($method === 'POST' && $path === '/api/chat') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'] ?? '';

    if (empty($userMessage)) {
        http_response_code(400);
        die(json_encode(['error' => 'Message required']));
    }

    $agent->addShortTermMessage('user', $userMessage);

    $profile = $agent->getActiveProfile();
    $personalization = $agent->buildPersonalizationPrompt($profile);
    $memoryContext = $agent->buildContext();
    $systemPrompt = "You are a helpful AI assistant personalized for the user.\n\n$personalization\n\nUse the following context:\n\n$memoryContext\n\nRespond helpfully based on the personalization directives and context.";

    $messages = [];
    foreach ($agent->getShortTermMessages() as $msg) {
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
        $agent->addShortTermMessage('assistant', $assistantText);

        echo json_encode([
            'success' => true,
            'response' => $assistantText,
            'profile' => $profile ? $profile['name'] : 'None'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'POST' && $path === '/api/compare') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'] ?? '';
    $profileAName = $input['profileA'] ?? null;
    $profileBName = $input['profileB'] ?? null;

    if (empty($userMessage)) {
        http_response_code(400);
        die(json_encode(['error' => 'Message required']));
    }

    if (!$profileAName || !$profileBName) {
        http_response_code(400);
        die(json_encode(['error' => 'Both profiles required']));
    }

    $profiles = $agent->listProfiles();
    $profileA = $profiles[$profileAName] ?? null;
    $profileB = $profiles[$profileBName] ?? null;

    if (!$profileA || !$profileB) {
        http_response_code(400);
        die(json_encode(['error' => 'One or both profiles not found']));
    }

    // Build context without personalizations
    $contextOnly = $agent->buildContext();

    $results = [];

    // Get response from Profile A
    try {
        $personalizationA = $agent->buildPersonalizationPrompt($profileA);
        $systemPromptA = "You are a helpful AI assistant personalized for the user.\n\n$personalizationA\n\nUse the following context:\n\n$contextOnly\n\nRespond helpfully.";

        $response = $client->chat($userMessage, [
            'system' => $systemPromptA,
            'max_tokens' => 400
        ]);

        $results['responseA'] = is_array($response) ? ($response['text'] ?? $response['message'] ?? json_encode($response)) : $response;
    } catch (Exception $e) {
        $results['responseA'] = "Error: " . $e->getMessage();
    }

    usleep(500000); // Delay to avoid rate limiting

    // Get response from Profile B
    try {
        $personalizationB = $agent->buildPersonalizationPrompt($profileB);
        $systemPromptB = "You are a helpful AI assistant personalized for the user.\n\n$personalizationB\n\nUse the following context:\n\n$contextOnly\n\nRespond helpfully.";

        $response = $client->chat($userMessage, [
            'system' => $systemPromptB,
            'max_tokens' => 400
        ]);

        $results['responseB'] = is_array($response) ? ($response['text'] ?? $response['message'] ?? json_encode($response)) : $response;
    } catch (Exception $e) {
        $results['responseB'] = "Error: " . $e->getMessage();
    }

    echo json_encode([
        'success' => true,
        'question' => $userMessage,
        'profileA' => $profileAName,
        'profileB' => $profileBName,
        'responseA' => $results['responseA'],
        'responseB' => $results['responseB']
    ]);
} elseif ($method === 'GET' && $path === '/api/profiles') {
    echo json_encode($agent->listProfiles());
} elseif ($method === 'POST' && $path === '/api/profiles/save') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? '';
    $data = $input['data'] ?? [];

    if (empty($name)) {
        http_response_code(400);
        die(json_encode(['error' => 'Profile name required']));
    }

    $agent->saveProfile($name, $data);
    echo json_encode(['success' => true, 'name' => $name]);
} elseif ($method === 'POST' && $path === '/api/profiles/load') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? '';

    if (empty($name)) {
        http_response_code(400);
        die(json_encode(['error' => 'Profile name required']));
    }

    $profile = $agent->loadProfileByName($name);
    if (!$profile) {
        http_response_code(404);
        die(json_encode(['error' => 'Profile not found']));
    }

    echo json_encode(['success' => true, 'profile' => $profile]);
} elseif ($method === 'POST' && $path === '/api/profiles/delete') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? '';

    if (empty($name)) {
        http_response_code(400);
        die(json_encode(['error' => 'Profile name required']));
    }

    $agent->deleteProfile($name);
    echo json_encode(['success' => true]);
} elseif ($method === 'GET' && $path === '/api/active-profile') {
    $profile = $agent->getActiveProfile();
    echo json_encode(['profile' => $profile]);
} elseif ($method === 'POST' && $path === '/api/memory/clear') {
    $agent->clearAllMemory();
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $path === '/api/demo/run') {
    $input = json_decode(file_get_contents('php://input'), true);
    $caseNum = $input['case'] ?? 1;

    $demoCases = include __DIR__ . '/demo_cases.php';

    if (!isset($demoCases[$caseNum])) {
        http_response_code(400);
        die(json_encode(['error' => "Demo case $caseNum not found"]));
    }

    $case = $demoCases[$caseNum];
    $agent->clearAllMemory();
    $results = [];

    foreach ($case['steps'] as $step) {
        if ($step['type'] === 'load-profile') {
            $profile = $agent->loadProfileByName($step['profileName']);
            $results[] = ['type' => 'profile', 'name' => $step['profileName'], 'profile' => $profile];
        } elseif ($step['type'] === 'question') {
            $profile = $agent->getActiveProfile();
            $personalization = $agent->buildPersonalizationPrompt($profile);
            $contextOnly = $agent->buildContext();
            $systemPrompt = "You are a helpful AI assistant personalized for the user.\n\n$personalization\n\nUse the following context:\n\n$contextOnly\n\nRespond helpfully.";

            try {
                $response = $client->chat($step['question'], [
                    'system' => $systemPrompt,
                    'max_tokens' => 400
                ]);

                $assistantText = is_array($response) ? ($response['text'] ?? $response['message'] ?? json_encode($response)) : $response;

                $results[] = [
                    'type' => 'question',
                    'profile' => $profile ? $profile['name'] : 'None',
                    'question' => $step['question'],
                    'response' => $assistantText
                ];
            } catch (Exception $e) {
                $results[] = [
                    'type' => 'question',
                    'profile' => $profile ? $profile['name'] : 'None',
                    'question' => $step['question'],
                    'error' => $e->getMessage()
                ];
            }

            usleep(500000);
        } elseif ($step['type'] === 'compare') {
            $profiles = $agent->listProfiles();
            $profileA = $profiles[$step['profileA']] ?? null;
            $profileB = $profiles[$step['profileB']] ?? null;
            $contextOnly = $agent->buildContext();

            $responseA = '';
            $responseB = '';

            if ($profileA) {
                try {
                    $personalization = $agent->buildPersonalizationPrompt($profileA);
                    $systemPrompt = "You are a helpful AI assistant.\n\n$personalization\n\nRespond helpfully.";
                    $response = $client->chat($step['question'], [
                        'system' => $systemPrompt,
                        'max_tokens' => 300
                    ]);
                    $responseA = is_array($response) ? ($response['text'] ?? $response['message'] ?? json_encode($response)) : $response;
                } catch (Exception $e) {
                    $responseA = "Error: " . $e->getMessage();
                }
            }

            usleep(500000);

            if ($profileB) {
                try {
                    $personalization = $agent->buildPersonalizationPrompt($profileB);
                    $systemPrompt = "You are a helpful AI assistant.\n\n$personalization\n\nRespond helpfully.";
                    $response = $client->chat($step['question'], [
                        'system' => $systemPrompt,
                        'max_tokens' => 300
                    ]);
                    $responseB = is_array($response) ? ($response['text'] ?? $response['message'] ?? json_encode($response)) : $response;
                } catch (Exception $e) {
                    $responseB = "Error: " . $e->getMessage();
                }
            }

            $results[] = [
                'type' => 'compare',
                'question' => $step['question'],
                'profileA' => $step['profileA'],
                'responseA' => $responseA,
                'profileB' => $step['profileB'],
                'responseB' => $responseB
            ];

            usleep(500000);
        }
    }

    echo json_encode([
        'success' => true,
        'case' => $caseNum,
        'results' => $results
    ]);
} elseif ($method === 'POST' && $path === '/api/record/start') {
    handleRecordStart(12);
} elseif ($method === 'POST' && $path === '/api/record/stop') {
    handleRecordStop();
} elseif ($method === 'POST' && $path === '/api/upload') {
    handleUpload(12);
} elseif ($method === 'GET' && $path === '/api/upload/status') {
    handleUploadStatus();
} elseif ($method === 'POST' && $path === '/api/upload/cancel') {
    handleUploadCancel();
} elseif ($method === 'POST' && $path === '/api/upload/reset') {
    handleUploadReset();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
