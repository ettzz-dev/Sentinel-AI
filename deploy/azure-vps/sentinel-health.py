#!/usr/bin/env python3
"""Sentinel VPS health: read-only JSON snapshot of the Azure VPS for the Sentinel MCP Worker.

Serves GET on 127.0.0.1:9077 for cloudflared. Python standard library only.
Auth: "Authorization: Bearer <key>". The key comes from the systemd credential "key"
(see sentinel-health.service), else /etc/sentinel/key.
Settings (environment): SENTINEL_BIND, SENTINEL_PORT, SENTINEL_SERVICES (space-separated units to watch).
See deploy/azure-vps/README.md.
"""

import hmac
import json
import os
import re
import socket
import subprocess
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

BIND = os.environ.get("SENTINEL_BIND", "127.0.0.1")
PORT = int(os.environ.get("SENTINEL_PORT", "9077"))
# Ubuntu 24.04 starts sshd through ssh.socket, so watch the socket rather than ssh.service
SERVICES = os.environ.get("SENTINEL_SERVICES", "ssh.socket cloudflared").split()
REAL_FS = {"ext4", "ext3", "xfs", "btrfs", "vfat", "zfs"}


def read_key():
    cred = os.environ.get("CREDENTIALS_DIRECTORY")
    path = os.path.join(cred, "key") if cred else "/etc/sentinel/key"
    with open(path) as f:
        return f.read().strip()


def run(*cmd):
    try:
        return subprocess.run(cmd, capture_output=True, text=True, timeout=5).stdout
    except (OSError, subprocess.TimeoutExpired):
        return ""


def cpu_percent():
    def sample():
        with open("/proc/stat") as f:
            v = [int(x) for x in f.readline().split()[1:9]]
        return sum(v), v[3] + v[4]  # total, idle + iowait

    t1, i1 = sample()
    time.sleep(0.5)
    t2, i2 = sample()
    dt = t2 - t1
    return round(100 * (dt - (i2 - i1)) / dt, 1) if dt else 0.0


def memory():
    m = {}
    with open("/proc/meminfo") as f:
        for line in f:
            k, v = line.split(":", 1)
            m[k] = int(v.split()[0])
    total, avail = m["MemTotal"], m["MemAvailable"]
    return {
        "total_mb": total // 1024,
        "available_mb": avail // 1024,
        "used_pct": round(100 * (total - avail) / total, 1),
        "swap_total_mb": m.get("SwapTotal", 0) // 1024,
        "swap_used_mb": (m.get("SwapTotal", 0) - m.get("SwapFree", 0)) // 1024,
    }


def disks():
    out, seen = [], set()
    with open("/proc/mounts") as f:
        for line in f:
            dev, mnt, fs = line.split()[:3]
            if fs not in REAL_FS or dev in seen:
                continue
            seen.add(dev)
            mnt = mnt.replace("\\040", " ")
            try:
                st = os.statvfs(mnt)
            except OSError:
                continue
            size = st.f_blocks * st.f_frsize
            free = st.f_bavail * st.f_frsize
            used = size - st.f_bfree * st.f_frsize
            out.append({
                "mount": mnt,
                "fs": fs,
                "size_gb": round(size / 1e9, 1),
                "used_gb": round(used / 1e9, 1),
                "free_gb": round(free / 1e9, 1),
                "used_pct": round(100 * used / (used + free), 1) if used + free else 0,
            })
    return out


def services():
    out = []
    for name in SERVICES:
        props = dict(
            line.split("=", 1)
            for line in run("systemctl", "show", name, "--property=LoadState,ActiveState,SubState,ActiveEnterTimestamp").splitlines()
            if "=" in line
        )
        out.append({
            "name": name,
            "installed": props.get("LoadState") == "loaded",
            "state": props.get("ActiveState", "unknown"),
            "sub_state": props.get("SubState", ""),
            "since": props.get("ActiveEnterTimestamp") or None,
        })
    return out


def failed_units():
    lines = run("systemctl", "list-units", "--state=failed", "--no-legend", "--plain").splitlines()
    return [line.split()[0] for line in lines if line.strip()]


def listening_ports():
    found = {}
    # Process names would need root, so ports only
    for line in run("ss", "-Hltnu").splitlines():
        p = line.split()
        if len(p) < 5:
            continue
        addr, _, port = p[4].rpartition(":")
        addr = addr.strip("[]").split("%")[0]
        if addr.startswith("127.") or addr == "::1":
            scope = "loopback"
        elif addr in ("0.0.0.0", "::", "*"):
            scope = "all interfaces"
        else:
            scope = "specific address"
        key = (p[0], addr, port)
        found[key] = {"proto": p[0], "address": addr, "port": int(port) if port.isdigit() else port, "scope": scope}
    return sorted(found.values(), key=lambda x: (x["scope"] == "loopback", str(x["port"]), x["proto"]))


def updates():
    info = {"reboot_required": os.path.exists("/run/reboot-required")}
    try:
        with open("/var/lib/update-notifier/updates-available") as f:
            text = f.read()
    except OSError:
        return info
    m = re.search(r"(\d+) updates? can be applied immediately", text)
    info["pending"] = int(m.group(1)) if m else 0
    m = re.search(r"(\d+) of these updates? (?:is a|are) standard security update", text)
    info["security"] = int(m.group(1)) if m else 0
    return info


def os_name():
    try:
        with open("/etc/os-release") as f:
            for line in f:
                if line.startswith("PRETTY_NAME="):
                    return line.split("=", 1)[1].strip().strip('"')
    except OSError:
        pass
    return None


def snapshot():
    with open("/proc/uptime") as f:
        uptime = int(float(f.read().split()[0]))
    now = int(time.time())
    return {
        "timestamp": now,
        "time_local": time.strftime("%Y-%m-%d %H:%M %Z", time.localtime(now)),
        "hostname": socket.gethostname(),
        "os": os_name(),
        "kernel": os.uname().release,
        "uptime_s": uptime,
        "cpu": {"usage_pct": cpu_percent(), "cores": os.cpu_count(), "load_avg": [round(x, 2) for x in os.getloadavg()]},
        "memory": memory(),
        "disks": disks(),
        "services": services(),
        "failed_units": failed_units(),
        "listening_ports": listening_ports(),
        "updates": updates(),
    }


class Handler(BaseHTTPRequestHandler):
    server_version = "sentinel-health"
    sys_version = ""

    def do_GET(self):
        auth = self.headers.get("Authorization", "")
        if not hmac.compare_digest(auth.encode(), f"Bearer {KEY}".encode()):
            return self.reply(401, {"error": "unauthorized"})
        try:
            self.reply(200, snapshot())
        except Exception as e:  # report instead of dropping the connection
            self.reply(500, {"error": f"{type(e).__name__}: {e}"})

    def reply(self, code, obj):
        data = json.dumps(obj).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def log_message(self, fmt, *args):
        pass


KEY = read_key()
if not KEY:
    raise SystemExit("sentinel-health: empty key")

if __name__ == "__main__":
    ThreadingHTTPServer((BIND, PORT), Handler).serve_forever()
