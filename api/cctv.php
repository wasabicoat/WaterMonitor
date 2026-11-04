<?php
/**
 * All-source CCTV list from Flood CCTV Thailand (cctv.maholan.net), which aggregates กทม., กรมทางหลวง,
 * ปภ., iTIC, ThaiWater, เทศบาลนครปากเกร็ด/นนทบุรี and more. Dead cameras are dropped and rows are
 * compacted (dead ones are kept only when they have coordinates, so the map can show them in grey).
 * Pak Kret cameras have no coordinates there, so they are filled from thaiclouderp's map page
 * (api/pakkret_coords.json is the bundled fallback); Pak Kret cameras maholan lacks are added from it too.
 * Snapshots are loaded by the browser straight from the maholan CDN — we never proxy images.
 *
 * Duplicates (same name at the same spot, often listed under 2-3 ids) keep only the best copy: fresh before
 * unknown before stale, then live stream (hls/mjpeg) over plain snapshot. Cameras that this page already shows
 * better elsewhere (นนทบุรี grid with water levels, live streams) are skipped.
 *
 *   api/cctv.php          -> JSON: fetched_at, stale, cams[[id, name, region, district, source, lat, lng, old, img?]]
 *                            old = 1 when upstream reports the image as not updating recently, 2 = dead;
 *                            img = snapshot URL prefix when the image is not on the maholan CDN
 *                            blank = ids whose latest snapshot is (almost) one solid colour = camera offline
 *   api/cctv.php?debug=1  -> diagnostics
 *
 * Blank detection: after the response is sent (php-fpm only), one worker at a time downloads a batch of
 * snapshots from the maholan CDN and checks them with GD. Results live in cache/cctv_blank.json; cameras
 * found blank are re-checked sooner so they reappear soon after they recover.
 */
require __DIR__ . '/lib.php';

define('MH_URL', 'https://cctv.maholan.net/api/cameras');
define('PK_URL', 'https://www.thaiclouderp.com/CCTV_MONITOR/web/pakkred');
define('MH_TTL', 600);      // camera list changes rarely
define('PK_TTL', 86400);
define('BLANK_BATCH', 40);         // snapshots checked per background run
define('BLANK_PARALLEL', 6);
define('BLANK_RECHECK_OK', 10800); // seconds before re-checking a camera that looked fine
define('BLANK_RECHECK_BAD', 1200); // ... one that was blank (so it comes back quickly)
define('BLANK_SHARE', 0.93);       // share of pixels close to the average colour => blank

// Nearby provinces first, the rest after (alphabetical)
$LOCAL = array('นนทบุรี' => 0, 'ปทุมธานี' => 1, 'กรุงเทพมหานคร' => 2);
// Already on this page in a better form (see #nbcctv and the live-stream cards)
$SKIP_SOURCES = array('เทศบาลนครนนทบุรี' => 1, 'เทศบาลนครนนทบุรี / FirstTech' => 1, 'เทศบาลเมืองปทุมธานี' => 1);
$FRESH_RANK = array('fresh' => 0, 'unknown' => 1, 'stale' => 2, 'dead' => 3);
$OLD = array('fresh' => 0, 'dead' => 2);   // anything else = 1

/** Pak Kret camera number => [lat, lng, title], from the `tourStops` array on thaiclouderp's map page. */
function pk_coords($dir) {
    $f = $dir ? "$dir/pakkret_coords.json" : null;
    if ($f && is_file($f) && time() - filemtime($f) < PK_TTL) return json_decode(file_get_contents($f), true);
    $html = fetch_url(PK_URL, 20);
    $out = array();
    if ($html !== null && preg_match('/const\s+tourStops\s*=\s*(\[.*?\]);/s', $html, $m)) {
        foreach ((array)json_decode($m[1], true) as $s) {
            if (isset($s[0]['lat'], $s[2])) $out[(int)$s[2]] = array(round($s[0]['lat'], 5), round($s[0]['lng'], 5), trim($s[1]));
        }
    }
    if (!$out && $f && is_file($f)) $out = json_decode(file_get_contents($f), true);   // keep last good copy
    if (!$out) $out = json_decode(file_get_contents(__DIR__ . '/pakkret_coords.json'), true);   // bundled copy
    // Write (or touch) even after a failed fetch, so an unreachable site costs one 20 s wait per day, not per request
    if ($f) @file_put_contents($f, to_json($out), LOCK_EX);
    return $out;
}

