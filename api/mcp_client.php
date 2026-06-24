<?php

// 1. Safely retrieve the Zero Trust secrets from your Apache Virtual Host
$cfClientId     = getenv('CF_ACCESS_CLIENT_ID'); 
$cfClientSecret = getenv('CF_ACCESS_CLIENT_SECRET');

// 2. Set up the target URL of your Cloudflare-protected MCP server/endpoint
$targetUrl = 'https://mcp.ettzz.dev/mcp';

// 3. Build the HTTP headers array with your Service Token credentials
$headers = [
    "CF-Access-Client-Id: " . $cfClientId,
    "CF-Access-Client-Secret: " . $cfClientSecret,
    "Content-Type: application/json",
    "Accept: application/json"
];

// Example payload structure you might be sending to the target endpoint
$payload = [
    "jsonrpc" => "2.0",
    "method" => "tools/list", // standard MCP method structure
    "params" => new stdClass(),
    "id" => 1
];

// 4. Execute the authenticated outbound cURL request
$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    // Handle cURL connection failures here
}
curl_close($ch);

function callMcpTool($tool, $args = [])
{
    $payload = [
        "jsonrpc" => "2.0",
        "id" => uniqid(),
        "method" => "tools/call",
        "params" => [
            "name" => $tool,
            "arguments" => $args
        ]
    ];

    $ch = curl_init("https://mcp.ettzz.dev/mcp");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            "error" => "cURL Error",
            "message" => $error
        ];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($decoded === null) {
        return [
            "error" => "Invalid JSON",
            "raw_response" => $response
        ];
    }

    return $decoded;
}