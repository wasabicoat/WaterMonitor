# SPEC — Nonthaburi municipality CCTV/stations + Facebook Live integration

Project root: `/Volumes/web/WaterMonitor` (served by Synology Web Station, PHP 8.2 with curl+openssl; code must
also run on PHP 7.0: no arrow functions `fn()`, no `match`, no `str_contains`, no typed properties/params unions).
Existing files: `index.php` (single-page UI), `api/pakkret.php` (reference implementation for style: fetch helper,
`cache_dir()`, `to_json()`, `?debug=1`). Language of all UI text: Thai.

---------------------------------------------------------------------------------------------------------
## §1 Upstream facts (verified)

* Station list: `GET http://182.52.224.70/json.php?app=station` → `application/json`, ~40 KB,
  `{"results":"Success","station":[ ... 41 items ... ]}`. Plain HTTP (no SSL).
* Station item (trimmed):
  ```json
  {"id":"STN2","code":"A2","zone":"A","name":"วัดตำหนักใต้",
   "cctv":["http://182.52.224.70/MilestoneImageService/ImageService.svc/ImageService/GetImage?width=800&height=450&ondate=2026-09-30 10:20&cameraname=A2-วัดตำหนักใต้ Cam1", "..."],
   "location":{"lat":13.886683,"lng":100.488564},
   "data":{
     "rf":{"enable":true,"value":{"now":0,"day":43.8,"warning":30,"danger":90,"status":"success","old":0}},
     "wl_up":{"enable":true,"value":{"now":0.66,"warning":1.5,"danger":2,"status":"success","old":0.66}},
     "wl_down":{"enable":true,"value":{"now":2.21,"warning":1.5,"danger":2.5,"status":"warning","old":2.21}}},
   "date":"2026-09-30 10:15","timeout":false,"door":true}
  ```
  * `cctv` may be `[]`. URLs contain raw spaces and Thai characters (not percent-encoded).
  * `data.X.enable` false OR `data.X.value` null ⇒ sensor absent.
  * `status` ∈ `success` (ปกติ), `warning` (เฝ้าระวัง), `danger` (วิกฤต), `black` (ขาดการติดต่อ), `white` (ไม่มีข้อมูล).
    Upstream status is authoritative — do NOT recompute from thresholds.
  * Zones A and B = floodgates (`ประตูระบายน้ำ`): `wl_up` = "หน้า" side, `wl_down` = "ท้าย" side, unit ม.รทก.
    Zone C = street-level water (`ระดับน้ำผิวถนน`): only `wl_up`, unit ม.
  * `rf.value.day` = rainfall accumulated today (มม.).
  * `date` is Asia/Bangkok local time. Many stations have old dates (2019–2025) = dead sensors.
* Camera image: `GET http://182.52.224.70/MilestoneImageService/ImageService.svc/ImageService/GetImage?width=W&height=H&cameraname=<rawurlencode(name)>`
  → `image/jpeg` live snapshot (JPEG starts with bytes `FF D8`). `ondate` param is optional — omit it.

---------------------------------------------------------------------------------------------------------
## §2 Backend: new file `api/nonthaburi.php`

> **Status (2026-09-30):** implemented. Shared helpers now live in `api/lib.php` (`cache_dir()`, `fetch_url($url, $timeout)`,
> `to_json()`, `send_json()`, `$DEBUG`, `$FETCH_LOG`); `api/nonthaburi.php` requires it. §2.2 below describes the
> CURRENT image pipeline (revised twice: slow upstream → background refresher; bandwidth → thumbnails + versioned URLs).

Constants:
```
SRC_STATIONS = 'http://182.52.224.70/json.php?app=station'
SRC_IMAGE    = 'http://182.52.224.70/MilestoneImageService/ImageService.svc/ImageService/GetImage'
TTL_STATIONS = 60      // seconds
TTL_IMAGE    = 60
ONLINE_MAX_AGE = 7200  // station counts as online if `date` within 2h of now
REFRESH_PARALLEL = 4   // concurrent upstream snapshot requests (upstream takes 10-40 s per image)
REFRESH_BUDGET   = 55  // seconds one refresher may run
IMG_TIMEOUT      = 40  // seconds per snapshot
FAIL_BACKOFF     = 180 // seconds before retrying a camera whose last fetch failed
```

### §2.1 `GET api/nonthaburi.php` → station JSON

Algorithm:
1. `raw = cached(cache/nonthaburi_raw.json, TTL_STATIONS)` else `fetch_url(SRC_STATIONS)`; if fetched body is valid
   JSON with array key `station`, write it to cache (raw upstream body). If fetch fails: use cached file regardless of
   age and set `stale=true`; if no cache → HTTP 502 `{"error":"ดึงข้อมูลจากเทศบาลนครนนทบุรีไม่ได้ (เปิด api/nonthaburi.php?debug=1)"}`.
