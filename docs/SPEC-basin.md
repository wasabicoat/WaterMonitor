# SPEC — Upstream Chao Phraya (ThaiWater) + Rangsit CDP cameras + camera directory

Project root `/Volumes/web/WaterMonitor` (Synology Web Station, PHP 8.2 + curl + openssl; code must also run on
PHP 7.0: no `fn()` arrow functions, no `match`, no `str_contains`/`str_starts_with`, no typed params/returns,
no `??=`). UI language Thai. Data sources were discovered via https://rutunnam.vercel.app (credit it in the UI) but
we call the ORIGINAL sources, never rutunnam's own `/api/*`.

Shared helpers: `api/lib.php` — read it. Every new API file starts with `require __DIR__ . '/lib.php';` and uses
`cache_dir()`, `fetch_url($url, $timeout)`, `to_json()`, `send_json($value, $httpCode)`, globals `$DEBUG`,
`$FETCH_LOG`. Reference implementation for style/structure: `api/thaiwater.php` (cache-then-scrape-then-stale
pattern, `?debug=1` page). Mirror it.

-----------------------------------------------------------------------------------------------------------------
## §1 Upstream facts (verified 2026-09-30)

ThaiWater base `TW = https://api-v3.thaiwater.net/api/v1/thaiwater30/` (no CORS → must proxy; responses are big).

### §1.1 `GET TW + 'public/waterlevel_load'` (~1.4 MB, ~0.6 s)
`{"waterlevel_data":{"result":"OK","data":[ ...806 rows... ]}, "scale":{"result":"OK","data":{"scale":[...]}}, ...}`
Row (trimmed):
```json
{"waterlevel_datetime":"2026-09-30 11:00","waterlevel_msl":"23.73","waterlevel_msl_previous":"23.80",
 "storage_percent":"84.09","discharge":"2425.00","diff_wl_bank":"1.97","diff_wl_bank_text":"ต่ำกว่าตลิ่ง (ม.)",
 "river_name":"แม่น้ำเจ้าพระยา",
 "station":{"id":2795,"tele_station_name":{"th":"ค่ายจิรประวัติ"},"tele_station_oldcode":"C.2",
            "tele_station_lat":15.67059,"tele_station_long":100.10936,"min_bank":25.7,"qmax":3735},
 "geocode":{"province_code":"60","province_name":{"th":"นครสวรรค์"},"amphoe_name":{"th":"เมืองนครสวรรค์"}}}
```
Numbers arrive as strings or null. `scale.data.scale` = list of `{"operator":">"|"<=","term":"100","color":"#FF0000","situation":"น้ำล้นตลิ่ง"}`
evaluated in order against `storage_percent` (first match wins) — identical to `tw_situation()` in `api/thaiwater.php`
(copy that function; do not include thaiwater.php).

### §1.2 `GET TW + 'public/watergate_load'` (~3.6 MB, ~1.3 s)
`{"watergate_data":{"data":[...]}}`. Row:
```json
{"watergate_datetime_in":"2026-09-30 11:40","watergate_in":2.474,"watergate_datetime_out":"2026-09-30 11:40","watergate_out":2.08875,
 "station":{"id":492324,"tele_station_name":{"th":"ปตร.จุฬาลงกรณ์"},"tele_station_oldcode":"ATG101"},
 "geocode":{"province_code":"13","province_name":{"th":"ปทุมธานี"}}}
```
`watergate_in` = upstream (หน้า) level, `watergate_out` = downstream (ท้าย) level, ม.รทก., may be null.

### §1.3 `GET TW + 'analyst/dam'` (~1 MB)
`{"result":"OK","data":{"dam_daily":[...]}}`. Row: `dam.dam_name.th`, `dam_date` ("2026-09-30"), `dam_storage` (ล้าน ลบ.ม.),
`dam_storage_percent`, `dam_uses_water_percent`, `dam_inflow`, `dam_released` (ล้าน ลบ.ม./วัน). The same dam appears
for several dates; rows with `dam_storage_percent` 0 are placeholders.

