<?php
declare(strict_types=1);

/**
 *   MCP_URL     optional, defaults to https://mcp.ettzz.pro/mcp
 *   MCP_ALLOW   optional, comma list of EXACT tool names Sentinel may use.
 *               If set, it replaces the automatic read-only filter.
 */
class McpClient
{
    private string $targetUrl;
    private array $authHeaders;
    private ?string $sessionId = null;
    private bool $initialized = false;
    private int $nextId = 0;
    public ?string $lastError = null;

    // Tool names must START with one of these to be considered read-only...
    private const READ_VERBS = [
        'get', 'list', 'show', 'read', 'status', 'check', 'view',
        'info', 'ping', 'query', 'find', 'search', 'describe', 'fetch',
    ];
    // ...and must not CONTAIN any of these words anywhere in the name.
    private const DENY_TOKENS = [
        'reboot', 'restart', 'reset', 'set', 'write', 'delete', 'remove', 'add',
        'create', 'edit', 'update', 'exec', 'execute', 'run', 'shell', 'command',
        'cmd', 'kill', 'enable', 'disable', 'apply', 'commit', 'flash', 'upgrade',
        'install', 'block', 'unblock', 'kick', 'disconnect', 'wipe', 'push', 'send',
    ];

    public static function env(string $key): string
    {
        $v = $_SERVER[$key] ?? $_SERVER['REDIRECT_' . $key] ?? $_ENV[$key] ?? getenv($key);
        return is_string($v) ? $v : '';
    }

    public function __construct(?string $url = null)
    {
        $this->targetUrl = $url ?: (self::env('MCP_URL') ?: 'https://mcp.ettzz.pro/mcp');
        $this->authHeaders = [
            'CF-Access-Client-Id: '     . self::env('CF_ACCESS_CLIENT_ID'),
            'CF-Access-Client-Secret: ' . self::env('CF_ACCESS_CLIENT_SECRET'),
        ];
    }

    // ---------- low level ----------

    private function err(int $code, string $msg): array
    {
        return ['error' => ['code' => $code, 'message' => $msg]];
    }

    /** One HTTP round trip. Always returns a JSON-RPC style array (result or error). */
    private function send(string $method, $params, bool $notify = false): array
    {
        $payload = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params];
        if (!$notify) $payload['id'] = ++$this->nextId;

        $headers = array_merge($this->authHeaders, [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
        ]);
        if ($this->sessionId) $headers[] = 'Mcp-Session-Id: ' . $this->sessionId;