function cctv_scrape($dir) {
    global $LOCAL, $SKIP_SOURCES, $FRESH_RANK, $OLD;
    $body = fetch_url(MH_URL, 25);
    $d = $body !== null ? json_decode($body, true) : null;
    // maholan down: still return the Pak Kret cameras (from $pk below) rather than nothing
    $partial = !isset($d['cameras']) || !is_array($d['cameras']);
    if ($partial) $d = array('cameras' => array());
    $pk = pk_coords($dir);
    $best = array();   // dedupe key => [rank, row]
    foreach ($d['cameras'] as $c) {
        $fr = isset($c['fr']['s']) ? $c['fr']['s'] : 'unknown';
        $src = isset($c['source']) ? $c['source'] : '';
        if (!isset($c['id'], $c['name']) || isset($SKIP_SOURCES[$src])) continue;
        $lat = isset($c['lat']) ? round($c['lat'], 5) : null;
        if ($fr === 'dead' && $lat === null && !preg_match('/^pakkret-/', $c['id'])) continue;   // nowhere to show it
        $lng = isset($c['lng']) ? round($c['lng'], 5) : null;
        $district = isset($c['district']) ? $c['district'] : '';
        if (preg_match('/^pakkret-(\d+)$/', $c['id'], $m)) {
            $district = 'ปากเกร็ด';
            if ($lat === null && isset($pk[(int)$m[1]])) list($lat, $lng) = $pk[(int)$m[1]];
            unset($pk[(int)$m[1]]);   // left in $pk = cameras maholan doesn't list
        }
        $name = trim($c['name']);
        $region = isset($c['region']) ? $c['region'] : '';
        $key = preg_replace('/[\s\-–.,()]+/u', '', $name) . '|' . ($lat !== null ? round($lat, 3) . ',' . round($lng, 3) : $region);
        $rank = (isset($FRESH_RANK[$fr]) ? $FRESH_RANK[$fr] : 1) * 2 + (isset($c['type']) && $c['type'] !== 'snapshot' ? 0 : 1);
        if (isset($best[$key]) && $best[$key][0] <= $rank) continue;
        $best[$key] = array($rank, array($c['id'], $name, $region, $district, $src, $lat, $lng, isset($OLD[$fr]) ? $OLD[$fr] : 1));
    }
    foreach ((array)$pk as $n => $p) {   // not in maholan: image straight from thaiclouderp (what its own page loads)
        $best['pk' . $n] = array(0, array(sprintf('pakkret-%03d', $n), $p[2], 'นนทบุรี', 'ปากเกร็ด', 'เทศบาลนครปากเกร็ด',
            $p[0], $p[1], 0, sprintf('https://www.thaiclouderp.com/src/img.php?name=CAMPK%03d_thumb.jpg&', $n)));
    }
    if (!$best) return null;
    $cams = array();
    foreach ($best as $b) $cams[] = $b[1];
    usort($cams, function ($a, $b) use ($LOCAL) {
        $ra = isset($LOCAL[$a[2]]) ? $LOCAL[$a[2]] : 9;
        $rb = isset($LOCAL[$b[2]]) ? $LOCAL[$b[2]] : 9;
        if ($ra !== $rb) return $ra - $rb;
        if ($a[2] !== $b[2]) return strcmp($a[2], $b[2]);
        if ($a[7] !== $b[7]) return $a[7] - $b[7];   // not-updating cameras last within a province
        return strcmp($a[4] . $a[3], $b[4] . $b[3]) ?: strnatcmp($a[0], $b[0]);
    });
    return array('fetched_at' => date('c'), 'source' => 'https://cctv.maholan.net/', 'partial' => $partial, 'cams' => $cams);
}

/**
 * True if the image is (almost) a single colour, e.g. a blue "no signal" frame. Tuned on real snapshots:
 * blank frames score 0.96, normal scenes 0.08-0.72, fog/flooded canals up to 0.88. Near-black frames are
 * not counted (could be a dark night scene). null = not an image / no GD.
 */
function is_blank_image($b) {
    if (!function_exists('imagecreatefromstring')) return null;
    $im = @imagecreatefromstring($b);
    if (!$im) return null;
    $w = 32; $h = 18;
    $sm = imagecreatetruecolor($w, $h);
    imagecopyresampled($sm, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));
    imagedestroy($im);
    $px = array(); $sr = $sg = $sb = 0;
    for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
        $c = imagecolorat($sm, $x, $y);
        $p = array(($c >> 16) & 255, ($c >> 8) & 255, $c & 255);
        $px[] = $p; $sr += $p[0]; $sg += $p[1]; $sb += $p[2];
    }
    imagedestroy($sm);
    $n = count($px); $mr = $sr / $n; $mg = $sg / $n; $mb = $sb / $n;
    $near = 0;
    foreach ($px as $p) if (max(abs($p[0] - $mr), abs($p[1] - $mg), abs($p[2] - $mb)) < 28) $near++;
    $lum = 0.299 * $mr + 0.587 * $mg + 0.114 * $mb;
    $chroma = max($mr, $mg, $mb) - min($mr, $mg, $mb);
    if ($lum < 40 && $chroma < 25) return false;
    return $near / $n >= BLANK_SHARE;
}

