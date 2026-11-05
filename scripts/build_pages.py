#!/usr/bin/env python3
"""
Build static site into _site/ for GitHub Pages deployment.

Usage:
  python3 scripts/build_pages.py            # Build from current cache/
  python3 scripts/build_pages.py --refresh  # Refresh cache/ via PHP CLI (if available) first
"""

from datetime import datetime, timezone, timedelta
import hashlib
import html
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time
from urllib.parse import parse_qs, quote, unquote_plus, urlparse

ROOT = Path(__file__).resolve().parent.parent
CACHE = ROOT / "cache"
OUT_DIR = ROOT / "_site"
BKK_TZ = timezone(timedelta(hours=7))

NONTHABURI_PLAYER = (
    "https://stream.firsttech.co.th/live/nakornnont.stream/?muted=1&autoplay=1&controls=1"
)
PATHUM_PLAYER = "http://101.109.253.60:8999/"
ONLINE_MAX_AGE = 7200
CAM_OLD_AGE = 1800


def render_php_template(path: Path) -> str:
    text = path.read_text(encoding="utf-8")
    # Strip leading <?php ... ?> header block if present
    text = re.sub(r"^\s*<\?php.*?\?>\s*\n?", "", text, count=1, flags=re.DOTALL)
    css_mtime = int((ROOT / "assets" / "app.css").stat().st_mtime)
    replacements = {
        "<?= filemtime(__DIR__ . '/assets/app.css') ?>": str(css_mtime),
        "<?= htmlspecialchars($nonthaburiPlayer) ?>": html.escape(NONTHABURI_PLAYER, quote=True),
        "<?= htmlspecialchars($pathumPlayer) ?>": html.escape(PATHUM_PLAYER, quote=True),
        "<?= json_encode($nonthaburiPlayer) ?>": json.dumps(NONTHABURI_PLAYER),
        "<?= json_encode($pathumPlayer) ?>": json.dumps(PATHUM_PLAYER),
        'href="nbcctv.php"': 'href="nbcctv.html"',
        "fetch('api/viewers.php?id=' + viewerId, { cache: 'no-store' })": "fetch('api/viewers.json')",
        "if (navigator.sendBeacon) navigator.sendBeacon('api/viewers.php?id=' + viewerId + '&leave=1');": "/* static pages: no leave beacon */",
        "fetch('api/pakkret.php' + (force ? '?fresh=1' : ''), { cache: 'no-store' })": "fetch('api/pakkret.json', { cache: 'no-store' })",
        "fetch('api/nonthaburi.php', { cache: 'no-store' })": "fetch('api/nonthaburi.json', { cache: 'no-store' })",
        "fetch('api/cctv.php')": "fetch('api/cctv.json')",
        "fetch('api/thaiwater.php', { cache: 'no-store' })": "fetch('api/thaiwater.json', { cache: 'no-store' })",
        "fetch('api/basin.php', { cache: 'no-store' })": "fetch('api/basin.json', { cache: 'no-store' })",
        "fetch('api/rangsit.php', { cache: 'no-store' })": "fetch('api/rangsit.json', { cache: 'no-store' })",
        "fetch('api/mapdata.php', { cache: 'no-store' })": "fetch('api/mapdata.json', { cache: 'no-store' })",
    }
    for old, new in replacements.items():
        text = text.replace(old, new)
    return text


def cam_name_from_url(url: str):
    q = urlparse(url).query
    if not q:
        return None
    m = re.search(r"(?:^|&)cameraname=([^&]*)", q)
    if not m:
        return None
    name = unquote_plus(m.group(1))
    return name if name else None


def parse_sensor(node):
    if not isinstance(node, dict) or not node.get("enable"):
        return None
    v = node.get("value")
    if not isinstance(v, dict):
        return None
    now_val = float(v.get("now") or 0.0)
    old_raw = v.get("old")
    old_val = float(old_raw) if old_raw is not None and old_raw != "" else None
    if old_val is None or now_val == old_val:
        trend = "flat"
    elif now_val > old_val:
        trend = "up"
    else:
        trend = "down"
    return {
        "now": now_val,
        "warning": float(v.get("warning") or 0.0),
        "danger": float(v.get("danger") or 0.0),
        "status": str(v.get("status") or "white"),
        "trend": trend,
    }


