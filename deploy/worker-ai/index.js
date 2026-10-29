// Sentinel MCP server: Worker "ai" (mcp.ettzz.pro, ai.ettzz.pro)
// Secrets: CF_ACCOUNT_ID, CF_API_TOKEN
// Devices: each sits behind a Cloudflare tunnel with an Access app (service token) and checks a Bearer key itself.
//   RouterPi   ROUTERPI_KEY, ROUTERPI_ACCESS_ID / ROUTERPI_ACCESS_SECRET (token "Sentinel-RouterPi"), optional ROUTERPI_URL
//   ERX        ERX_KEY, ERX_ACCESS_ID / ERX_ACCESS_SECRET (token "Sentinel-ERX"), optional ERX_URL
//   Azure VPS  AZURE_KEY, AZURE_ACCESS_ID / AZURE_ACCESS_SECRET (token "Sentinel-AzureVPS"), optional AZURE_URL
// Phone MCP (Galaxy, Termux tunnel): secret PHONE_MCP_TOKEN (Bearer token from the Phone MCP app);
//          optional PHONE_MCP_URL, PHONE_ACCESS_ID / PHONE_ACCESS_SECRET if Access is put in front of it.
// The phone's tools are re-exported live as "phone-<name>", so enabling a feature in the app is enough.

const PHONE_PREFIX = "phone-";

const DEVICES = {
  routerpi: {
    label: "RouterPi",
    env: "ROUTERPI",
    token: "Sentinel-RouterPi",
    url: "https://rpi.ettzz.app/cgi-bin/sentinel-stats",
    offline: "RouterPi tunnel is offline (the car may be off or out of coverage).",
  },
  erx: {
    label: "EdgeRouter X",
    env: "ERX",
    token: "Sentinel-ERX",
    url: "https://erx.ettzz.app/cgi-bin/sentinel-stats",
    offline: "EdgeRouter X tunnel is offline (cloudflared on the router is down, or its internet connection is).",
  },
  azure: {
    label: "Azure VPS",
    env: "AZURE",
    token: "Sentinel-AzureVPS",
    url: "https://vps-health.ettzz.app/",
    offline: "Azure VPS tunnel is offline (the VM or its cloudflared is down).",
  },
};

async function queryDevice(env, id, params = {}, timeoutMs = 15000) {
  const d = DEVICES[id];
  const key = env[`${d.env}_KEY`];
  const accessId = env[`${d.env}_ACCESS_ID`];
  const accessSecret = env[`${d.env}_ACCESS_SECRET`];
  if (!key) throw new Error(`Worker is missing the ${d.env}_KEY secret.`);
  if (!accessId || !accessSecret) {
    throw new Error(`Worker is missing the ${d.env}_ACCESS_ID / ${d.env}_ACCESS_SECRET secrets.`);
  }
  const url = new URL(env[`${d.env}_URL`] || d.url);
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== "") url.searchParams.set(k, String(v));
  }
  let res;
  try {
    res = await fetch(url, {
      headers: {
        Authorization: `Bearer ${key}`,
        "CF-Access-Client-Id": accessId,
        "CF-Access-Client-Secret": accessSecret,
      },
      signal: AbortSignal.timeout(timeoutMs),
    });
  } catch (e) {
    throw new Error(e?.name === "TimeoutError" ? `${d.label} did not answer within ${timeoutMs / 1000}s.` : `${d.label} unreachable: ${e?.message || e}`);
  }
  const text = await res.text();
  // 530 = Cloudflare could not reach the tunnel; 502 = tunnel up, local service down
  if (res.status === 530) throw new Error(d.offline);
  if (res.status === 502) throw new Error(`${d.label}: the tunnel is up but the local service did not answer (HTTP 502).`);
  if (res.status === 403 && !text.trimStart().startsWith("{")) {
    throw new Error(`Cloudflare Access rejected the Worker (check the ${d.token} service token).`);
  }
  if (!res.ok) throw new Error(`${d.label} returned HTTP ${res.status}: ${text.slice(0, 200)}`);
  try {
    // Compact: Sentinel's MCP client truncates long results
    return JSON.stringify(JSON.parse(text));
  } catch {
    throw new Error(`${d.label} returned non-JSON output: ` + text.slice(0, 200));
  }
}