2. Normalize each station → output item:
   ```
   {
     "id": string, "code": string, "zone": string,
     "type": "road" if zone === "C" else "gate",
     "name": string,
     "lat": float|null, "lng": float|null,
     "date": string,                         // as upstream
     "online": bool,                         // strtotime(date) >= time() - ONLINE_MAX_AGE
     "cams": [ {"name": <cameraname>, "url": "api/nonthaburi.php?cam=" . rawurlencode(<cameraname>),
                "v": int} ],                  // v = mtime of the cached snapshot (0 = not cached yet)
     "wl_up":   null | {"now":float,"warning":float,"danger":float,"status":string,"trend":"up"|"down"|"flat"},
     "wl_down": null | same shape,
     "rf":      null | {"now":float,"day":float,"status":string}
   }
   ```
   * cameraname: for each cctv URL, take `parse_url($u, PHP_URL_QUERY)`, then extract the `cameraname` value with
     regex `/(?:^|&)cameraname=([^&]*)/` and `urldecode()` it (do NOT use parse_str — keep raw spaces). Skip if empty.
   * trend: `now > old` → up, `now < old` → down, else flat (treat missing old as flat).
   * floats via `(float)`; null-safe.
3. Response 200: `{"fetched_at": ISO8601, "stale": bool, "stations": [...]}` with headers
   `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-cache`.
4. After the response is sent (`fastcgi_finish_request`), if any snapshot is older than TTL_IMAGE → run the
   background refresher (§2.2). Pages request this JSON every minute, so this is what keeps snapshots fresh
   (browsers no longer re-request unchanged images).

### §2.2 `GET api/nonthaburi.php?cam=<name>[&w=480][&v=<mtime>]` → JPEG proxy

1. **Whitelist (security, mandatory):** load the station list the same way as §2.1 step 1 (cache OK); collect all
   camera names; if `$_GET['cam']` is not exactly one of them → HTTP 404, empty body. Never fetch arbitrary names/URLs.
2. Upstream is always requested at width=800,height=450 — Milestone returns a blue "no image" placeholder for
   other sizes. Cache file: `<cache_dir>/cam_<md5(name|800)>.jpg`.
3. If the cache file exists: serve it immediately (`&w=480` → 480 px thumbnail made with GD, cached as
   `cam_<md5>_480.jpg`, regenerated when the 800 px file is newer; no GD → full image).
   Headers: `&v=` equal to the file's mtime → `Cache-Control: public, max-age=31536000, immutable` (versioned URL);
   otherwise `public, max-age=30`. If the file is older than TTL_IMAGE, continue with step 5 after the response.
4. No cache yet: send a tiny 16:9 SVG "กำลังโหลดภาพ…" (`Cache-Control: no-store`); the page retries it.
   (Without php-fpm: fetch this one camera synchronously instead.)
5. **Background refresher** (`refresh_cams`, after `fastcgi_finish_request`): only one runs at a time (non-blocking
   `flock` on `cam_refresh.lock`); queue = stale/missing cameras, oldest first, skipping cameras with a `.fail` marker
   younger than FAIL_BACKOFF; fetch REFRESH_PARALLEL at a time with `curl_multi` until REFRESH_BUDGET.
   Success (HTTP 200, JPEG, > 1000 bytes) → save and remove `.fail`; failure → touch `<file>.fail`.
   Save rule: if both the new and the cached image are the blue offline placeholder (GD check: ≥80% pure-blue sample
   pixels below the text rows), keep the old file so its mtime (= version) does not change.
6. No writable cache dir: fetch and echo directly (no caching).

### §2.3 `GET api/nonthaburi.php?debug=1` → text/plain diagnostics
Print: PHP version, curl yes/NO, php-fpm yes/no, GD yes/NO, cache dir, fetch log for the station URL (method, http, bytes, error, elapsed),
counts: stations, stations online, cameras. Then fetch ONE camera image (first camera) and print http/bytes/jpeg-ok.
End with `RESULT: OK` or `RESULT: FAIL`.

---------------------------------------------------------------------------------------------------------
## §3 Frontend: edits to `index.php`

Do NOT change existing behaviour of the Nonthaburi HLS card, Pak Kret card, history card, or their JS
(`loadPakkret`, `renderChart`, etc.). Only ADD. Reuse existing CSS tokens (`--bg --card --text --muted --line
--accent --ok --warn --crit`) and existing classes (`.card`, `.card.wide`, `.body`, `.tabs`, `.meta`, `.table-wrap`,
`.dot`, `.badge`, `[hidden]`). Dark mode must keep working (tokens only, no hard-coded light colors except the
lightbox backdrop `rgba(0,0,0,.85)` and gray `#6b7280` for "black" status).

