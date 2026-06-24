<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$apiKey    = getenv('CLOUDFLARE_API_KEY');
$accountId = getenv('CLOUDFLARE_ACCOUNT_ID');

if (!$apiKey || !$accountId) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => 'Missing environment variables'
    ]);

    exit;
}

$url = "https://api.cloudflare.com/client/v4/accounts/$accountId/cfd_tunnel";

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => curl_error($ch)
    ]);

    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($httpCode !== 200) {

    http_response_code($httpCode);

    echo json_encode([
        'success' => false,
        'error'   => 'Cloudflare API returned HTTP ' . $httpCode,
        'raw'     => $response
    ]);

    exit;
}

echo $response;
