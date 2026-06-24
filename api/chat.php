<?php

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get API key
$apiKey = getenv('OPENAI_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key missing']);
    exit;
}

// Read input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No message provided']);
    exit;
}

$userMessage = trim($input['message']);

// Call OpenAI (NEW API)
$ch = curl_init('https://api.openai.com/v1/responses');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'gpt-4.1-mini',
        'input' => $userMessage
    ])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

// Handle curl error
if ($curlError) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $curlError]);
    exit;
}

// Decode response
$data = json_decode($response, true);

// Handle API error
if ($httpCode !== 200) {
    $msg = $data['error']['message'] ?? 'Unknown error';
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// Extract text (NEW FORMAT)
$text = $data['output'][0]['content'][0]['text'] ?? null;

if (!$text) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Invalid response structure']);
    exit;
}

// Success
echo json_encode([
    'success' => true,
    'response' => $text
]);

