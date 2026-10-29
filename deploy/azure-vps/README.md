# Azure VPS → Sentinel

```
Sentinel (chat.php) ──MCP──> Worker "ai" (mcp.ettzz.pro)
                                 │  get-azure-vps-health
                                 ▼  CF-Access-Client-Id/Secret (service token "Sentinel-AzureVPS")
                                 ▼  Authorization: Bearer <AZURE_KEY>
                     https://vps-health.ettzz.app/
                                 │  cloudflared on the VPS → 127.0.0.1:9077
                                 ▼
          sentinel-health.py (systemd, DynamicUser, read-only filesystem)
```

`vps-health.ettzz.app` is a placeholder. If you use another hostname, set the Worker variable `AZURE_URL`.

The endpoint reports CPU, load, memory and swap, disk usage per real filesystem, and uptime. It also reports the
units in `SENTINEL_SERVICES` and any failed units, listening ports (with no process names, because that would need
root), pending and security updates from `update-notifier`, and `/run/reboot-required`.
It uses the Python standard library only, and it runs as a throwaway unprivileged user that can't write to the system.

## 1. Install (on the VPS)

```sh
sudo install -D -m 755 sentinel-health.py /opt/sentinel/sentinel-health.py
sudo install -m 644 sentinel-health.service /etc/systemd/system/
sudo install -d -m 700 /etc/sentinel
head -c 32 /dev/urandom | xxd -p -c 64 | sudo tee /etc/sentinel/key >/dev/null
sudo chmod 600 /etc/sentinel/key
sudo cat /etc/sentinel/key        # for AZURE_KEY
```

Edit `SENTINEL_SERVICES` in the unit for what the VPS runs, e.g. `ssh.socket cloudflared nginx docker`, then:

```sh
sudo systemctl daemon-reload
sudo systemctl enable --now sentinel-health
curl -s -H "Authorization: Bearer $(sudo cat /etc/sentinel/key)" http://127.0.0.1:9077/ | python3 -m json.tool
```

## 2. Tunnel and Access

On the VPS's existing cloudflared tunnel, add a public hostname `vps-health.ettzz.app` → `http://127.0.0.1:9077`
(dashboard-managed tunnel), or for a locally-configured tunnel add this to the ingress in `config.yml`:

```yaml
  - hostname: vps-health.ettzz.app
    service: http://127.0.0.1:9077
```

| Access app | Domain | Policy |
| --- | --- | --- |
| VPS Sentinel Health | `vps-health.ettzz.app` | Service Auth: new token `Sentinel-AzureVPS` |

WARP isn't involved: the Worker reaches the VPS through the tunnel's public hostname.

## 3. Worker secrets

From `deploy/worker-ai/`:

```sh
npx wrangler secret put AZURE_KEY
npx wrangler secret put AZURE_ACCESS_ID
npx wrangler secret put AZURE_ACCESS_SECRET
npx wrangler deploy
```

If `MCP_ALLOW` is set in the VirtualHost, add `get-azure-vps-health`.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `the tunnel is up but the local service did not answer (HTTP 502)` | `sentinel-health` not running: `journalctl -u sentinel-health` |
| `401 unauthorized` | `AZURE_KEY` doesn't match `/etc/sentinel/key` (restart the service after changing the key) |
| `updates` has no `pending` | `update-notifier-common` not installed (minimal images) |
| A service shows `installed: false` | unit name wrong in `SENTINEL_SERVICES` (`systemctl list-units --type=service`) |
