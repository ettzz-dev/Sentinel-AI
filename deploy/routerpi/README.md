# RouterPi → Sentinel

```
Sentinel (chat.php) ──MCP──> Worker "ai" (mcp.ettzz.pro)
                                 │  get-routerpi-{status,network,clients,logs,history,data-usage}
                                 ▼  CF-Access-Client-Id/Secret (service token "Sentinel-RouterPi")
                                 ▼  Authorization: Bearer <ROUTERPI_KEY>
                     https://rpi.ettzz.app/cgi-bin/sentinel-stats
                                 │  tunnel "CAR | RouterPi" → localhost:80 (uhttpd)
                                 ▼
          /proc, INA219 @ i2c-1 0x43 (UPS HAT D), SIM7600 AT port (ttyUSB2)
```

Cloudflare Access on `rpi.ettzz.app`:

| Access app | Domain | Policy |
| --- | --- | --- |
| RouterPi LuCI | `rpi.ettzz.app` | Allow: ettzz@pm.me (login) |
| RouterPi Sentinel Stats | `rpi.ettzz.app/cgi-bin/sentinel-stats` | Service Auth: token `Sentinel-RouterPi` (expires 2027-10-26) |

The token's ID and secret are stored as the `ai` Worker secrets `ROUTERPI_ACCESS_ID` / `ROUTERPI_ACCESS_SECRET`.
When it expires, rotate it in Zero Trust → Access → Service credentials and update those two secrets.

The same script also runs on the EdgeRouter X (see `deploy/edgerouter/README.md`). The Access app matches the path,
so the `?view=` query string needs no extra Access setup.

| Worker tool | `?view=` | What it returns |
| --- | --- | --- |
| `get-routerpi-status` | `status` (default) | CPU, memory, temperature, storage, UPS battery, LTE signal, WWAN |
| `get-routerpi-network` | `network` | interfaces, per-device traffic and current bit/s, routes, default route |
| `get-routerpi-clients` | `clients` | DHCP leases, ARP neighbours, Wi-Fi stations |
| `get-routerpi-logs` | `logs&lines=N&filter=TEXT` | recent `logread` lines (max 100) |
| `get-routerpi-dmesg` | `dmesg&lines=N&filter=TEXT` | recent kernel messages, stamps converted to local time (max 100) |
| `get-routerpi-history` | `history&hours=N` | reboots, outages, signal trends from the 5-minute samples (step 5) |
| `get-routerpi-data-usage` | `usage` | Vodacom anytime/night usage this cycle, last 7 days, optional USSD balance (step 6) |

The script is read-only: it only reads `/proc`/`/sys`, `ubus` status calls, `logread`, reads INA219 registers (no writes),
and sends AT *query* commands (`AT+CSQ`, `AT+CPSI?`, `AT+COPS?`, `AT+CGPS?`). The only things it writes are its own
history file (cron) and the USSD balance cache in `/tmp`. If `BALANCE_USSD` is set, it also sends that one fixed USSD code.

## 1. Packages and I2C (on the Pi)

```sh
opkg update
opkg install i2c-tools kmod-i2c-bcm2835 sms-tool kmod-usb-serial-option
```

Enable I2C in the boot partition's `config.txt` (mount `/dev/mmcblk0p1` on `/boot` if it is not mounted):

```
dtparam=i2c_arm=on
```

Reboot, then check that the UPS HAT (D) answers at `43`:

```sh
i2cdetect -y 1
```

## 2. Keep the LTE modem away from GPS

The SIM7600X has its own GNSS. The Waveshare GPS module should be the only thing that handles positioning.

1. **Turn off the modem's GNSS and stop it auto-starting** (one time; the setting persists in the modem):
   ```sh
   sms-tool -d /dev/ttyUSB2 at "AT+CGPS=0"      # ERROR just means it was already off
   sms-tool -d /dev/ttyUSB2 at "AT+CGPSAUTO=0"
   ```
   `get-routerpi-status` reports `lte.modem_gnss`; it should say `off`.
2. **Never point gpsd/ugps at the modem's NMEA port** (`/dev/ttyUSB1`). Point it at the Waveshare GPS module only:
   UART HAT → `/dev/ttyAMA0` (or `/dev/ttyS0`); USB module → `/dev/ttyACM0` or a `ttyUSB` from `cp210x`/`ch341`.
3. **SIM7600X HAT UART jumpers:** do **not** use position **B** (module UART on Pi GPIO14/15). That UART belongs
   to the GPS module. Use the USB connection for the modem (jumpers on **A**, or removed).
4. **Serial console:** if the GPS module is on the Pi UART, remove `console=serial0,115200` from `cmdline.txt` and the
   `ttyAMA0`/`ttyS0` `askconsole` line from `/etc/inittab`. Otherwise the login console fights the GPS for that UART.
5. **If the GPS module is USB-serial**, `ttyUSB` numbering can shift at boot. Check which ports belong to the modem
   (`dmesg | grep -i 'GSM modem'`) and set `AT_PORT` in `/etc/sentinel/config` if it is not `ttyUSB2`.
