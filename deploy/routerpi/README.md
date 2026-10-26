# RouterPi → Sentinel

```
Sentinel (chat.php) ──MCP──> Worker "ai" (mcp.ettzz.pro)
                                 │  get-routerpi-status
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

The script is read-only: it only reads `/proc`/`/sys`, reads INA219 registers (no writes),
and sends AT *query* commands (`AT+CSQ`, `AT+CPSI?`, `AT+COPS?`, `AT+CGPS?`).

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
If `MCP_ALLOW` is set in the VirtualHost, add `get-routerpi-status` to it.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `401 unauthorized` with the right key | uhttpd isn't passing `Authorization`; check `/etc/config/uhttpd` has no `realm`/`config` basic auth on `/cgi-bin` |
| `Cloudflare Access rejected the Worker` | service token expired/revoked, or the `ROUTERPI_ACCESS_*` secrets don't match it |
| `RouterPi tunnel is offline` | Pi off, or cloudflared has no LTE uplink |
| `battery.error: no response from INA219` | I2C not enabled, or HAT not seated. Check `i2cdetect -y 1` |
| `lte` has only `cpsi_raw` | modem not on LTE (e.g. `NO SERVICE`, `WCDMA`); raw line is still returned |
