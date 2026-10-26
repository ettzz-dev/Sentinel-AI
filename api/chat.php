<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
if (is_file(__DIR__ . '/mcp_client.php')) require_once __DIR__ . '/mcp_client.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$apiKey = $_SERVER['ANTHROPIC_API_KEY']
    ?? $_SERVER['REDIRECT_ANTHROPIC_API_KEY']
    ?? $_ENV['ANTHROPIC_API_KEY']
    ?? getenv('ANTHROPIC_API_KEY');

if (empty($apiKey)) {
    log_error('ANTHROPIC_API_KEY not configured');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'ANTHROPIC_API_KEY not configured. Verify VirtualHost SetEnv settings.']);
    exit;
}

set_time_limit(150);

// ---------- helper: one call to the Anthropic Messages API ----------
function anthropic_call(string $apiKey, array $payload): array
{$ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 100,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException("Connection to Anthropic failed: $err");
    $data = json_decode($raw, true);
    if ($code !== 200) {
        throw new RuntimeException("Anthropic API error ($code): " . ($data['error']['message'] ?? $raw));
    }
    return $data;
}

// 1. DUAL PARSING ENGINE: Intercept both Form Data (Uploads) and Legacy raw JSON streams
$conversationId = null;
$rawMessage = '';
$uploadedFilesContext = '';

$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (stripos($contentType, 'multipart/form-data') !== false) {
    $rawMessage = trim($_POST['message'] ?? '');
    $conversationId = (!empty($_POST['conversation_id']) && $_POST['conversation_id'] !== 'null')
        ? $_POST['conversation_id'] : null;

    if (!empty($_FILES['files']['name'])) {
        $maxFileBytes  = 200 * 1024;   // per file
        $maxTotalBytes = 500 * 1024;   // all files combined

        $names = (array)$_FILES['files']['name'];
        $tmps  = (array)$_FILES['files']['tmp_name'];
        $errs  = (array)$_FILES['files']['error'];
        $sizes = (array)$_FILES['files']['size'];

        $textExts = ['txt','log','conf','cfg','ini','json','yaml','yml','xml','csv','md',
                     'js','ts','php','html','css','sh','py','toml','sql','rules'];
        $total = 0;
        $attached = 0;
        $blocks = '';

        foreach ($names as $i => $name) {
            $name = basename((string)$name);

            if ($errs[$i] !== UPLOAD_ERR_OK) {
                $blocks .= "[File \"$name\" failed to upload (PHP upload error {$errs[$i]})]\n";
                continue;
            }
            if (!is_uploaded_file($tmps[$i])) continue;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $raw = (string)file_get_contents($tmps[$i], false, null, 0, $maxFileBytes);
            $isText = in_array($ext, $textExts, true)
                   || strpos(substr($raw, 0, 8192), "\0") === false;   // no NUL bytes = text

            if (!$isText) {
                $blocks .= "[File \"$name\" skipped: binary format, {$sizes[$i]} bytes]\n";
                continue;
            }
            if ($total + strlen($raw) > $maxTotalBytes) {
                $blocks .= "[File \"$name\" skipped: total attachment size limit reached]\n";
                continue;
            }

            $total += strlen($raw);
            $attached++;
            $truncNote = ($sizes[$i] > $maxFileBytes)
                ? " (truncated to first " . ($maxFileBytes / 1024) . " KB)" : '';
            $blocks .= "--- FILE START: $name$truncNote ---\n$raw\n--- FILE END ---\n";
        }

        if ($blocks !== '') {
            $uploadedFilesContext = "\n\n[USER ATTACHED THE FOLLOWING FILES]:\n" . $blocks;
        }
        if ($rawMessage === '' && $attached === 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => trim($blocks) ?: 'No readable files were attached.']);
            exit;
        }
    }
} else {
    // Legacy fallback for plain JSON requests
    $input = json_decode(file_get_contents('php://input'), true);
    $rawMessage = trim($input['message'] ?? '');
    $conversationId = (!empty($input['conversation_id']) && $input['conversation_id'] !== 'null')
        ? $input['conversation_id'] : null;
}

if ($rawMessage === '' && empty($uploadedFilesContext)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Message cannot be empty']);
    exit;
}

$selectedModel = 'claude-sonnet-5-5';
$userMessage   = $rawMessage;

if (preg_match('/^\/(pro|deep)(?:\s+|$)/i', $rawMessage)) {
    $selectedModel = 'claude-opus-5-5';
    $userMessage = trim(preg_replace('/^\/(pro|deep)(?:\s+|$)/i', '', $rawMessage));
} elseif (preg_match('/^\/lite(?:\s+|$)/i', $rawMessage)) {
    $selectedModel = 'claude-haiku-5-5';
    $userMessage = trim(preg_replace('/^\/lite(?:\s+|$)/i', '', $rawMessage));
}

// Append parsed file contents straight onto user prompt so the Claude Engine treats it like context data
$promptToModel = $userMessage . $uploadedFilesContext;