### §1.4 Rangsit CDP `GET https://cdp.rangsitcity.go.th/` (HTML ~234 KB, Next.js)
The HTML embeds camera points in escaped JSON (`\"` quotes). After replacing `\"` with `"`, each point looks like:
```
{"id":152,"name":"เมืองปทุม","description":null,"latitude":14.02283238,"longitude":100.53555608,
 "watch":{"pointId":152,"label":"จังหวัดปทุมธานี (เมืองปทุม)","level":"WATCH","note":null,"updatedBy":"อัตโนมัติ (OpenCV)",
          "updatedAt":"2026-09-30T04:57:14.695Z","mode":"AUTO","cvLevel":"WATCH","cvReason":"สูงกว่าตลิ่ง ... (ผิวน้ำ ≈2.36 ม.รทก., 14 ซม. ต่ำกว่าขีดแดง ...)",
          "cvAt":"$D2026-09-30T04:57:14.694Z", ...}}
```
Currently 2 points: 151 "กล้อง CCTV ตรวจวัดระดับน้ำสะพานแดง", 152 "เมืองปทุม". `level` ∈ NORMAL?/WATCH/WARNING?/CRITICAL
(treat unknown as-is). Snapshot image: `https://cdp.rangsitcity.go.th/api/flood/snapshot/<id>?t=<ms>` (JPEG, no-store,
hotlinking allowed) — the BROWSER loads it directly; the PHP never proxies images.

-----------------------------------------------------------------------------------------------------------------
## §2 `api/basin.php` (new)

Constants: `TW_BASE`, `BASIN_TTL = 600` (s), `RECENT_HOURS = 6`, provinces `BASIN_PROVINCES = ['12','13','14','10']`
(นนทบุรี, ปทุมธานี, พระนครศรีอยุธยา, กรุงเทพมหานคร), gate provinces `['12','13','10']`,
key stations in upstream→downstream order: `['C.2','C.13','C.3','C.7A','C.35','CPY014']`,
main dams (substring match on `dam.dam_name.th`): `['ภูมิพล','สิริกิติ์','แควน้อย','ป่าสัก']`.

Algorithm `basin_scrape()` → array|null:
1. Fetch §1.1 (timeout 30). If not valid JSON with `waterlevel_data.data` array → return null (whole call fails).
2. `$scale = waterlevel_load.scale.data.scale` (array, default []).
3. For every row build `st = wl_item(row, scale)`:
   ```
   {code, name, province, amphoe, river, lat, lng, datetime, wl, wl_prev, bank(min_bank), pct(storage_percent),
    discharge, qmax, diff_bank, diff_text, situation:{text,color}}
   ```
   (floats via `num_or_null`, strings default ''). `recent` = `strtotime(datetime) >= time() - RECENT_HOURS*3600`.
4. `key_stations` = rows whose `code` is in the key list, output in the key-list order (skip missing), include even
   if not recent but add `"recent": bool`.
5. `stations` = rows with province_code in BASIN_PROVINCES AND recent, sorted by `pct` desc (nulls last), each with
   `province_code` added.
6. Fetch §1.2 (timeout 30); on failure `gates = []` and add `"gates"` to `$errors`. Else `gates` = rows with
   province_code in gate provinces AND (watergate_in !== null OR watergate_out !== null) AND
   max(datetime_in, datetime_out) recent → `{code, name, province, datetime (latest of in/out), up (in), down (out)}`,
   sorted by province then name.
7. Fetch §1.3 (timeout 30); on failure `dams = []`, add `"dams"` to `$errors`. Else for each main-dam keyword take the
   row with the latest `dam_date` among rows whose name contains the keyword AND `dam_storage_percent > 0` →
   `{name, date, storage, storage_pct, uses_pct, inflow, released}` in keyword order (skip if none).
8. Return `{fetched_at: date('c'), key_stations, stations, gates, dams, errors: [...]}`.

Endpoint behaviour (same as `api/thaiwater.php`): serve cache `basin.json` if age < BASIN_TTL (`stale:false`); else
scrape → write cache → serve; scrape null → serve old cache with `stale:true`; none → 502
`{"error":"ดึงข้อมูลลุ่มเจ้าพระยาจาก ThaiWater ไม่ได้ (เปิด api/basin.php?debug=1)"}`.
`?debug=1` → text/plain: PHP/curl info, `$FETCH_LOG` lines, counts (key_stations, stations, gates, dams), `errors`,
then `RESULT: OK|FAIL`. Memory: set `@ini_set('memory_limit','256M')` at top (watergate JSON is 3.6 MB decoded).

## §3 `api/rangsit.php` (new)