6. **If ModemManager or a 3ginfo LuCI page already polls `ttyUSB2`**, set `AT_PORT=/dev/ttyUSB3` (the SIM7600's second AT port)
   so the two don't interleave commands.
7. **RF:** keep the GPS antenna 20–30 cm or more from the LTE antennas. Leave the SIM7600 HAT's GNSS antenna port unconnected.

## 3. Install the script

Quickest: `scp -O deploy/routerpi/sentinel-stats deploy/routerpi/install.sh root@<pi>:/tmp/` then `ssh root@<pi> sh /tmp/install.sh routerpi`.
It backs up the old script, keeps the key/config, self-tests and prints modem/I2C diagnostics. Manual steps:

From this PC (the Pi's SSH goes through `rpissh.ettzz.app`, or use its LAN IP):

```sh
scp -O deploy/routerpi/sentinel-stats root@<pi>:/www/cgi-bin/sentinel-stats
```

On the Pi:

```sh
chmod 755 /www/cgi-bin/sentinel-stats
mkdir -p /etc/sentinel
head -c 32 /dev/urandom | hexdump -e '32/1 "%02x"' > /etc/sentinel/key
chmod 600 /etc/sentinel/key
cat /etc/sentinel/key          # copy this for step 4

# keep across sysupgrade
echo /www/cgi-bin/sentinel-stats >> /etc/sysupgrade.conf
echo /etc/sentinel/ >> /etc/sysupgrade.conf
```

Optional `/etc/sentinel/config` (shell syntax), only for values that differ from the defaults:

```sh
AT_PORT=/dev/ttyUSB3
WWAN_IF=wwan0        # check with: ip -br link
CURRENT_SIGN=-1      # if "charging" shows while on battery
```

Test it locally on the Pi:

```sh
HTTP_AUTHORIZATION="Bearer $(cat /etc/sentinel/key)" /www/cgi-bin/sentinel-stats
```

## 4. Worker

From `deploy/worker-ai/` on this PC:

```sh
npx wrangler deploy
npx wrangler secret put ROUTERPI_KEY     # paste the key from step 3
```

Then ask Sentinel "how's the car's battery and LTE signal?". Sentinel's MCP tool list is cached for 5 minutes.
If `MCP_ALLOW` is set in the VirtualHost, add the `get-routerpi-*` tool names to it.

## 5. History (reboots, outages, signal)

A cron job samples the Pi every 5 minutes into `/etc/sentinel/history.csv` (about 100 bytes per row, 35 days kept,
trimmed once a day). The file lives on the Pi, so the history also covers time when the tunnel was down.
Reboots show up as a change of boot ID, and the event includes the readings taken just before the reboot.

The Pi has no RTC, so it boots with a guessed time until NTP syncs over LTE. An NTP hotplug hook marks when the clock is
good, and rows taken before that are flagged (`clock_synced=0`).

```sh
# timezone, so day/hour buckets and the night window use SAST
uci set system.@system[0].zonename='Africa/Johannesburg'
uci set system.@system[0].timezone='SAST-2'
uci commit system && /etc/init.d/system reload

# NTP sync marker
mkdir -p /etc/hotplug.d/ntp
cat > /etc/hotplug.d/ntp/90-sentinel <<'EOF'
[ "$ACTION" = step ] || { [ "$ACTION" = stratum ] && [ "${stratum:-16}" -lt 16 ]; } && touch /var/run/sentinel-ntp-ok
EOF
echo /etc/hotplug.d/ntp/90-sentinel >> /etc/sysupgrade.conf

# sampler
echo '*/5 * * * * /www/cgi-bin/sentinel-stats --sample' >> /etc/crontabs/root
/etc/init.d/cron enable && /etc/init.d/cron restart
```

Check it after 10 minutes: `tail -n 2 /etc/sentinel/history.csv`. The last column should become `1` once NTP has synced.
Columns are listed in the script above `sample()`.

## 6. Data usage (Vodacom 60 GB anytime / 40 GB night)

`get-routerpi-data-usage` totals the `WWAN_IF` byte deltas from the history file. Usage between 00:00 and 05:00 counts
as night data, and the totals restart each cycle on `BILL_DAY`. These figures are estimates. Vodacom's own count can
differ a little, and data used between the last sample and a reboot is not counted.

For Vodacom's own figure, find a USSD code that **replies with the balance directly** (not a menu). Try it by hand:

```sh
sms-tool -d /dev/ttyUSB2 ussd '*135#'      # try the codes Vodacom documents; menus need replies and won't work
```

Then set it in `/etc/sentinel/config`. Adjust the contract values there too if they ever change:

```sh
BALANCE_USSD='<code that answered with the balance>'
BILL_DAY=1           # day the contract resets
ANYTIME_GB=60
NIGHT_GB=40
```

The reply is cached for 30 minutes (`BALANCE_CACHE_S`). If the modem can't do USSD on LTE (no CSFB/IMS USSD on that
cell), the tool still returns the counted figures with `operator_balance.error`.

## 7. RouterPi system settings (applied 2026-11-02)

Changes made on the Pi itself while fixing missing signal data, unexplained reboots and "internet down" history.
They live in the Pi's config, not in this repo. Backups: `/etc/sentinel/backup-20261102-2129/`
(`watchcat`, `lte-keepalive`, `network`, old `check-qmi-signal.sh`).

| Setting | Was | Now | Why |
| --- | --- | --- | --- |
| WARP MTU (`network.cloudflare.mtu`) | 1420 | 1280 | Full-size WireGuard packets exceeded the LTE path MTU and were dropped: SSH stalled, large replies hung |
| ModemManager | enabled | disabled | It probed `cdc-wdm0` while netifd's `uqmi` drove the same QMI port; `wwan0` stayed down with LTE "Online". It also left the modem in an invalid mode (`AT+CNMP: 1`) |
| `network.wwan.modes` | unset | `lte,umts,gsm` | netifd sets the allowed modes on every connect, so a stray modem mode can't lock out LTE |
| watchcat (6 jobs) | enabled | disabled | Reset `wwan` after 2–5 min (shorter than a cold LTE attach), rebuilt the network at 10/20 min (dropping WARP and the tunnel), and could reboot |
| `lte-keepalive` | restart 3, reboot 9 | restart 5, reboot 20 | Single watchdog: ~10 min to an interface restart, ~40 min to a reboot |
| `/usr/bin/check-qmi-signal.sh` | grep for `"rssi": -NN` | `jsonfilter -e @.rssi` | `uqmi` prints no space, so it never matched and emailed "modem unresponsive" every 6 h |
| Timezone | UTC | Africa/Johannesburg (SAST-2) | History buckets and the night-data window use local time |
| I2C (`/boot/config.txt`) | off | `dtparam=i2c_arm=on` | UPS HAT (D) INA219 at `i2c-1 0x43`; backup `/boot/config.txt.bak-sentinel` |
| `kmod-hwmon-raspberrypi` | not installed | installed | Exposes `rpi_volt` (`pi_undervoltage` in status/history) and logs "Undervoltage detected!" |
| Syslog (`system.log_file`) | RAM only, 128 KB | also `/root/syslog`, 512 KB + `.old` | Keeps the lead-up to a reboot |

BusyBox on this image has no `timeout` applet (`/usr/bin/timeout` is a dangling link) and no `flock -w`; `sentinel-stats`
works around both. From this PC, `ssh routerpi` needs `KexAlgorithms curve25519-sha256`: the default post-quantum key
exchange doesn't fit through the WARP-over-LTE path.

Open: the Pi rebooted during modem attach/radio restarts with nothing in the watchdog logs. Suspected brownout from the
SIM7600's current spikes; the under-voltage flag and persistent syslog are there to confirm or rule it out.

Planned reboots: run `mreboot` on the Pi (`/usr/bin/mreboot`, the user's script). It asks for a reason, appends
`YYYY-mm-dd HH:MM:SS | MANUAL REBOOT | Note: <reason>` to `/etc/router_maintenance.log` and reboots. `get-routerpi-status`
returns `boot.booted_local` and the last `MAINT_LINES` (5) log lines, and Sentinel's prompt (`api/chat.php`) matches them
against the boot time before calling a reboot unexpected. Both files are in `/etc/sysupgrade.conf`. Reboots done any other
way (LuCI, plain `reboot`) leave no note and count as unexpected.

Also noted, not changed: `ttyd` logs its base64 `user:password` at startup (`sentinel-stats` redacts it in `logs`;
change the ttyd password), and a leftover `cloudflared.apk-new` service crash-loops on an invalid token.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `401 unauthorized` with the right key | uhttpd isn't passing `Authorization`; check `/etc/config/uhttpd` has no `realm`/`config` basic auth on `/cgi-bin` |
| `Cloudflare Access rejected the Worker` | service token expired/revoked, or the `ROUTERPI_ACCESS_*` secrets don't match it |
| `RouterPi tunnel is offline` | Pi off, or cloudflared has no LTE uplink |
| `battery.error: no response from INA219` | I2C not enabled, or HAT not seated. Check `i2cdetect -y 1` |
| `lte.source` is `modemmanager` or `qmi` | AT ports gave nothing (see `lte.note`); signal came from the fallback |
| `lte` has only `cpsi_raw` | modem not on LTE (e.g. `NO SERVICE`, `WCDMA`); raw line is still returned |
| `history`: `no history yet` | cron line missing or cron not running (`/etc/init.d/cron status`) |
| every history row ends in `0` | NTP hook not firing. Check `/etc/hotplug.d/ntp/90-sentinel` and that NTP is enabled in System → Time |
| `operator_balance.error: no USSD reply` | wrong code, the code opens a menu, or no USSD on LTE; counted figures still work |
| a secret shows as `[redacted]` in `logs`/`dmesg` | intended: values after credential/password/token/secret/private key/pincode/psk keywords are stripped on the box |
| `clients` has no `dhcp_leases` | DHCP served by something other than dnsmasq/odhcpd, or `luci-rpc` missing |