        $respHeaders = [];
        $ch = curl_init($this->targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$respHeaders) {
                $p = explode(':', $line, 2);
                if (count($p) === 2) $respHeaders[strtolower(trim($p[0]))] = trim($p[1]);
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($body === false) return $this->err(-1, "Connection error: $cerr");

        if (isset($respHeaders['mcp-session-id'])) $this->sessionId = $respHeaders['mcp-session-id'];

        if ($code >= 400) {
            $hint = in_array($code, [401, 403], true)
                ? ' (check CF_ACCESS_CLIENT_ID / CF_ACCESS_CLIENT_SECRET and the Access policy)' : '';
            return $this->err($code, "HTTP $code$hint: " . substr(strip_tags((string)$body), 0, 200));
        }

        if ($notify) return [];

        // Server may answer with plain JSON or a Server-Sent Events stream
        $json  = $body;
        $ctype = $respHeaders['content-type'] ?? '';
        if (stripos($ctype, 'text/event-stream') !== false || preg_match('/^\s*(event|data):/m', (string)$body)) {
            $json = null;
            foreach (preg_split('/\R/', (string)$body) as $line) {
                if (strpos($line, 'data:') === 0) {
                    $cand = trim(substr($line, 5));
                    $d = json_decode($cand, true);
                    if (is_array($d) && (isset($d['result']) || isset($d['error']))) $json = $cand;
                }
            }
        }

        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) return $this->err(-2, 'Malformed response: ' . substr((string)$body, 0, 200));
        return $decoded;
    }

    private function initialize(): void
    {
        $this->initialized = true;
        $this->send('initialize', [
            'protocolVersion' => '2025-03-26',
            'capabilities'    => new stdClass,
            'clientInfo'      => ['name' => 'sentinel-ai', 'version' => '1.0'],
        ]);
        $this->send('notifications/initialized', new stdClass, true);
    }

    // --- REPLACE THIS FUNCTION IN mcp_client.php ---
	/** Request; if the server demands a session/initialize handshake, do it once and retry. */
		private function call(string $method, $params): array
		{
    // Proactively initialize if we haven't done so yet during this script execution
    if (!$this->initialized && $method !== 'initialize' && $method !== 'notifications/initialized') {
        $this->initialize();
    }

    $r = $this->send($method, $params);
    
    // Fallback error check loop remains as a safety net
    if (isset($r['error']) && !$this->initialized
        && preg_match('/session|initiali[sz]/i', (string)($r['error']['message'] ?? ''))) {
        $this->initialize();
        $r = $this->send($method, $params);
    }
    return $r;
}

    // ---------- tools ----------

    public function listTools(): array
    {
        $r = $this->call('tools/list', new stdClass);
        // Your custom Worker once used a non-standard method name; keep the fallback, silently
        if (isset($r['error']) && (int)($r['error']['code'] ?? 0) === -32601) {
            $r = $this->call('listTools', new stdClass);
        }
        if (isset($r['error'])) {
            $this->lastError = (string)($r['error']['message'] ?? 'unknown error');
            return [];
        }
        return $r['result']['tools'] ?? [];
    }

    /** Tool list cached for 5 minutes so every chat message does not hit the router. */
    private function cachedTools(int $ttl = 300): array
    {
        $file = sys_get_temp_dir() . '/sentinel_mcp_tools.json';
        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $c = json_decode((string)file_get_contents($file), true);
            if (is_array($c) && $c) return $c;
        }
        $tools = $this->listTools();
        if ($tools) @file_put_contents($file, json_encode($tools));
        return $tools;
    }

    public function callTool(string $name, array $args = []): array
    {
        $r = $this->call('tools/call', ['name' => $name, 'arguments' => $args ?: new stdClass]);
        if (isset($r['error'])) {
            return ['ok' => false, 'text' => 'MCP error: ' . ($r['error']['message'] ?? 'unknown')];
        }
        $res  = $r['result'] ?? [];
        $text = '';
        foreach ($res['content'] ?? [] as $c) {
            if (($c['type'] ?? '') === 'text') $text .= $c['text'] . "\n";
        }
        if ($text === '' && isset($res['structuredContent'])) $text = json_encode($res['structuredContent']);
        if ($text === '') $text = json_encode($res);
        $text = trim($text);
        if (strlen($text) > 8000) $text = substr($text, 0, 8000) . "\n...[truncated]";
        return ['ok' => empty($res['isError']), 'text' => $text];
    }

    private function isReadOnly(string $name): bool
    {
        $allow = trim(self::env('MCP_ALLOW'));
        if ($allow !== '') {
            return in_array($name, array_map('trim', explode(',', $allow)), true);
        }
        $spaced = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name);
        $tokens = preg_split('/[^a-z0-9]+/', strtolower((string)$spaced), -1, PREG_SPLIT_NO_EMPTY);
        if (!$tokens || !in_array($tokens[0], self::READ_VERBS, true)) return false;
        return !array_intersect($tokens, self::DENY_TOKENS);
    }

    /**
     * Returns the read-only tools in Anthropic format, plus a name map and debug info.
     * Tools are exposed to Claude as owrt_<name>.
     */
    public function anthropicTools(): array
    {
        $tools = []; $map = []; $loaded = []; $blocked = [];

        foreach ($this->cachedTools() as $t) {
            $name = (string)($t['name'] ?? '');
            if ($name === '') continue;
            if (!$this->isReadOnly($name)) { $blocked[] = $name; continue; }

            $safe   = 'owrt_' . substr((string)preg_replace('/[^a-zA-Z0-9_-]/', '_', $name), 0, 58);
            $schema = $t['inputSchema'] ?? [];
            if (!is_array($schema) || ($schema['type'] ?? '') !== 'object') $schema = ['type' => 'object'];
            unset($schema['$schema']);
            if (empty($schema['properties'])) $schema['properties'] = new stdClass;

            $tools[] = [
                'name'         => $safe,
                'description'  => substr('OpenWrt gateway: ' . trim((string)($t['description'] ?? $name)), 0, 1000),
                'input_schema' => $schema,
            ];
            $map[$safe] = $name;
            $loaded[]   = $name;
        }

        return ['tools' => $tools, 'map' => $map, 'loaded' => $loaded, 'blocked' => $blocked, 'error' => $this->lastError];
    }
}
