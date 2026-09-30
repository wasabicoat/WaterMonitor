<?php
/**
 * Proxy for the Nonthaburi municipality (เทศบาลนครนนทบุรี) water-level stations
 * and CCTV snapshots (182.52.224.70).
 *
 *   api/nonthaburi.php            -> JSON: fetched_at, stale, stations[]
 *   api/nonthaburi.php?cam=<name> -> latest CCTV snapshot (image/jpeg)
 *   api/nonthaburi.php?debug=1    -> diagnostics page
 *
 * Upstream is plain HTTP (JSON + a JPEG image service). Proxying keeps the page
 * working if it is ever served over HTTPS (no mixed content) and caches results
 * on disk, so N visitors don't mean N upstream requests.
 *
 * Camera snapshots take 10-25 s each upstream. Requests never wait for that:
 * they get the cached image (or a small "loading" placeholder) immediately, and
 * a single background refresher (one PHP worker, guarded by flock) fetches stale
 * cameras in parallel with curl_multi after the response has been sent.
 */
require __DIR__ . '/lib.php';

define('SRC_STATIONS', 'http://182.52.224.70/json.php?app=station');
define('SRC_IMAGE',    'http://182.52.224.70/MilestoneImageService/ImageService.svc/ImageService/GetImage');
define('TTL_STATIONS', 60);       // seconds
define('TTL_IMAGE',    60);       // seconds
define('ONLINE_MAX_AGE', 7200);   // station counts as online if `date` within 2h of now
define('REFRESH_PARALLEL', 6);    // concurrent upstream snapshot requests
define('REFRESH_BUDGET', 55);     // seconds a refresher may run
define('IMG_TIMEOUT', 40);        // seconds per snapshot

/** Load the station list (raw upstream body) with a short cache; returns array|false. */
function load_stations($dir, &$stale) {
    $stale = false;
    $cache = $dir ? "$dir/nonthaburi_raw.json" : null;

    if ($cache && is_file($cache) && (time() - filemtime($cache) < TTL_STATIONS)) {
        $j = json_decode(file_get_contents($cache), true);
        if (is_array($j) && isset($j['station']) && is_array($j['station'])) return $j['station'];
    }

    $body = fetch_url(SRC_STATIONS);
    $j = $body !== null ? json_decode($body, true) : null;
    if (is_array($j) && isset($j['station']) && is_array($j['station'])) {
        if ($cache) @file_put_contents($cache, $body, LOCK_EX);   // raw upstream body
        return $j['station'];
    }

    // Fetch failed: fall back to cached file regardless of age
    if ($cache && is_file($cache)) {
        $stale = true;
        $cj = json_decode(file_get_contents($cache), true);
        if (is_array($cj) && isset($cj['station']) && is_array($cj['station'])) return $cj['station'];
    }
    return false;
}

/** Extract the cameraname from a cctv URL query string (keeps raw spaces). */
function cam_name($u) {
    $q = parse_url($u, PHP_URL_QUERY);
    if ($q === null || $q === false) return null;
    if (!preg_match('/(?:^|&)cameraname=([^&]*)/', $q, $m)) return null;
    $name = urldecode($m[1]);
    return $name === '' ? null : $name;
}

/** Build a sensor block from an upstream data.X entry, or null if absent. */
function sensor($node) {
    if (!is_array($node) || empty($node['enable'])) return null;
    $v = isset($node['value']) ? $node['value'] : null;
    if (!is_array($v)) return null;
    $now = isset($v['now']) ? (float)$v['now'] : 0.0;
    $old = isset($v['old']) && $v['old'] !== null ? (float)$v['old'] : null;
    if ($old === null)      $trend = 'flat';
    elseif ($now > $old)    $trend = 'up';
    elseif ($now < $old)    $trend = 'down';
    else                    $trend = 'flat';
    return array(
        'now'     => $now,
        'warning' => isset($v['warning']) ? (float)$v['warning'] : 0.0,
        'danger'  => isset($v['danger']) ? (float)$v['danger'] : 0.0,
        'status'  => isset($v['status']) ? (string)$v['status'] : 'white',
        'trend'   => $trend,
    );
}