def build_nonthaburi_json():
    raw_file = CACHE / "nonthaburi_raw.json"
    if not raw_file.is_file():
        return
    shutil.copy2(raw_file, OUT_DIR / "cache" / "nonthaburi_raw.json")
    raw = json.loads(raw_file.read_text(encoding="utf-8"))
    stations_in = raw.get("station") if isinstance(raw, dict) else None
    if not isinstance(stations_in, list):
        return

    now_ts = time.time()
    out_stations = []
    copied = 0

    for s in stations_in:
        if not isinstance(s, dict):
            continue
        zone = str(s.get("zone") or "")
        loc = s.get("location") if isinstance(s.get("location"), dict) else {}
        date_str = str(s.get("date") or "")

        cams = []
        for u in s.get("cctv") or []:
            name = cam_name_from_url(str(u))
            if not name:
                continue
            md5_hex = hashlib.md5((name + "|800").encode("utf-8")).hexdigest()
            full_f = CACHE / f"cam_{md5_hex}.jpg"
            thumb_f = CACHE / f"cam_{md5_hex}_480.jpg"
            off_f = CACHE / f"cam_{md5_hex}.jpg.off"

            src_img = thumb_f if thumb_f.is_file() else (full_f if full_f.is_file() else None)
            if src_img is not None:
                rel_path = f"cache/{src_img.name}"
                shutil.copy2(src_img, OUT_DIR / rel_path)
                cam_url = f"{rel_path}?cam={quote(name, safe='')}"
                mtime = int(full_f.stat().st_mtime) if full_f.is_file() else int(src_img.stat().st_mtime)
                copied += 1
            else:
                cam_url = f"cache/cam_{md5_hex}_480.jpg?cam={quote(name, safe='')}"
                mtime = 0

            cams.append(
                {
                    "name": name,
                    "url": cam_url,
                    "v": mtime,
                    "off": 1 if off_f.is_file() else 0,
                    "old": 1 if (mtime > 0 and (now_ts - mtime > CAM_OLD_AGE)) else 0,
                }
            )

        d = s.get("data") if isinstance(s.get("data"), dict) else {}
        rf = None
        rf_node = d.get("rf")
        if isinstance(rf_node, dict) and rf_node.get("enable") and isinstance(rf_node.get("value"), dict):
            rv = rf_node["value"]
            rf = {
                "now": float(rv.get("now") or 0.0),
                "day": float(rv.get("day") or 0.0),
                "status": str(rv.get("status") or "white"),
            }

        online = False
        if date_str:
            try:
                dt = datetime.strptime(date_str, "%Y-%m-%d %H:%M").replace(tzinfo=BKK_TZ)
                # Consider online if within 2h of raw file mtime (so static builds stay online)
                ref_ts = raw_file.stat().st_mtime
                online = dt.timestamp() >= (ref_ts - ONLINE_MAX_AGE)
            except ValueError:
                online = True

        out_stations.append(
            {
                "id": str(s.get("id") or ""),
                "code": str(s.get("code") or ""),
                "zone": zone,
                "type": "road" if zone == "C" else "gate",
                "name": str(s.get("name") or ""),
                "lat": float(loc["lat"]) if "lat" in loc and loc["lat"] is not None else None,
                "lng": float(loc["lng"]) if "lng" in loc and loc["lng"] is not None else None,
                "date": date_str,
                "online": online,
                "cams": cams,
                "wl_up": parse_sensor(d.get("wl_up")),
                "wl_down": parse_sensor(d.get("wl_down")),
                "rf": rf,
            }
        )

    payload = {
        "fetched_at": datetime.now(BKK_TZ).isoformat(timespec="seconds"),
        "stale": False,
        "stations": out_stations,
    }
    (OUT_DIR / "api" / "nonthaburi.json").write_text(
        json.dumps(payload, ensure_ascii=False), encoding="utf-8"
    )
    print(f"[build] _site/api/nonthaburi.json ({len(out_stations)} stations, {copied} snapshots)")