### §3.1 PHP variables (top of file, next to `$nonthaburiStream`)
```php
$fbLiveUrl = 'https://www.facebook.com/thaitcity/videos/2092790871327298/';
```

### §3.2 Sticky section nav
Right after `</header>`: `<nav class="secnav">` with anchor links:
`#river` "ท่าน้ำ", `#history` "ระดับน้ำย้อนหลัง", `#fblive` "ไลฟ์สด", `#nbcctv` "CCTV นนทบุรี", `#stations` "สถานีวัดน้ำ".
Add matching `id`s: first existing card (ท่าน้ำนนทบุรี) gets `id="river"`, existing history card gets `id="history"`.
Style: `position:sticky; top:0; z-index:10; background:var(--bg)`; links as pill buttons like `.tabs button`;
single row with `overflow-x:auto` on mobile; `max-width:1280px; margin:0 auto; padding` aligned with `main`.
Add `html { scroll-behavior:smooth; scroll-padding-top:56px; }`.

### §3.3 Facebook Live card (append inside `<main>` after history card)
```html
<section class="card wide" id="fblive">
  <h2>📺 ถ่ายทอดสด <span class="live">LIVE</span></h2>
  <div class="fbwrap"><iframe src="https://www.facebook.com/plugins/video.php?href=<?= rawurlencode($fbLiveUrl) ?>&show_text=false&width=1280"
     title="Facebook Live" loading="lazy" scrolling="no" frameborder="0" allowfullscreen
     allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe></div>
  <div class="body"><div class="meta">ที่มา: <a href="<?= htmlspecialchars($fbLiveUrl) ?>" target="_blank" rel="noopener">เปิดบน Facebook</a>
     · หากไลฟ์จบแล้ว วิดีโออาจแสดงเป็นย้อนหลังหรือไม่พร้อมใช้งาน</div></div>
</section>
```
`.fbwrap { position:relative; aspect-ratio:16/9; background:#000 }` `.fbwrap iframe { position:absolute; inset:0; width:100%; height:100% }`.

### §3.4 CCTV grid card
```html
<section class="card wide" id="nbcctv">
  <h2>📷 CCTV เทศบาลนครนนทบุรี <small id="nbCamCount" class="meta"></small></h2>
  <div class="body">
    <div class="nbtools">
      <div class="tabs" id="nbZone"> buttons data-z="": "ทั้งหมด" (active), "A":"โซน A", "B":"โซน B", "C":"โซน C (ผิวถนน)" </div>
      <input id="nbSearch" type="search" placeholder="ค้นหาสถานี เช่น A1 หรือ คลอง…">
    </div>
    <div class="camgrid" id="nbGrid"></div>
    <div class="meta" id="nbMsg"></div>
    <div class="meta">ที่มา: <a href="http://182.52.224.70/?page=cctv" target="_blank" rel="noopener">ระบบศูนย์ป้องกันและแก้ไขปัญหาน้ำท่วม เทศบาลนครนนทบุรี</a> · ภาพอัปเดตทุก 1 นาที</div>
  </div>
</section>
```
* One tile per camera of every station with `cams.length > 0`, in upstream order.
  Tile markup: `<figure class="cam" data-zone data-text="<code> <name>">` containing `<img alt loading="lazy" data-src="<cam.url>&w=480&v=<cam.v>">`
  (revised: versioned thumbnail URL; after each JSON refresh `nbSyncVersions` swaps `data-src` for cameras whose `v`
  changed and reloads only those that are on screen; placeholder SVG (naturalWidth < 100) is retried with `&r=`)
  and `<figcaption>` = `<code> <name>` + (` · Cam<i+1>` if station has >1 cam) + a status dot for the station's worst status (§3.6).
* Images load only when visible: IntersectionObserver (rootMargin `200px`) sets `img.src = data-src` (versioned URL, so the
  browser cache serves unchanged snapshots). Fallback if no IntersectionObserver: set src immediately.
* `img.onerror` → add class `err` to figure; CSS shows "ภาพไม่พร้อมใช้งาน" text (via `figure.cam.err::after`) and hides img.
* Zone buttons + search filter tiles by toggling `hidden` (search: case-insensitive substring on `data-text`).
* Grid CSS: `display:grid; gap:10px; grid-template-columns:repeat(auto-fill,minmax(220px,1fr))`; at ≤600px
  `minmax(150px,1fr)`. Image box `aspect-ratio:16/9; object-fit:cover; background:#000; border-radius:8px; width:100%`.
  Caption `font-size:.85rem`, ellipsis on one line.