const NO_ARGS = { type: "object", properties: {} };

const clampInt = (v, def, min, max) => {
  const n = Number.parseInt(v, 10);
  return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : def;
};

const LOG_ARGS = {
  type: "object",
  properties: {
    lines: { type: "integer", minimum: 1, maximum: 100, description: "How many of the most recent matching lines to return (default 40)." },
    filter: {
      type: "string",
      description: 'Only lines containing this text, case-insensitive, e.g. "cloudflared", "dnsmasq", "wwan", "kernel". Letters, digits, space and . _ : / - only.',
    },
  },
};
const logParams = (a) => ({
  view: "logs",
  lines: clampInt(a.lines, 40, 1, 100),
  filter: typeof a.filter === "string" ? a.filter.replace(/[^A-Za-z0-9 ._:\/-]/g, "").slice(0, 64) : "",
});

// The same sentinel-stats views on every OpenWrt box
const openwrtTools = (id, who) => [
  {
    name: `get-${id}-network`,
    device: id,
    params: () => ({ view: "network" }),
    description:
      `${who}: logical interfaces (up/down, protocol, device, IPv4, gateway, DNS, uptime), every network device with ` +
      "link speed, byte totals and current rx/tx bits per second, the IPv4 routing table and the default route (WAN state). Read-only.",
  },
  {
    name: `get-${id}-clients`,
    device: id,
    params: () => ({ view: "clients" }),
    description:
      `${who}: connected devices: DHCP leases (IP, MAC, hostname, time left, ARP state), all IPv4 neighbours including static-IP devices, ` +
      "and Wi-Fi stations with signal if the box has Wi-Fi. Read-only.",
  },
  {
    name: `get-${id}-logs`,
    device: id,
    inputSchema: LOG_ARGS,
    params: logParams,
    description: `${who}: recent system log lines (logread), oldest first, optionally filtered by text. Use it to diagnose problems. Read-only.`,
  },
];

const DEVICE_TOOLS = [
  {
    name: "get-routerpi-status",
    device: "routerpi",
    description:
      "Live snapshot of RouterPi, the OpenWrt Raspberry Pi in the car: CPU usage, load, memory, CPU temperature, overlay storage, uptime, " +
      "UPS HAT (D) battery voltage/current/percent/charging state, SIM7600 LTE signal (RSRP dBm, RSRQ dB, SINR dB, RSSI, band, operator, cell), " +
      "WWAN traffic, and whether the modem's built-in GNSS is off. Read-only. Fails if the car/Pi is offline.",
  },
  ...openwrtTools("routerpi", "RouterPi (OpenWrt Pi in the car, LTE uplink)"),
  {
    name: "get-routerpi-history",
    device: "routerpi",
    inputSchema: {
      type: "object",
      properties: { hours: { type: "integer", minimum: 1, maximum: 840, description: "How far back to look (default 24; the Pi keeps 35 days)." } },
    },
    params: (a) => ({ view: "history", hours: clampInt(a.hours, 24, 1, 840) }),
    description:
      "RouterPi history from 5-minute samples recorded on the Pi itself, so it covers times the tunnel was down: summary (reboots, samples with " +
      "internet down or not on LTE, RSRP min/avg/max, average SINR), up to 24 time buckets (RSRP avg/min, down samples, lowest battery %), and events: " +
      "reboots with the readings just before them, internet down/up transitions with the signal at that moment, and sampling gaps. " +
      "Use it to spot patterns, such as reboots tied to weak LTE or a flat battery. Read-only.",
  },
  {
    name: "get-routerpi-data-usage",
    device: "routerpi",
    params: () => ({ view: "usage" }),
    timeoutMs: 18000,
    description:
      "Vodacom data contract on RouterPi's SIM (60 GB anytime, 40 GB night): anytime and night usage this billing cycle counted from the Pi's " +
      "WWAN byte counters, estimated GB left, per-day usage for the last 7 days, and the operator's own balance message via USSD when configured " +
      "(cached for 30 minutes). Counter figures are estimates. Read-only.",
  },
  {
    name: "get-erx-status",
    device: "erx",
    description:
      "Live snapshot of the EdgeRouter X (OpenWrt router): CPU usage, load, memory, overlay storage, uptime and WAN traffic. " +
      "Read-only. Fails if the router or its tunnel is down.",
  },
  ...openwrtTools("erx", "EdgeRouter X (OpenWrt router)"),
  {
    name: "get-azure-vps-health",
    device: "azure",
    description:
      "Azure VPS (Ubuntu 24.04) health: CPU, load, memory, swap, disk usage per filesystem, uptime, watched systemd services and any failed units, " +
      "listening TCP/UDP ports (loopback or exposed on all interfaces), pending updates (and how many are security updates) and the reboot-required flag. Read-only.",
  },
];

