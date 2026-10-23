<?php
// test_mcp.php
require_once __DIR__ . '/mcp_client.php';

echo "<h2>Testing Cloudflare MCP Router connection...</h2>";

$mcp = new McpClient();

echo "<strong>Sending 'tools/list' request to mcp.ettzz.pro/mcp...</strong><br>";
$result = $mcp->listTools();

echo "<pre>";
print_r($result);
echo "</pre>";