/** id => [blank 0/1, checked unix time] */
function blank_state($dir) {
    $f = $dir ? "$dir/cctv_blank.json" : null;
    $s = $f && is_file($f) ? json_decode(file_get_contents($f), true) : null;
    return is_array($s) ? $s : array();
}

/** Check the cameras most overdue for a look; one worker at a time (non-blocking flock). */
function blank_check($dir, $cams) {
    if (!$dir || !function_exists('curl_multi_init') || !function_exists('imagecreatefromstring')) return;
    $lock = @fopen("$dir/cctv_blank.lock", 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;
    $state = blank_state($dir);
    $now = time(); $due = array();
    foreach ($cams as $c) {
        if ($c[7] >= 2 || isset($c[8])) continue;   // dead, or image not on the maholan CDN
        $s = isset($state[$c[0]]) ? $state[$c[0]] : null;
        $wait = $s && $s[0] ? BLANK_RECHECK_BAD : BLANK_RECHECK_OK;
        if (!$s || $now - $s[1] >= $wait) $due[$c[0]] = $s ? $s[1] : 0;
    }
    asort($due);                                    // never checked / oldest first
    $queue = array_slice(array_keys($due), 0, BLANK_BATCH);
    $bucket = (int)floor($now / 60);                // same URL the browsers use => CDN cache hit
    $mh = curl_multi_init(); $active = array();
    while ($queue || $active) {
        while (count($active) < BLANK_PARALLEL && $queue) {
            $id = array_shift($queue);
            $ch = curl_init('https://cctv.maholan.net/api/snap/' . rawurlencode($id) . '?t=' . $bucket);
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (WaterMonitor)'));
            curl_multi_add_handle($mh, $ch);
            $active[(int)$ch] = array($ch, $id);
        }
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 1.0);
        while ($info = curl_multi_info_read($mh)) {
            list($ch, $id) = $active[(int)$info['handle']];
            $body = curl_multi_getcontent($ch);
            $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 ? is_blank_image($body) : null;
            if ($ok !== null) $state[$id] = array($ok ? 1 : 0, $now);
            curl_multi_remove_handle($mh, $ch); curl_close($ch);
            unset($active[(int)$info['handle']]);
        }
    }
    curl_multi_close($mh);
    @file_put_contents("$dir/cctv_blank.json", to_json($state), LOCK_EX);
    flock($lock, LOCK_UN);
}

$dir   = cache_dir();
$cache = $dir ? "$dir/cctv.json" : null;

if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    $d = cctv_scrape($dir);
    foreach ($FETCH_LOG as $l) echo '  - ' . to_json($l) . "\n";
    echo $d ? "\ncams: " . count($d['cams']) . ($d['partial'] ? " (Pak Kret only — maholan failed)" : '') . "\nRESULT: OK\n" : "\nRESULT: FAIL\n";
    exit;
}

$json = null; $stale = false;
if ($cache && is_file($cache) && time() - filemtime($cache) < MH_TTL) $json = file_get_contents($cache);
if ($json === null) {
    $d = cctv_scrape($dir);
    if ($d && !$d['partial']) {
        $json = to_json($d);
        if ($cache) @file_put_contents($cache, $json, LOCK_EX);
    } elseif ($cache && is_file($cache)) {   // last full list beats a Pak Kret-only one
        $json = file_get_contents($cache);
        $stale = true;
    } elseif ($d) {
        $json = to_json($d);
        $stale = true;
    }
}
if ($json === null) send_json(array('error' => 'ดึงรายชื่อกล้องจาก cctv.maholan.net ไม่ได้ (เปิด api/cctv.php?debug=1)'), 502);

$blank = array();
foreach (blank_state($dir) as $id => $s) if ($s[0]) $blank[] = (string)$id;

// ~2,400 rows: gzip cuts the payload ~10x; browsers may reuse it for 5 minutes
if (!ini_get('zlib.output_compression') && extension_loaded('zlib')) ob_start('ob_gzhandler');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo substr($json, 0, -1) . ($stale ? ',"stale":true' : '') . ',"blank":' . to_json($blank) . '}';

// Background blank check, only once the visitor already has the response
if (function_exists('fastcgi_finish_request')) {
    while (ob_get_level()) ob_end_flush();
    fastcgi_finish_request();
    $d = json_decode($json, true);
    if (isset($d['cams'])) blank_check($dir, $d['cams']);
}