const TOOLS = [
  { name: "test-tool", description: "Connectivity test; returns a fixed message.", inputSchema: NO_ARGS },
  { name: "get-system-health", description: "Get the Worker's online status and region.", inputSchema: NO_ARGS },
  { name: "whoami", description: "Identify this MCP server and its version.", inputSchema: NO_ARGS },
  { name: "check-tunnels", description: "List every Cloudflare tunnel with its name and live status (healthy, degraded, down, inactive).", inputSchema: NO_ARGS },
];

const HANDLERS = {
  "test-tool": async () => "MCP is working 🚀",
  "get-system-health": async () =>
    JSON.stringify({ time: new Date().toISOString(), status: "online", region: "Cloudflare Edge" }, null, 2),
  "whoami": async () => "I am Sentinel MCP v1.3",
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
};

for (const t of DEVICE_TOOLS) {
  TOOLS.push({ name: t.name, description: t.description, inputSchema: t.inputSchema || NO_ARGS });
  HANDLERS[t.name] = (env, args) => queryDevice(env, t.device, t.params ? t.params(args) : {}, t.timeoutMs);
}

// ---------- Phone MCP bridge (legacy SSE transport: GET /sse, then POST to the announced endpoint) ----------

async function phoneRequest(env, method, params, timeoutMs = 15000) {
  if (!env.PHONE_MCP_TOKEN) throw new Error("Worker is missing the PHONE_MCP_TOKEN secret.");
  const sseUrl = env.PHONE_MCP_URL || "https://galaxy-mcp.ettzz.pro/sse";
  const headers = { Authorization: `Bearer ${env.PHONE_MCP_TOKEN}` };
  if (env.PHONE_ACCESS_ID && env.PHONE_ACCESS_SECRET) {
    headers["CF-Access-Client-Id"] = env.PHONE_ACCESS_ID;
    headers["CF-Access-Client-Secret"] = env.PHONE_ACCESS_SECRET;
  }
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeoutMs);

  let reader;
  try {
    const res = await fetch(sseUrl, { headers: { ...headers, Accept: "text/event-stream" }, signal: ctrl.signal });
    if (res.status === 530 || res.status === 502) throw new Error("Phone tunnel is offline (phone asleep, Termux stopped, or no data).");
    if (res.status === 401) throw new Error("Phone MCP rejected the token (check PHONE_MCP_TOKEN against the app).");
    if (!res.ok || !res.body) throw new Error(`Phone MCP returned HTTP ${res.status}.`);
    reader = res.body.pipeThrough(new TextDecoderStream()).getReader();

    let buf = "";
    const nextEvent = async () => {
      for (;;) {
        const end = buf.indexOf("\n\n");
        if (end !== -1) {
          const raw = buf.slice(0, end);
          buf = buf.slice(end + 2);
          let event = "message", data = "";
          for (const line of raw.split("\n")) {
            if (line.startsWith("event:")) event = line.slice(6).trim();
            else if (line.startsWith("data:")) data += (data ? "\n" : "") + line.slice(5).trimStart();
          }
          if (data) return { event, data };
          continue; // comment / keep-alive
        }
        const { value, done } = await reader.read();
        if (done) throw new Error("Phone MCP closed the stream.");
        buf += value.replace(/\r\n?/g, "\n");
      }
    };

    let ev;
    do ev = await nextEvent(); while (ev.event !== "endpoint");
    const postUrl = new URL(ev.data, sseUrl).toString();

    let nextId = 0;
    const post = async (payload) => {
      const r = await fetch(postUrl, {
        method: "POST",
        headers: { ...headers, "Content-Type": "application/json" },
        body: JSON.stringify(payload),
        signal: ctrl.signal,
      });
      if (!r.ok) throw new Error(`Phone MCP POST ${payload.method} failed: HTTP ${r.status}`);
    };
    // Responses arrive on the SSE stream, not in the POST reply
    const rpc = async (m, p) => {
      const id = ++nextId;
      await post({ jsonrpc: "2.0", id, method: m, params: p });
      for (;;) {
        const e = await nextEvent();
        if (e.event !== "message") continue;
        let msg;
        try { msg = JSON.parse(e.data); } catch { continue; }
        if (msg.id !== id) continue;
        if (msg.error) throw new Error(`Phone MCP: ${msg.error.message || JSON.stringify(msg.error)}`);
        return msg.result;
      }
    };

    await rpc("initialize", {
      protocolVersion: "2024-11-05",
      capabilities: {},
      clientInfo: { name: "sentinel-mcp-bridge", version: "1.2" },
    });
    await post({ jsonrpc: "2.0", method: "notifications/initialized" });
    return await rpc(method, params);
  } catch (e) {
    if (e?.name === "AbortError") throw new Error(`Phone MCP did not answer within ${timeoutMs / 1000}s.`);
    throw e;
  } finally {
    clearTimeout(timer);
    reader?.cancel().catch(() => {});
    ctrl.abort();
  }
}

