# EdgeRouter X → Sentinel

The ERX runs OpenWrt (25.12), so it uses the same read-only CGI as RouterPi:
[`deploy/routerpi/sentinel-stats`](../routerpi/sentinel-stats), with the UPS and LTE parts turned off.

```
Sentinel (chat.php) ──MCP──> Worker "ai" (mcp.ettzz.pro)
                                 │  get-erx-{status,network,clients,logs}
                                 ▼  CF-Access-Client-Id/Secret (service token "Sentinel-ERX")
                                 ▼  Authorization: Bearer <ERX_KEY>
                     https://erx.ettzz.app/cgi-bin/sentinel-stats
                                 │  cloudflared on the ERX → localhost:80 (uhttpd)
                                 ▼
                     /proc, ubus, logread, ip
```

`erx.ettzz.app` is a placeholder. If you use another hostname, set the Worker variable `ERX_URL` to the full URL.

OpenWrt 25.12 uses `apk`, not `opkg`.

## 1. Install the script (on the ERX)

```sh
scp -O deploy/routerpi/sentinel-stats root@<erx>:/www/cgi-bin/sentinel-stats
```

```sh
chmod 755 /www/cgi-bin/sentinel-stats
mkdir -p /etc/sentinel
head -c 32 /dev/urandom | hexdump -e '32/1 "%02x"' > /etc/sentinel/key
chmod 600 /etc/sentinel/key
cat /etc/sentinel/key          # for ERX_KEY

# the WAN device whose traffic "status" reports (pppoe-wan for PPPoE)
ubus call network.interface.wan status | jsonfilter -e '@.l3_device'

cat > /etc/sentinel/config <<'EOF'
UPS=0
LTE=0
WWAN_IF=<device from the command above>
EOF

echo /www/cgi-bin/sentinel-stats >> /etc/sysupgrade.conf
echo /etc/sentinel/ >> /etc/sysupgrade.conf

HTTP_AUTHORIZATION="Bearer $(cat /etc/sentinel/key)" QUERY_STRING=view=network /www/cgi-bin/sentinel-stats
```

The ERX writes nothing to flash on its own: it has no history cron, which keeps NAND wear down.

## 2. Tunnel

In Zero Trust → Networks → Tunnels, create a tunnel `HOME | ERX` (cloudflared connector), then on the ERX:

```sh
apk update && apk add cloudflared
uci set cloudflared.config.token='<tunnel token>'
uci set cloudflared.config.enabled='1'
uci commit cloudflared
/etc/init.d/cloudflared enable && /etc/init.d/cloudflared restart
```

Public hostname: `erx.ettzz.app`, **path `cgi-bin/sentinel-stats`**, service `http://localhost:80`.
The path restriction keeps LuCI off the internet. Add a second hostname with an email-login Access app only if
you want LuCI remotely.

cloudflared needs about 40 MB of RAM. The ERX's 256 MB is enough, but check `free` after a day.

If another always-on machine at home already runs cloudflared, you can skip installing it on the ERX. Point a public
hostname on that tunnel at `http://<erx-lan-ip>:80` with the same path instead.

## 3. Access

| Access app | Domain | Policy |
| --- | --- | --- |
| ERX Sentinel Stats | `erx.ettzz.app/cgi-bin/sentinel-stats` | Service Auth: new token `Sentinel-ERX` |

## 4. Worker secrets

From `deploy/worker-ai/`:

```sh
npx wrangler secret put ERX_KEY
npx wrangler secret put ERX_ACCESS_ID
npx wrangler secret put ERX_ACCESS_SECRET
npx wrangler deploy
```

If `MCP_ALLOW` is set in the VirtualHost, add the `get-erx-*` tool names to it.

Troubleshooting is the same as for RouterPi (see its README). `EdgeRouter X tunnel is offline` means cloudflared on
the ERX is down or the home connection is out.
