#!/bin/sh
# Installs/updates sentinel-stats on an OpenWrt box. Run ON the router:
#   sh /tmp/install.sh routerpi            (keeps the existing key and config)
#   sh /tmp/install.sh erx <ERX_KEY>       (writes key + UPS=0/LTE=0 config if missing)
# Expects the new script at /tmp/sentinel-stats.
set -e
role=$1
src=/tmp/sentinel-stats
dst=/www/cgi-bin/sentinel-stats
[ -s "$src" ] || { echo "missing $src"; exit 1; }

mkdir -p /etc/sentinel /www/cgi-bin
[ -f "$dst" ] && cp "$dst" "/etc/sentinel/sentinel-stats.bak" && echo "old script backed up to /etc/sentinel/sentinel-stats.bak"
cp "$src" "$dst"
chmod 755 "$dst"

if [ -n "$2" ] && [ ! -s /etc/sentinel/key ]; then
	printf '%s' "$2" > /etc/sentinel/key
	echo "key written"
fi
chmod 600 /etc/sentinel/key 2>/dev/null || echo "WARNING: /etc/sentinel/key missing"

if [ "$role" = erx ] && [ ! -f /etc/sentinel/config ]; then
	wan=$(ubus call network.interface.wan status 2>/dev/null | jsonfilter -e '@.l3_device' 2>/dev/null)
	printf 'UPS=0\nLTE=0\nWWAN_IF=%s\n' "${wan:-eth0}" > /etc/sentinel/config
	echo "config written (WWAN_IF=${wan:-eth0})"
fi

for f in "$dst" /etc/sentinel/; do
	grep -qxF "$f" /etc/sysupgrade.conf 2>/dev/null || echo "$f" >> /etc/sysupgrade.conf
done

set +e
echo "--- self test"
HTTP_AUTHORIZATION="Bearer $(cat /etc/sentinel/key)" "$dst" | tail -n 1 | cut -c 1-1500
echo
if [ "$role" = routerpi ]; then
	echo "--- diagnostics"
	ls -l /dev/ttyUSB* /dev/cdc-wdm* /dev/i2c-* 2>&1
	pgrep -l ModemManager 2>/dev/null || echo "ModemManager not running"
	for h in /sys/class/hwmon/hwmon*; do echo "$h $(cat $h/name 2>/dev/null)"; done
	command -v i2cdetect >/dev/null && i2cdetect -y 1 2>&1 | tail -n 8
	dmesg | grep -iE 'ttyUSB|option|qmi|cdc|i2c|ina2' | tail -n 15
fi