async function phoneTools(env) {
  if (!env.PHONE_MCP_TOKEN) return [];
  try {
    const result = await phoneRequest(env, "tools/list", {}, 8000);
    return (result?.tools || []).map((t) => ({
      ...t,
      name: PHONE_PREFIX + t.name,
      description: "Galaxy phone: " + (t.description || t.name),
    }));
  } catch (e) {
    // Phone offline must not take the other tools down with it
    console.error("phone tools/list failed:", e?.message || e);
    return [];
  }
}

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
          serverInfo: { name: "sentinel-mcp", version: "1.3" },
        });
      case "notifications/initialized":
        return new Response(null, { status: 202 });
      case "ping":
        return ok({});
      case "tools/list":
        return ok({ tools: [...TOOLS, ...(await phoneTools(env))] });
      case "tools/call": {
        const name = body.params?.name;
        if (typeof name === "string" && name.startsWith(PHONE_PREFIX)) {
          try {
            const result = await phoneRequest(env, "tools/call", {
              name: name.slice(PHONE_PREFIX.length),
              arguments: body.params?.arguments ?? {},
            });
            return ok(result);
          } catch (e) {
            return ok({ content: [{ type: "text", text: "Tool failed: " + (e?.message || e) }], isError: true });
          }
        }
        const handler = HANDLERS[name];
        if (!handler) return fail(-32602, `Unknown tool: ${name}`);
        const args = body.params?.arguments;
        try {
          return ok({ content: [{ type: "text", text: await handler(env, args && typeof args === "object" ? args : {}) }] });
        } catch (e) {
          return ok({ content: [{ type: "text", text: "Tool failed: " + (e?.message || e) }], isError: true });
        }
      }
      default:
        return fail(-32601, "Method not found");
    }
  },
};
