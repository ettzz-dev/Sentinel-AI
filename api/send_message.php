<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/mcp_client.php';

$data = json_decode(file_get_contents("php://input"), true);

$message = $data['message'] ?? null;
$conversationId = $data['conversation_id'] ?? null;
$userId = 1;

// Validate input
$input = json_decode(file_get_contents("php://input"), true);

$message = $input['message'] ?? null;
$conversation_id = $input['conversation_id'] ?? null;

if (!$message) {
    echo json_encode(["error" => "Message is required"]);
    exit;
}

// 🔥 MCP Commands
$commands = [

    // Testing
    "test-tool" => "test-tool",

    // Cloudflare
    "system health" => "get-system-health",
    "tunnel status" => "check-tunnels",

    // Home Assistant
    "home status" => "get-home-status",

    // AutoPi
    "vehicle status" => "get-vehicle-status",

    // Network
    "network health" => "get-network-health"
];

$key = strtolower(trim($message));

if (isset($commands[$key])) {

    $result = callMcpTool($commands[$key]);

    echo json_encode([
        "reply" => $result['result']['content'][0]['text']
    ]);

    exit;
}

if (trim(strtolower($message)) === "whoami") {

    $result = callMcpTool("whoami");

    echo json_encode([
        "reply" => $result['result']['content'][0]['text']
    ]);

    exit;
}




// 🧠 Ensure conversation exists
if ($conversationId) {
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);

    if (!$stmt->fetch()) {
        $conversationId = null;
    }
}

// 🧠 Create conversation if it doesn't exist yet
if (!$conversationId) {
    // 1. Insert a fresh conversation session row
    $stmt = $pdo->prepare("INSERT INTO conversations (title, user_id) VALUES (?, ?)");
    $stmt->execute(['New Chat', $userId]);
    
    // 2. Grab the auto-incremented ID that the database just generated
    $conversationId = $pdo->lastInsertId();
} else {
    // If it already exists, just update the title if it's still default
    $stmt = $pdo->prepare("
        UPDATE conversations
        SET title = ?
        WHERE id = ? AND title = 'New Chat'
    ");
    $stmt->execute([substr($message, 0, 40), $conversationId]);
}

// 🧠 Save user message
$stmt = $pdo->prepare("
    INSERT INTO messages (conversation_id, role, content)
    VALUES (?, 'user', ?)
");

$stmt->execute([$conversationId, $message]);

// 🧠 Load full history
$stmt = $pdo->prepare("
    SELECT role, content
    FROM messages
    WHERE conversation_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$conversationId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🧠 Build messages for AI
$messages = [
    ["role" => "system", "content" => "You are a networking assistant."]
];

foreach ($history as $msg) {
    $messages[] = [
        "role" => $msg['role'],
        "content" => $msg['content']
    ];
}

// 🔐 Load API key
$apiKey = getenv("OPENAI_API_KEY");

// 🧠 Prepare API payload
$payload = [
    "model" => "gpt-4o-mini",
    "messages" => $messages
];

// 🌐 Call OpenAI
$ch = curl_init("https://api.openai.com/v1/chat/completions");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);

// 🔥 Handle curl errors
if ($response === false) {
    $assistantReply = "Curl error: " . curl_error($ch);
    curl_close($ch);
} else {
    $result = json_decode($response, true);

    if (isset($result['error'])) {
        $assistantReply = "API error: " . $result['error']['message'];
    } else {
        $assistantReply = $result['choices'][0]['message']['content'] ?? "No response from AI";
    }

    curl_close($ch);
}

// 🧠 Save assistant reply
$stmt = $pdo->prepare("
    INSERT INTO messages (conversation_id, role, content)
    VALUES (?, 'assistant', ?)
");
$stmt->execute([$conversationId, $assistantReply]);

// 🧠 Return response
echo json_encode([
    "conversation_id" => $conversationId,
    "reply" => $assistantReply
]);