/** Normalize one upstream station into the output shape. */
function normalize_station($s) {
    $zone = isset($s['zone']) ? (string)$s['zone'] : '';
    $loc  = isset($s['location']) && is_array($s['location']) ? $s['location'] : array();
    $date = isset($s['date']) ? (string)$s['date'] : '';

    $cams = array();
    if (isset($s['cctv']) && is_array($s['cctv'])) {
        foreach ($s['cctv'] as $u) {
            $name = cam_name($u);
            if ($name === null) continue;
            $cams[] = array('name' => $name, 'url' => 'api/nonthaburi.php?cam=' . rawurlencode($name));
        }
    }

    $d = isset($s['data']) && is_array($s['data']) ? $s['data'] : array();
    $rf = null;
    if (isset($d['rf']) && is_array($d['rf']) && !empty($d['rf']['enable']) && is_array($d['rf']['value'])) {
        $rv = $d['rf']['value'];
        $rf = array(
            'now'    => isset($rv['now']) ? (float)$rv['now'] : 0.0,
            'day'    => isset($rv['day']) ? (float)$rv['day'] : 0.0,
            'status' => isset($rv['status']) ? (string)$rv['status'] : 'white',
        );
    }

    $ts = strtotime($date);
    return array(
        'id'      => isset($s['id']) ? (string)$s['id'] : '',
        'code'    => isset($s['code']) ? (string)$s['code'] : '',
        'zone'    => $zone,
        'type'    => ($zone === 'C') ? 'road' : 'gate',
        'name'    => isset($s['name']) ? (string)$s['name'] : '',
        'lat'     => isset($loc['lat']) ? (float)$loc['lat'] : null,
        'lng'     => isset($loc['lng']) ? (float)$loc['lng'] : null,
        'date'    => $date,
        'online'  => ($ts !== false && $ts >= time() - ONLINE_MAX_AGE),
        'cams'    => $cams,
        'wl_up'   => isset($d['wl_up']) ? sensor($d['wl_up']) : null,
        'wl_down' => isset($d['wl_down']) ? sensor($d['wl_down']) : null,
        'rf'      => $rf,
    );
}

/** All camera names from the station list (the whitelist for ?cam=). */
function all_cam_names($stations) {
    $names = array();
    foreach ($stations as $s) foreach (normalize_station($s)['cams'] as $c) $names[] = $c['name'];
    return $names;
}

/** Upstream snapshot URL. Always 800x450: other sizes return a blue "no image" placeholder. */
function cam_src($name) {
    return SRC_IMAGE . '?width=800&height=450&cameraname=' . rawurlencode($name);
}

function cam_file($dir, $name) {
    return "$dir/cam_" . md5($name . '|800') . '.jpg';
}

function is_jpeg($b) {
    return is_string($b) && strlen($b) > 1000 && strpos($b, "\xFF\xD8") === 0;
}

/**
 * Refresh stale/missing snapshots, oldest first, REFRESH_PARALLEL at a time, within REFRESH_BUDGET.
 * Only one refresher runs at a time across all PHP workers (non-blocking flock).
 */
function refresh_cams($dir, $names) {
    $lockFh = @fopen("$dir/cam_refresh.lock", 'c');
    if (!$lockFh || !flock($lockFh, LOCK_EX | LOCK_NB)) return;

    $queue = array();
    foreach ($names as $n) {
        $f = cam_file($dir, $n);
        $age = is_file($f) ? time() - filemtime($f) : PHP_INT_MAX;
        if ($age >= TTL_IMAGE) $queue[$n] = $age;
    }
    arsort($queue);                     // missing / oldest first
    $queue = array_keys($queue);
    $deadline = time() + REFRESH_BUDGET;

    if (!function_exists('curl_multi_init')) {
        foreach ($queue as $n) {
            if (time() > $deadline) break;
            $b = fetch_url(cam_src($n), IMG_TIMEOUT);
            if (is_jpeg($b)) @file_put_contents(cam_file($dir, $n), $b, LOCK_EX);
        }
    } else {
        $mh = curl_multi_init();
        $active = array();              // (int)handle => name
        $add = function () use (&$queue, &$active, $mh, $deadline) {
            while (count($active) < REFRESH_PARALLEL && $queue && time() < $deadline) {
                $n = array_shift($queue);
                $ch = curl_init(cam_src($n));
                curl_setopt_array($ch, array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT        => IMG_TIMEOUT,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 (WaterMonitor)',
                ));
                curl_multi_add_handle($mh, $ch);
                $active[(int)$ch] = array($ch, $n);
            }
        };
        $add();
        while ($active) {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 1.0);
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                list(, $n) = $active[(int)$ch];
                $b = curl_multi_getcontent($ch);
                if ($info['result'] === CURLE_OK && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && is_jpeg($b)) {
                    @file_put_contents(cam_file($dir, $n), $b, LOCK_EX);
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($active[(int)$ch]);
                $add();
            }
        }
        curl_multi_close($mh);
    }
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
}

