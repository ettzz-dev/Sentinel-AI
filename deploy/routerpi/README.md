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

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `401 unauthorized` with the right key | uhttpd isn't passing `Authorization`; check `/etc/config/uhttpd` has no `realm`/`config` basic auth on `/cgi-bin` |
| `Cloudflare Access rejected the Worker` | service token expired/revoked, or the `ROUTERPI_ACCESS_*` secrets don't match it |
| `RouterPi tunnel is offline` | Pi off, or cloudflared has no LTE uplink |
| `battery.error: no response from INA219` | I2C not enabled, or HAT not seated. Check `i2cdetect -y 1` |
| `lte` has only `cpsi_raw` | modem not on LTE (e.g. `NO SERVICE`, `WCDMA`); raw line is still returned |
| `history`: `no history yet` | cron line missing or cron not running (`/etc/init.d/cron status`) |
| every history row ends in `0` | NTP hook not firing. Check `/etc/hotplug.d/ntp/90-sentinel` and that NTP is enabled in System → Time |
| `operator_balance.error: no USSD reply` | wrong code, the code opens a menu, or no USSD on LTE; counted figures still work |
| `clients` has no `dhcp_leases` | DHCP served by something other than dnsmasq/odhcpd, or `luci-rpc` missing |