Constants: `CDP_URL = 'https://cdp.rangsitcity.go.th/'`, `CDP_TTL = 60`.
`cdp_scrape()`: fetch CDP_URL (timeout 20); `$s = str_replace('\\"', '"', $html)`; regex
`/\{"id":(\d+),"name":"([^"]*)","description":(?:null|"[^"]*"),"latitude":([-\d.]+),"longitude":([-\d.]+),"watch":\{(.*?)\}\}/u`
for each match parse the watch body with small regexes for `"level":"([A-Z_]+)"`, `"label":"([^"]*)"`,
`"cvReason":"((?:[^"\\]|\\.)*)"` (json_decode('"'.$m.'"') to unescape), `"updatedAt":"([^"]+)"`, `"updatedBy":"([^"]*)"`.
Output point: `{id:int, name, label, lat, lng, level, reason, updated_at (ISO string as given), updated_by,
image:"https://cdp.rangsitcity.go.th/api/flood/snapshot/<id>"}`. Deduplicate by id (keep first). No points → null.
Endpoint: same cache/stale/502 pattern with cache file `rangsit.json`, response `{fetched_at, stale, source:CDP_URL, points:[...]}`;
502 error text `"ดึงข้อมูลกล้องรังสิตไม่ได้ (เปิด api/rangsit.php?debug=1)"`. `?debug=1` prints fetch log, point count,
each point `id name level`, `RESULT: OK|FAIL`.

-----------------------------------------------------------------------------------------------------------------
## §4 Frontend — edits to `index.php` (ADD only)