/** Small 16:9 SVG shown until a camera's first snapshot is cached (JS retries it). */
function placeholder_svg() {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="9" viewBox="0 0 160 90">'
       . '<rect width="160" height="90" fill="#1c2733"/>'
       . '<text x="80" y="50" fill="#9aa8b5" font-family="sans-serif" font-size="11" text-anchor="middle">กำลังโหลดภาพ…</text></svg>';
}

function finish_response() {
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); return true; }
    return false;
}

// ---------------------------------------------------------------------------

$dir = cache_dir();

// --- diagnostics ------------------------------------------------------------
if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "WaterMonitor (Nonthaburi) diagnostics\n=====================================\n";
    echo 'PHP ' . PHP_VERSION . ' · cURL ' . (function_exists('curl_init') ? 'yes' : 'NO')
       . ' · php-fpm ' . (function_exists('fastcgi_finish_request') ? 'yes' : 'no (images fetched synchronously)') . "\n";
    echo 'Cache folder: ' . ($dir ? $dir : 'NONE writable') . "\n\n";

    @unlink($dir ? "$dir/nonthaburi_raw.json" : '');   // force a live fetch
    $stale = false;
    $stations = load_stations($dir, $stale);
    foreach ($FETCH_LOG as $l) echo 'Station fetch: ' . to_json($l) . "\n";
    $ok = $stations !== false;
    if ($ok) {
        $names = all_cam_names($stations);
        $online = 0;
        foreach ($stations as $s) if (normalize_station($s)['online']) $online++;
        echo "\nStations: " . count($stations) . " · online: $online · cameras: " . count($names) . "\n";
        $fresh = 0;
        if ($dir) foreach ($names as $n) { $f = cam_file($dir, $n); if (is_file($f) && time() - filemtime($f) < 300) $fresh++; }
        echo "Cached snapshots < 5 min old: $fresh / " . count($names) . "\n";
        if ($names) {
            $b = fetch_url(cam_src($names[0]), IMG_TIMEOUT);
            $l = end($FETCH_LOG);
            echo 'Snapshot test (' . $names[0] . '): ' . to_json($l) . ' jpeg=' . (is_jpeg($b) ? 'yes' : 'NO') . "\n";
            if (!is_jpeg($b)) $ok = false;
        }
    }
    echo "\nRESULT: " . ($ok ? 'OK' : 'FAIL') . "\n";
    exit;
}

// --- camera snapshot proxy ----------------------------------------------------
if (isset($_GET['cam'])) {
    $name = (string)$_GET['cam'];
    $stale = false;
    $stations = load_stations($dir, $stale);
    $names = $stations !== false ? all_cam_names($stations) : array();
    if (!in_array($name, $names, true)) { http_response_code(404); exit; }   // whitelist

    if (!$dir) {                                   // no cache folder: plain synchronous proxy
        $b = fetch_url(cam_src($name), IMG_TIMEOUT);
        if (!is_jpeg($b)) { http_response_code(502); exit; }
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=30');
        echo $b;
        exit;
    }

    $f = cam_file($dir, $name);
    $fresh = is_file($f) && time() - filemtime($f) < TTL_IMAGE;
    if (is_file($f)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=30');
        header('Content-Length: ' . filesize($f));
        readfile($f);
        if ($fresh) exit;
    } elseif (!function_exists('fastcgi_finish_request')) {
        // Dev server without php-fpm: fetch this one camera synchronously
        $b = fetch_url(cam_src($name), IMG_TIMEOUT);
        if (!is_jpeg($b)) { http_response_code(502); exit; }
        @file_put_contents($f, $b, LOCK_EX);
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=30');
        echo $b;
        exit;
    } else {
        placeholder_svg();
    }

    // Stale or missing: refresh in the background after the response is sent
    if (finish_response()) refresh_cams($dir, $names);
    exit;
}

// --- station JSON -------------------------------------------------------------
$stale = false;
$stations = load_stations($dir, $stale);
if ($stations === false) {
    send_json(array('error' => 'ดึงข้อมูลจากเทศบาลนครนนทบุรีไม่ได้ (เปิด api/nonthaburi.php?debug=1)'), 502);
}
$out = array();
foreach ($stations as $s) $out[] = normalize_station($s);
send_json(array('fetched_at' => date('c'), 'stale' => $stale, 'stations' => $out));
