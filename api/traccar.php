<?php
require_once __DIR__ . '/../config/logger.php';

header('Content-Type: application/json');

$endpoint = $_GET['endpoint'] ?? '';

$traccarUrl = "http://YOUR_TRACCAR/api/" . $endpoint;

$ch = curl_init($traccarUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "user:pass");

$response = curl_exec($ch);
if ($response === false) {
    log_error('Traccar request failed: ' . curl_error($ch), ['endpoint' => $endpoint]);
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Traccar unreachable']);
    exit;
}
echo $response;
