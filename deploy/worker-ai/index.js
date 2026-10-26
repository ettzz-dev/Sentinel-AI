// Sentinel MCP server: Worker "ai" (mcp.ettzz.pro, ai.ettzz.pro)
// Secrets: CF_ACCOUNT_ID, CF_API_TOKEN, ROUTERPI_KEY,
//          ROUTERPI_ACCESS_ID / ROUTERPI_ACCESS_SECRET (Access service token "Sentinel-RouterPi")
// Optional var: ROUTERPI_URL

const TOOLS = [
  { name: "test-tool", description: "Connectivity test; returns a fixed message.", inputSchema: { type: "object", properties: {} } },
  { name: "get-system-health", description: "Get the Worker's online status and region.", inputSchema: { type: "object", properties: {} } },
  { name: "whoami", description: "Identify this MCP server and its version.", inputSchema: { type: "object", properties: {} } },
  { name: "check-tunnels", description: "List every Cloudflare tunnel with its name and live status (healthy, degraded, down, inactive).", inputSchema: { type: "object", properties: {} } },
  {
    name: "get-routerpi-status",
    description:
      "Live snapshot of RouterPi, the OpenWrt Raspberry Pi in the car: CPU usage, load, memory, CPU temperature, overlay storage, uptime, " +
      "UPS HAT (D) battery voltage/current/percent/charging state, SIM7600 LTE signal (RSRP dBm, RSRQ dB, SINR dB, RSSI, band, operator, cell), " +
      "WWAN traffic, and whether the modem's built-in GNSS is off. Read-only. Fails if the car/Pi is offline.",
    inputSchema: { type: "object", properties: {} },
  },
];

const HANDLERS = {
  "test-tool": async () => "MCP is working 🚀",
  "get-system-health": async () =>
    JSON.stringify({ time: new Date().toISOString(), status: "online", region: "Cloudflare Edge" }, null, 2),
  "whoami": async () => "I am Sentinel MCP v1.1",
  "check-tunnels": async (env) => {
    if (!env.CF_ACCOUNT_ID || !env.CF_API_TOKEN) {
      return "Worker is missing the CF_ACCOUNT_ID or CF_API_TOKEN secret.";
    }
    const res = await fetch(
      `https://api.cloudflare.com/client/v4/accounts/${env.CF_ACCOUNT_ID}/cfd_tunnel?is_deleted=false`,
      { headers: { Authorization: `Bearer ${env.CF_API_TOKEN}` } }
    );
    const data = await res.json();
    if (!data.success) return "Cloudflare API error: " + JSON.stringify(data.errors);
    return JSON.stringify(data.result.map((t) => ({ name: t.name, status: t.status })), null, 2);
  },
  "get-routerpi-status": async (env) => {
    if (!env.ROUTERPI_KEY) throw new Error("Worker is missing the ROUTERPI_KEY secret.");
    if (!env.ROUTERPI_ACCESS_ID || !env.ROUTERPI_ACCESS_SECRET) {
      throw new Error("Worker is missing the ROUTERPI_ACCESS_ID / ROUTERPI_ACCESS_SECRET secrets.");
    }
    const url = env.ROUTERPI_URL || "https://rpi.ettzz.app/cgi-bin/sentinel-stats";
    let res;
    try {
      res = await fetch(url, {
        headers: {
          Authorization: `Bearer ${env.ROUTERPI_KEY}`,
          "CF-Access-Client-Id": env.ROUTERPI_ACCESS_ID,
          "CF-Access-Client-Secret": env.ROUTERPI_ACCESS_SECRET,
        },
        signal: AbortSignal.timeout(15000),
      });
    } catch (e) {
      throw new Error(e?.name === "TimeoutError" ? "RouterPi did not answer within 15s." : `RouterPi unreachable: ${e?.message || e}`);
    }
    const text = await res.text();
    // 530 = Cloudflare could not reach the tunnel (Pi off, or no LTE)
    if (res.status === 530) throw new Error("RouterPi tunnel is offline (the car may be off or out of coverage).");
    if (res.status === 403 && !text.trimStart().startsWith("{")) {
      throw new Error("Cloudflare Access rejected the Worker (check the Sentinel-RouterPi service token).");
    }
    if (!res.ok) throw new Error(`RouterPi returned HTTP ${res.status}: ${text.slice(0, 200)}`);
    try {
      return JSON.stringify(JSON.parse(text), null, 2);
    } catch {
      throw new Error("RouterPi returned non-JSON output: " + text.slice(0, 200));
    }
  },
};

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    if (url.pathname !== "/mcp") return new Response("Worker alive 🚀");
    if (request.method !== "POST") return new Response("MCP endpoint requires POST", { status: 405 });

    let body;
    try {
      body = await request.json();
    } catch {
      return Response.json({ jsonrpc: "2.0", id: null, error: { code: -32700, message: "Parse error" } }, { status: 400 });
    }

    const id = body.id ?? null;
    const ok = (result) => Response.json({ jsonrpc: "2.0", id, result });
    const fail = (code, message) => Response.json({ jsonrpc: "2.0", id, error: { code, message } });

    switch (body.method) {
      case "initialize":
        return ok({
          protocolVersion: "2025-03-26",
          capabilities: { tools: {} },
          serverInfo: { name: "sentinel-mcp", version: "1.1" },
        });
      case "notifications/initialized":
        return new Response(null, { status: 202 });
      case "ping":
        return ok({});
      case "tools/list":
        return ok({ tools: TOOLS });
      case "tools/call": {
        const name = body.params?.name;
        const handler = HANDLERS[name];
        if (!handler) return fail(-32602, `Unknown tool: ${name}`);
        try {
          return ok({ content: [{ type: "text", text: await handler(env) }] });
        } catch (e) {
          return ok({ content: [{ type: "text", text: "Tool failed: " + (e?.message || e) }], isError: true });
        }
      }
      default:
        return fail(-32601, "Method not found");
    }
  },
};