def main():
    refresh = "--refresh" in sys.argv
    CACHE.mkdir(parents=True, exist_ok=True)
    for sub in ("assets", "api", "cache"):
        (OUT_DIR / sub).mkdir(parents=True, exist_ok=True)

    if refresh and shutil.which("php"):
        for ep in ("basin.php", "thaiwater.php", "pakkret.php", "rangsit.php", "cctv.php", "nonthaburi.php", "mapdata.php"):
            res = subprocess.run(
                ["php", str(ROOT / "api" / ep)],
                capture_output=True,
                timeout=90,
                check=False,
            )
            print(f"[refresh] {ep}: exit={res.returncode}, bytes={len(res.stdout)}")

    shutil.copy2(ROOT / "assets" / "app.css", OUT_DIR / "assets" / "app.css")
    if (ROOT / "api" / "pakkret_coords.json").is_file():
        shutil.copy2(ROOT / "api" / "pakkret_coords.json", OUT_DIR / "api" / "pakkret_coords.json")
    (OUT_DIR / ".nojekyll").write_text("", encoding="utf-8")

    index_html = render_php_template(ROOT / "index.php")
    (OUT_DIR / "index.html").write_text(index_html, encoding="utf-8")
    print(f"[build] _site/index.html ({len(index_html)} chars)")

    nb_html = render_php_template(ROOT / "nbcctv.php")
    (OUT_DIR / "nbcctv.html").write_text(nb_html, encoding="utf-8")
    print(f"[build] _site/nbcctv.html ({len(nb_html)} chars)")

    (OUT_DIR / "api" / "viewers.json").write_text(
        json.dumps({"viewers": 1}), encoding="utf-8"
    )

    for name in ("basin", "thaiwater", "rangsit", "mapdata"):
        src = CACHE / f"{name}.json"
        if src.is_file():
            data = json.loads(src.read_text(encoding="utf-8"))
            if isinstance(data, dict):
                data["stale"] = False
                (OUT_DIR / "api" / f"{name}.json").write_text(
                    json.dumps(data, ensure_ascii=False), encoding="utf-8"
                )
                print(f"[build] _site/api/{name}.json")

    pk_src = CACHE / "pakkret.json"
    if pk_src.is_file():
        pk = json.loads(pk_src.read_text(encoding="utf-8"))
        if isinstance(pk, dict):
            pk["stale"] = False
            pk_img = CACHE / "pakkret.jpg"
            if pk_img.is_file():
                shutil.copy2(pk_img, OUT_DIR / "cache" / "pakkret.jpg")
                pk["image_url"] = f"cache/pakkret.jpg?t={int(pk_img.stat().st_mtime)}"
            else:
                pk["image_url"] = None
            (OUT_DIR / "api" / "pakkret.json").write_text(
                json.dumps(pk, ensure_ascii=False), encoding="utf-8"
            )
            print("[build] _site/api/pakkret.json")

    cctv_src = CACHE / "cctv.json"
    if cctv_src.is_file():
        cctv_data = json.loads(cctv_src.read_text(encoding="utf-8"))
        blank = []
        blank_src = CACHE / "cctv_blank.json"
        if blank_src.is_file():
            shutil.copy2(blank_src, OUT_DIR / "cache" / "cctv_blank.json")
            bs = json.loads(blank_src.read_text(encoding="utf-8"))
            if isinstance(bs, dict):
                blank = [str(k) for k, v in bs.items() if isinstance(v, list) and v and v[0]]
        if isinstance(cctv_data, dict):
            cctv_data["blank"] = blank
            (OUT_DIR / "api" / "cctv.json").write_text(
                json.dumps(cctv_data, ensure_ascii=False), encoding="utf-8"
            )
            print(f"[build] _site/api/cctv.json ({len(cctv_data.get('cams', []))} cams)")

    build_nonthaburi_json()


if __name__ == "__main__":
    main()
