<?php

require __DIR__ . '/ai/gemini-assistant/vendor/autoload.php';
require_once __DIR__ . '/../config/db.php'; // Pulls in $pdo from config/db.php

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$apiKey = getenv('GEMINI_API_KEY');
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Message cannot be empty']);
    exit;
}

// Treat empty string or "null" from localStorage as null
$conversationId = (!empty($input['conversation_id']) && $input['conversation_id'] !== 'null') 
    ? $input['conversation_id'] 
    : null;

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'API key is not configured on the server.']);
    exit;
}

try {
    // 1. If this is a new chat, create a new conversation entry
    if (!$conversationId) {
        $title = mb_substr($userMessage, 0, 45); // First 45 chars as title
        $stmt = $pdo->prepare("INSERT INTO conversations (title, created_at) VALUES (:title, NOW())");
        $stmt->execute([':title' => $title]);
        $conversationId = (string)$pdo->lastInsertId();
    }

    // 2. Save User prompt
    $stmtUser = $pdo->prepare("INSERT INTO messages (conversation_id, role, content, created_at) VALUES (:cid, 'user', :content, NOW())");
    $stmtUser->execute([
        ':cid' => $conversationId,
        ':content' => $userMessage
    ]);

    // 3. Request Gemini inference
    $client = Gemini::client($apiKey);
    $response = $client->generativeModel(model: 'gemini-3.6-flash')
        ->withSystemInstruction(\Gemini\Data\Content::parse('You are Sentinel AI, a helpful and concise network engineer. You will be monitoring and managing my local network infrastructure.'))
        ->generateContent($userMessage);

    $aiReply = $response->text();

    // 4. Save Sentinel AI response
    $stmtAi = $pdo->prepare("INSERT INTO messages (conversation_id, role, content, created_at) VALUES (:cid, 'assistant', :content, NOW())");
    $stmtAi->execute([
        ':cid' => $conversationId,
        ':content' => $aiReply
    ]);

    // 5. Send back response with conversation_id to sync sidebar & localStorage
    echo json_encode([
        'status' => 'success',
        'reply' => $aiReply,
        'conversation_id' => $conversationId
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'conversation_id' => $conversationId
    ]);
}