Do not change existing cards/JS behaviour. Reuse: `$`, `esc()`, `every(ms, fn)` (visibility-aware poller),
`twTextColor(hex)`, CSS tokens and classes `.card .card.wide .body .meta .legend .table-wrap .chips .chip .dot .badge
.camgrid figure.cam .twgrid .tw .twbar .level .num`. Chart.js is already loaded (don't add charts in this task).

### §4.1 Nav (inside `.secnav .inner`, after the `ThaiWater` link): `#basin` "ต้นน้ำ", `#rangsit` "กล้องรังสิต", `#links` "ลิงก์กล้องอื่น".

### §4.2 Card `#basin` (card wide) — insert right AFTER the `#thaiwater` card
Title `🌊 น้ำจากต้นน้ำเจ้าพระยา → นนทบุรี`. Body:
* **Flow strip** `#bsKey`: one tile per key station in order, horizontally scrollable on mobile
  (`display:flex; gap; overflow-x:auto`), tiles connected by "→". Tile: `code` + `name` + province (small), big
  `discharge` "2,425 ลบ.ม./วิ" when not null (with `% of qmax` if qmax: "65% ของความจุลำน้ำ"), otherwise water level
  "2.46 ม.รทก."; situation badge (text+color, text color via `twTextColor`); `pct.toFixed(0)+'% ตลิ่ง'`; time HH:MM;
  if `!recent` → add "(ข้อมูลเก่า)". Discharge formatted `toLocaleString('th-TH')`.
* Note line (`.legend`): "ปริมาณน้ำไหลผ่าน (ลบ.ม./วินาที) ที่นครสวรรค์ (C.2) และท้ายเขื่อนเจ้าพระยา (C.13) ใช้คาดการณ์น้ำที่จะมาถึงนนทบุรีในอีก 2–4 วัน".
* **Dams** `#bsDams` (`.twgrid` of small tiles): name, `storage_pct` bar (`.twbar`, color: >100 `var(--crit)`,
  >80 `var(--warn)`, else `var(--ok)`), "เก็บกัก 106.8% · ระบายวันนี้ 9.94 ล้าน ลบ.ม. · ไหลเข้า 47.54" and date.
* **Stations table** (`.table-wrap`) `#bsTable`: columns สถานี (code name) | จังหวัด | ลำน้ำ | ระดับ (ม.รทก.) with ▲/▼
  vs wl_prev | % ตลิ่ง | สถานการณ์ (dot + text, dot color = situation.color) | เวลา (HH:MM). Province filter chips above
  (`#bsProv`, buttons like `.tabs button`): ทั้งหมด / นนทบุรี / ปทุมธานี / พระนครศรีอยุธยา / กรุงเทพมหานคร (filter by
  `province`). Summary `#bsSummary`: "ล้นตลิ่ง N สถานี · น้ำมาก N" counted from situation.text over ALL rows.
* **Gates table** (`<details class="data">` summary "ประตูระบายน้ำ (N)") `#bsGates`: ประตู | จังหวัด | หน้า | ท้าย | เวลา.
* Footer meta: "ที่มา: ThaiWater (สสน.) · อัปเดตทุก 10 นาที" + `#bsMsg` for errors/stale (also list `errors` from API:
  "ข้อมูลประตูระบายน้ำ/เขื่อนไม่พร้อมใช้งาน").

### §4.3 Card `#rangsit` (card wide) — insert AFTER `#basin`
Title `📷 กล้องวัดระดับน้ำ รังสิต–ปทุมธานี (AI อ่านระดับน้ำ)`. Grid (`.camgrid` but `minmax(300px,1fr)`) of tiles:
`<figure class="cam">` with `<img>` (src = `image + '?t=' + Date.now()`, `loading="lazy"`, `decoding="async"`),
figcaption = name; under it a level badge (`level` mapped: CRITICAL→'วิกฤต' var(--crit); WARNING→'เตือนภัย' var(--warn);
WATCH→'เฝ้าระวัง' var(--warn); NORMAL→'ปกติ' var(--ok); other→level text, var(--line)) and `reason` text (.meta,
esc'd) + "อัปเดต HH:MM" (from updated_at, local time). Click tile → open the image in the EXISTING lightbox style:
simplest = `window.open(image, '_blank')` is NOT acceptable; build a lightbox like `nbOpenLightbox` (you may generalize
it into `openLightbox(src, captionHtml, readingsText)` and make `nbOpenLightbox` call it — behaviour of the CCTV
lightbox must stay identical). Refresh: `every(60000, loadRangsit)`; image src updated with new `?t=` only for tiles
on screen (IntersectionObserver) — like the existing CCTV grid. Source line: "ที่มา: ศูนย์ข้อมูลภัยพิบัติ เทศบาลนครรังสิต
(cdp.rangsitcity.go.th) · ระดับน้ำประเมินอัตโนมัติจากภาพ (OpenCV) โดยต้นทาง".

### §4.4 Card `#links` (card wide) — insert AFTER `#rangsit`, static HTML, no JS
Title `🔗 กล้องและข้อมูลน้ำจากแหล่งอื่น`. List (`<ul class="linklist">`, 2 columns ≥700px) of `<a target="_blank"
rel="noopener">` with a one-line description:
1. ศูนย์กล้องลุ่มน้ำเจ้าพระยา (กรมชลประทาน) — https://wmsc.rid.go.th/cctv2/ — "เว็บไซต์ระบุว่ารองรับ Firefox"
2. กล้องถนนปากเกร็ด (เทศบาลนครปากเกร็ด) — https://www.thaiclouderp.com/CCTV_MONITOR/web/pakkred
3. กล้องจราจรเทศบาลนครรังสิต — https://rangsitcity.go.th/cctvrangsitcity/
4. เทศบาลเมืองปทุมธานี (กล้องสวนเทพปทุมฯ) — https://www.muangpathum.org/th/z_main.jsp
5. ศูนย์กล้องระดับน้ำทั่วประเทศ (กรมทรัพยากรน้ำ) — https://telemetry.dwr.go.th/reportCctv
6. แผนที่น้ำท่วม 7 วัน (GISTDA) — https://disaster.gistda.or.th/flood
7. รู้ทันน้ำ — แผนที่สถานการณ์น้ำ — https://rutunnam.vercel.app — "แหล่งอ้างอิงรายชื่อกล้องและแหล่งข้อมูลในส่วนนี้"
Note (.legend): "กล้องของ กทม. (dds.bangkok.go.th) ไม่ได้นำมาแสดง เพราะภาพไม่อัปเดตมาตั้งแต่ ส.ค. 2569".

### §4.5 Loading pattern (both loaders)
Same as `loadThaiwater`: fetch with `cache:'no-store'`, `text()` + `JSON.parse` try/catch → message
`api/<file> ตอบกลับผิดรูปแบบ (HTTP n)`; non-OK → `d.error`; stale → "ข้อมูลเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)".
`loadBasin()` on load + `every(600000, loadBasin)`; `loadRangsit()` on load + `every(60000, loadRangsit)`.
All upstream strings through `esc()` when using innerHTML. Mobile 375px: no horizontal page scroll.

-----------------------------------------------------------------------------------------------------------------
## §5 Acceptance (run by reviewer)
1. `php -l` all changed/new PHP on 8.3 and 7.0.
2. `api/basin.php` 200: key_stations 6 in order, stations ≥ 30 all recent & in 4 provinces sorted by pct desc,
   gates ≥ 2, dams 4 (ภูมิพล, สิริกิติ์, แควน้อย…, ป่าสัก…) with storage_pct > 0; second call served from cache (< 50 ms).
3. `api/rangsit.php` 200: 2 points with id/name/level/reason/image; `?debug=1` RESULT: OK.
4. Browser (nginx+php-fpm): no console errors; 375px `scrollWidth == innerWidth`; new nav links scroll to cards;
   rangsit lightbox open/close (×, Esc, backdrop) and the CCTV lightbox still works.