if ($userMessage === '' && !empty($uploadedFilesContext)) {
    // If user uploaded a document file but typed no text, set a default instructional prompt sequence
    $userMessage = "[Analyzed document file attachment content]";
    $promptToModel = "Analyze these network diagnostic parameters for me and summarize what you find:" . $uploadedFilesContext;
} elseif ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Add a question after the command, e.g. "/pro what is OSPF?"']);
    exit;
}

$userId = 1;

try {
    // 3. Conversation Tracking Management
    if ($conversationId) {
        $checkStmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ?");
        $checkStmt->execute([$conversationId]);
        if (!$checkStmt->fetch()) $conversationId = null;
    }
    if (!$conversationId) {
        $stmt = $pdo->prepare("INSERT INTO conversations (title, user_id, created_at) VALUES (:title, :user_id, NOW())");
        $stmt->execute([':title' => mb_substr($userMessage, 0, 45), ':user_id' => $userId]);
        $conversationId = (string)$pdo->lastInsertId();
    }

    // 4. Short-term memory: this conversation's history
    $historyStmt = $pdo->prepare("SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY id ASC");
    $historyStmt->execute([$conversationId]);
    $messages = [];
    foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $msg) {
        $text = trim((string)$msg['content']);
        if ($text === '') continue;
        $messages[] = ['role' => $msg['role'] === 'user' ? 'user' : 'assistant', 'content' => $text];
    }
    
    // Pass along augmented payload block instead of flat raw messages text variables
    $messages[] = ['role' => 'user', 'content' => $promptToModel];

    // 5. Long-term memory: saved facts from ALL conversations
    $memStmt = $pdo->prepare("SELECT id, content FROM memories WHERE user_id = ? ORDER BY id ASC LIMIT 200");
    $memStmt->execute([$userId]);
    $memories = $memStmt->fetchAll(PDO::FETCH_ASSOC);

    $memoryBlock = '';
    if ($memories) {
        $memoryBlock = "\n\nThings you remember about the user and their setup (id: fact):\n";
        foreach ($memories as $m) {
            $memoryBlock .= "[{$m['id']}] {$m['content']}\n";
        }
    } else {
        $memoryBlock = "\n\nYou have no saved memories yet.";
    }

    // OpenWrt gateway tools via MCP (read-only, fails soft if the router is unreachable)
    $mcp = null;
    $mcpTools = [];
    $mcpMap = [];
    $mcpDebug = ['loaded' => [], 'blocked' => [], 'error' => null];
    $mcpNote = '';
    if (class_exists('McpClient')) {
        try {
            $mcp = new McpClient();
            $info = $mcp->anthropicTools();
            $mcpTools = $info['tools'];
            $mcpMap   = $info['map'];
            $mcpDebug = ['loaded' => $info['loaded'], 'blocked' => $info['blocked'], 'error' => $info['error']];
        } catch (\Throwable $e) {
            log_exception($e, 'MCP tool discovery failed');
            $mcpDebug['error'] = $e->getMessage();
        }
    }
    if ($mcpTools) {
        $mcpNote = "\n\nGATEWAY ACCESS: You have read-only access to the user's OpenWrt gateway through the tools whose names start with owrt_. "
                 . 'Use them when the user asks about the gateway, network status, clients, interfaces, logs or connectivity, and base your answer on the real tool output. '
                 . 'You cannot change anything on the gateway; never claim you did. Never reveal secrets that appear in tool output.';
        if (isset($mcpMap['owrt_get-routerpi-status'])) {
            $mcpNote .= ' RouterPi is the OpenWrt Raspberry Pi in the car (UPS HAT battery, SIM7600 LTE modem, separate GPS module). '
                      . 'Use owrt_get-routerpi-status for its battery, LTE signal, CPU, memory, temperature or uptime. '
                      . 'LTE RSRP guide: >= -80 dBm excellent, -80 to -90 good, -90 to -100 fair, < -100 poor; SINR > 20 dB excellent, 13-20 good, 0-13 fair, < 0 poor. '
                      . 'If the tool fails, say the car may be off or out of coverage rather than guessing values.';
        }
    }

    date_default_timezone_set('Africa/Johannesburg');
    $system = 'You are Sentinel AI, a helpful and concise network engineer monitoring and managing local infrastructure. '
        . 'The current date and time is ' . date('l, j F Y, H:i') . ' (South Africa, SAST). '
        . 'Use web search for anything current (news, sports results, prices, versions) and never guess such facts. '
        . "\n\nMEMORY: You have a persistent memory across conversations. "
        . 'Use the remember tool when the user shares a durable fact worth keeping (their devices, hostnames, network layout, preferences, projects, decisions) or explicitly asks you to remember something. '
        . 'Save short, standalone facts. Do not save duplicates of facts already listed. '
        . 'Use the forget tool when the user asks you to forget something or a saved fact is outdated (replace it with a new remember call if needed). '
        . 'NEVER save passwords, API keys, tokens, or other secrets. Do not save trivia or one-off questions. '
        . 'When analyzing tunnel metrics or processing output from the check-tunnels tool, always verify the status.'
        . 'Never hallucinate or assume additional inactive tunnels exist. '
        . 'Do not announce every save; a brief confirmation is enough when the user asked for it.'
        . $memoryBlock
        . $mcpNote;

        $tools = [
        ['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 3],
        [
            'name'        => 'remember',
            'description' => 'Save one short, standalone, durable fact about the user or their setup to long-term memory.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => ['fact' => ['type' => 'string', 'description' => 'The fact to save']],
                'required'   => ['fact'],
            ],
        ],
        [
            'name'        => 'forget',
            'description' => 'Delete a saved memory by its id (shown in square brackets in the memory list).',
            'input_schema' => [
                'type'       => 'object',
                'properties' => ['memory_id' => ['type' => 'integer', 'description' => 'The id of the memory to delete']],
                'required'   => ['memory_id'],
            ],
        ],
    ];
    if ($mcpTools) $tools = array_merge($tools, $mcpTools);

    $payload = [
        'model'      => $selectedModel,
        'max_tokens' => 4096,
        'system'     => $system,
        'tools'      => $tools,
    ];

    // ---------- tool loop ----------
    $reply     = '';
    $maxRounds = 6;

    for ($round = 0; $round < $maxRounds; $round++) {
        $payload['messages'] = $messages;
        $resp    = anthropic_call($apiKey, $payload);
        $content = $resp['content'] ?? [];
        $stop    = $resp['stop_reason'] ?? '';

        // Collect text, and make sure empty tool inputs re-encode as {} not []
        foreach ($content as $i => $block) {
            if (($block['type'] ?? '') === 'text') {
                $reply .= $block['text'] ?? '';
            }
            if (($block['type'] ?? '') === 'tool_use' && empty($block['input'])) {
                $content[$i]['input'] = new stdClass();
            }
        }

        if ($stop === 'pause_turn') {
            // Server-side web search needs more time: hand the turn back as-is
            $messages[] = ['role' => 'assistant', 'content' => $content];
            continue;
        }

        if ($stop !== 'tool_use') break;   // end_turn / max_tokens / etc.

        // Run client-side tools (remember, forget, owrt_*)
        $messages[] = ['role' => 'assistant', 'content' => $content];
        $results = [];

        foreach ($content as $block) {
            if (($block['type'] ?? '') !== 'tool_use') continue;

            $name  = $block['name'];
            $input = (array)($block['input'] ?? []);
            $out   = '';
            $isErr = false;

            try {
                if ($name === 'remember') {
                    $fact = trim((string)($input['fact'] ?? ''));
                    if ($fact === '') {
                        $out = 'Nothing to save.'; $isErr = true;
                    } elseif (preg_match('/(password|passwd|api[_\s-]?key|secret|token)\s*[:=]/i', $fact)) {
                        $out = 'Refused: looks like a secret.'; $isErr = true;
                    } else {
                        $fact = mb_substr($fact, 0, 300);
                        $dup = $pdo->prepare("SELECT id FROM memories WHERE user_id = ? AND content = ?");
                        $dup->execute([$userId, $fact]);
                        if ($dup->fetch()) {
                            $out = 'Already saved.';
                        } else {
                            $ins = $pdo->prepare("INSERT INTO memories (user_id, content) VALUES (?, ?)");
                            $ins->execute([$userId, $fact]);
                            $out = 'Saved (id ' . $pdo->lastInsertId() . ').';
                        }
                    }
                } elseif ($name === 'forget') {
                    $del = $pdo->prepare("DELETE FROM memories WHERE id = ? AND user_id = ?");
                    $del->execute([(int)($input['memory_id'] ?? 0), $userId]);
                    $out = $del->rowCount() ? 'Deleted.' : 'No memory with that id.';
               } elseif (isset($mcpMap[$name]) && $mcp && method_exists($mcp, 'callTool')) {
					$res = $mcp->callTool($mcpMap[$name], $input);   
$out = isset($res['text']) ? $res['text'] : json_encode($res, JSON_INVALID_UTF8_SUBSTITUTE);
if (isset($res['ok']) && $res['ok'] === false) {
    $isErr = true;
}
					} else {
                    $out = "Unknown or unavailable tool: $name"; $isErr = true;
                }
            } catch (\Throwable $e) {
                log_exception($e, "Tool '$name' failed", ['conversation_id' => $conversationId]);
                $out = 'Tool error: ' . $e->getMessage(); $isErr = true;
            }

            $results[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $block['id'],
                'content'     => mb_substr($out, 0, 20000),
                'is_error'    => $isErr,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $results];
    }

    $reply = trim($reply);
    if ($reply === '') $reply = '(No response received. Please try again.)';

    // ---------- save this exchange ----------
    $save = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)");
    $save->execute([$conversationId, 'user', $userMessage]);
    $save->execute([$conversationId, 'assistant', $reply]);

    echo json_encode([
        'success'         => true,
        'status'          => 'ok',
        'reply'           => $reply,
        'conversation_id' => $conversationId,
        'model'           => $selectedModel,
        'mcp'             => $mcpDebug,
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    log_exception($e, 'Chat request failed', ['conversation_id' => $conversationId, 'model' => $selectedModel]);
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'error' => $e->getMessage()]);
}