* Click tile → lightbox: fixed full-screen overlay (`rgba(0,0,0,.85)`, z-index 50) with the 800px image
  (full 800 px: `cam.url + '&v=' + cam.v`), caption, the station's readings line (§3.6 format), and a close button (×).
  Close on ×, backdrop click, or Esc. `<body>` scroll locked while open (`overflow:hidden`).
* `#nbCamCount` text: `(<N> กล้อง)`.

### §3.5 Stations table card
```html
<section class="card wide" id="stations">
  <h2>🌊 สถานีวัดระดับน้ำ เทศบาลนครนนทบุรี</h2>
  <div class="body">
    <div class="chips" id="nbChips"></div>
    <div class="table-wrap"><table><thead><tr>
      <th>สถานี</th><th>ประเภท</th><th>หน้า / ผิวถนน (ม.)</th><th>ท้าย (ม.)</th><th>ฝนวันนี้ (มม.)</th><th>สถานะ</th><th>เวลา</th>
    </tr></thead><tbody id="nbTable"></tbody></table></div>
    <div class="meta" id="nbNote"></div>
  </div>
</section>
```
* Rows: stations with `online === true` and (`wl_up` or `wl_down` not null).
* Cells: `<code> <name>`; type `ประตูระบายน้ำ` / `ผิวถนน`; wl_up.now `.toFixed(2)` + trend arrow (`▲` up, `▼` down,
  none flat) colored by that sensor's status; wl_down likewise or `–`; `rf.day` `.toFixed(1)` or `–`; status dot+text
  of worst status; time = last 5 chars of `date` (HH:MM).
* Sort: worst status rank (danger 0, warning 1, black 2, success 3, white 4), then by `code` natural order
  (`localeCompare(b, 'th', {numeric:true})`).
* `#nbChips`: pills `วิกฤต N`, `เฝ้าระวัง N`, `ปกติ N`, `ขาดการติดต่อ N` (counts over the shown rows, omit zero counts),
  each with its status color as background (text color: white, except warning black).
* `#nbNote`: `ไม่แสดง <K> สถานีที่ไม่ได้อัปเดตเกิน 2 ชั่วโมง` where K = stations not online.

### §3.6 Status helpers (JS)
```
NB_STATUS = { success:{t:'ปกติ',c:'var(--ok)'}, warning:{t:'เฝ้าระวัง',c:'var(--warn)'}, danger:{t:'วิกฤต',c:'var(--crit)'},
              black:{t:'ขาดการติดต่อ',c:'#6b7280'}, white:{t:'ไม่มีข้อมูล',c:'var(--line)'} }   // unknown → white
worstStatus(station) = min-rank status among non-null wl_up, wl_down (rank as §3.5); none → 'white'
readings line (lightbox): "หน้า 0.66 ม. · ท้าย 2.21 ม. · ฝนวันนี้ 43.8 มม. · อัปเดต 10:15"   (omit absent parts; road type: "ผิวถนน 0.90 ม.")
```
* Add `esc(s)` HTML-escape helper and use it for EVERY upstream string inserted via innerHTML (names, codes, dates).

### §3.7 Data loading
`loadNonthaburi()` — same pattern as `loadPakkret`: `fetch('api/nonthaburi.php', {cache:'no-store'})`, read `text()`,
`JSON.parse` in try/catch (non-JSON → message `api/nonthaburi.php ตอบกลับผิดรูปแบบ (HTTP <status>)`), non-OK → `d.error`.
Errors shown in `#nbMsg`. On success: build grid only the FIRST time (or if the camera set changed — compare joined
cam names); always re-render table/chips/counts. If `d.stale` → `#nbMsg` = `ข้อมูลสถานีเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)`.
Call once on load and `setInterval(loadNonthaburi, 60000)`. Independent from Pak Kret — one failing must not break the other.

### §3.8 Footer
Append: ` · CCTV/สถานี: เทศบาลนครนนทบุรี · ไลฟ์: Facebook`.

---------------------------------------------------------------------------------------------------------
## §4 Acceptance / test plan (run by reviewer)
1. `php -l` both files on PHP 8.3 and 7.0.
2. `api/nonthaburi.php` → 200 JSON, 41 stations, ≥30 cams, cams[].url decodable, online count ≈ 24.
3. `?cam=<valid>` → 200 image/jpeg starting FFD8; `&w=400` smaller; second call within 60 s served from cache.
4. `?cam=evil` and `?cam=http://x` → 404 (whitelist).
5. `?debug=1` → `RESULT: OK`.
6. Page at 1280px and 375px: nav sticky, FB iframe 16:9, grid 2 cols on phone / ≥4 on desktop, table scrolls,
   no horizontal page scroll (scrollWidth == innerWidth), lightbox opens/closes (Esc, backdrop, ×).
7. Upstream station name containing `<b>` rendered as text (esc).
8. Existing Pak Kret + history cards unchanged and still working.
