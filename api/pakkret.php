<?php
/**
 * Proxy + scraper for the Pak Kret water-level sensor (pakkretconnect.com).
 * Pure PHP (5.6+), no other runtime needed.
 *
 *   api/pakkret.php            -> JSON: level, status, time, thresholds, overlay, history
 *   api/pakkret.php?fresh=1    -> same, but with a 15 s cache instead of 60 s (used on page load)
 *   api/pakkret.php?img=1      -> latest CCTV snapshot (image/jpeg)
 *   api/pakkret.php?debug=1    -> diagnostics page: shows why data can't be fetched on this server
 *
 * Upstream pages are server-rendered HTML, so we fetch and parse them here
 * (the browser can't: no CORS). Results are cached on disk for CACHE_TTL seconds
 * when a writable folder exists; without one it still works, just uncached.
 */

define('SRC_NOW',   'https://www.pakkretconnect.com/liffwater/eon');
define('SRC_GRAPH', 'https://www.pakkretconnect.com/liffwater/eonGraph');
define('CACHE_TTL', 60);

$DEBUG = isset($_GET['debug']);
// PHP warnings printed into the response would corrupt the JSON the page expects.
ini_set('display_errors', $DEBUG ? '1' : '0');
error_reporting(E_ALL);
@set_time_limit(60);
if (function_exists('date_default_timezone_set')) date_default_timezone_set('Asia/Bangkok');

$FETCH_LOG = array();   // per-request fetch diagnostics (shown in ?debug=1)

/** First writable folder among ../cache, ./cache, system temp; null if none. */
function cache_dir() {
    $candidates = array(__DIR__ . '/../cache', __DIR__ . '/cache', rtrim(sys_get_temp_dir(), '/\\') . '/watermonitor');
    foreach ($candidates as $d) {
        if (!is_dir($d)) @mkdir($d, 0775, true);
        if (is_dir($d) && is_writable($d)) return $d;
    }
    return null;
}

/** Fetch a URL with cURL if available, else file_get_contents. Returns body or null. */
function fetch_url($url) {
    global $FETCH_LOG;
    $log = array('url' => $url);
    // Plain UA on purpose: a Chrome UA combined with cURL's Accept-Encoding gets HTTP 403 from the site's bot filter.
    $ua = 'Mozilla/5.0 (WaterMonitor)';

    if (function_exists('curl_init')) {
        // Try with SSL verification first; some shared hosts have an outdated CA bundle, so retry without.
        foreach (array(true, false) as $verify) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_SSL_VERIFYPEER => $verify,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            ));
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            $log['method'] = 'curl' . ($verify ? '' : ' (no SSL verify)');
            $log['http'] = $code;
            $log['error'] = $err;
            $log['bytes'] = is_string($body) ? strlen($body) : 0;
            $FETCH_LOG[] = $log;
            if ($body !== false && $code === 200) return $body;
            if ($code !== 0) break;   // got an HTTP answer; SSL was not the problem
        }
    }

    if (!function_exists('curl_init') && !extension_loaded('openssl')) {
        // Without cURL, https:// needs OpenSSL; PHP would otherwise report a confusing "No such file or directory".
        $log['error'] = 'PHP ไม่มี cURL และไม่มี OpenSSL จึงเปิด https:// ไม่ได้ — เปิด extension curl และ openssl ใน PHP ของเซิร์ฟเวอร์ (Synology: Web Station > Script Language Settings > PHP profile > Extensions)';
        $FETCH_LOG[] = $log;
        return null;
    }

    if (ini_get('allow_url_fopen')) {
        foreach (array(true, false) as $verify) {
            $ctx = stream_context_create(array(
                'http' => array('timeout' => 25, 'header' => "User-Agent: $ua\r\n", 'ignore_errors' => true),
                'ssl'  => array('verify_peer' => $verify, 'verify_peer_name' => $verify),
            ));
            $body = @file_get_contents($url, false, $ctx);
            $e = error_get_last();
            $status = isset($http_response_header[0]) ? $http_response_header[0] : '';
            $log['method'] = 'file_get_contents' . ($verify ? '' : ' (no SSL verify)');
            $log['http'] = $status;
            $log['error'] = $body === false && $e ? $e['message'] : '';
            $log['bytes'] = is_string($body) ? strlen($body) : 0;
            $FETCH_LOG[] = $log;
            if ($body !== false && strpos($status, '200') !== false) return $body;
        }
    } elseif (!function_exists('curl_init')) {
        $log['error'] = 'ไม่มี cURL และ allow_url_fopen ถูกปิด — เซิร์ฟเวอร์ดึงข้อมูลจากเว็บอื่นไม่ได้';
        $FETCH_LOG[] = $log;
    }
    return null;
}

/** Extract a JS array literal like `const name = [...]` as a PHP array. */
function js_array($html, $name) {
    if (!preg_match('/const\s+' . preg_quote($name, '/') . '\s*=\s*(\[.*?\]);/s', $html, $m)) return array();
    $arr = json_decode($m[1], true);
    return is_array($arr) ? $arr : array();
}

function js_string($html, $name) {
    return preg_match('/const\s+' . preg_quote($name, '/') . '\s*=\s*"([^"]*)"/', $html, $m) ? $m[1] : null;
}

function series($html, $prefix) {
    return array(
        'labels' => js_array($html, $prefix . 'Dates'),
        'values' => array_map('floatval', js_array($html, $prefix . 'Values')),
    );
}

/** Fetch + parse both upstream pages. Returns [data, jpegBytes] or null if the main page failed. */
function scrape() {
    $now = fetch_url(SRC_NOW);
    if ($now === null) return null;

    $data = array(
        'source'     => SRC_NOW,
        'fetched_at' => date('c'),
        'level'      => null,
        'status'     => null,
        'time'       => null,
        'overlay'    => null,
        'thresholds' => array('watch' => 1.50, 'critical' => 2.30),
        'history'    => new stdClass(),
    );
    $jpg = null;

    if (preg_match('/ระดับน้ำ:\s*([-\d.]+)\s*เมตร/u', $now, $m)) $data['level'] = (float)$m[1];
    if (preg_match('/<p class="text-sm text-gray-500 mb-2">\s*([^<]+?)\s*<\/p>/', $now, $m)) {
        $data['time'] = preg_replace('/\.000$/', '', $m[1]);
    }
    if (preg_match('/rounded-full"\s+style="[^"]*">\s*([^<]+?)\s*<\/span>/u', $now, $m)) $data['status'] = $m[1];
    // Attribute may be quoted with ' or " (values are HTML-entity encoded either way)
    if (preg_match('/data-thresholds=([\'"])(.+?)\1/s', $now, $m)) {
        $data['overlay'] = json_decode(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'), true);
    }
    if (preg_match('/<img src="data:image\/[a-z]+;base64,([^"]+)"[^>]*snapshot-image/', $now, $m)) {
        $b = base64_decode($m[1]);
        if ($b !== false) $jpg = $b;
    }

    $graph = fetch_url(SRC_GRAPH);
    if ($graph !== null) {
        $hist = array();
        foreach (array('daily', 'weekly', 'monthly', 'yearly', 'multiyear') as $p) $hist[$p] = series($graph, $p);
        $data['history'] = $hist;
        $y = js_string($graph, 'yellowThreshold');
        $r = js_string($graph, 'redThreshold');
        if ($y !== null) $data['thresholds']['watch'] = (float)$y;
        if ($r !== null) $data['thresholds']['critical'] = (float)$r;
    }
    return array($data, $jpg);
}

function to_json($v) {
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    return json_encode($v, $flags);
}

// ---------------------------------------------------------------------------

$dir       = cache_dir();
$cacheJson = $dir ? "$dir/pakkret.json" : null;
$cacheImg  = $dir ? "$dir/pakkret.jpg"  : null;

// Diagnostics page
if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "WaterMonitor diagnostics\n========================\n";
    echo 'PHP version      : ' . PHP_VERSION . "\n";
    echo 'cURL             : ' . (function_exists('curl_init') ? 'yes' : 'NO') . "\n";
    echo 'allow_url_fopen  : ' . (ini_get('allow_url_fopen') ? 'on' : 'OFF') . "\n";
    echo 'OpenSSL          : ' . (extension_loaded('openssl') ? 'yes' : 'NO') . "\n";
    echo 'mbstring/PCRE u  : ' . (@preg_match('/ก/u', 'ก') ? 'ok' : 'FAIL') . "\n";
    echo 'Cache folder     : ' . ($dir ? $dir . ' (writable)' : 'NONE writable (works without cache)') . "\n\n";
    $t = microtime(true);
    $res = scrape();
    echo "Fetch results (" . round(microtime(true) - $t, 2) . "s):\n";
    foreach ($FETCH_LOG as $l) echo '  - ' . to_json($l) . "\n";
    if ($res) {
        list($d, $jpg) = $res;
        echo "\nParsed:\n";
        echo '  level      : ' . var_export($d['level'], true) . "\n";
        echo '  status     : ' . var_export($d['status'], true) . "\n";
        echo '  time       : ' . var_export($d['time'], true) . "\n";
        echo '  image      : ' . ($jpg ? strlen($jpg) . ' bytes' : 'NOT FOUND') . "\n";
        echo '  overlay    : ' . ($d['overlay'] ? 'ok' : 'NOT FOUND') . "\n";
        $h = (array)$d['history'];
        echo '  history    : ' . ($h ? implode(', ', array_map(function ($k) use ($h) { return $k . '=' . count($h[$k]['values']); }, array_keys($h))) : 'NOT FOUND') . "\n";
        echo "\nRESULT: OK\n";
    } else {
        echo "\nRESULT: ดึงข้อมูลจาก pakkretconnect.com ไม่ได้ — ดู error ด้านบน\n";
        echo "(ถ้าเป็น timeout / could not resolve / connection refused แปลว่าโฮสต์บล็อกการเชื่อมต่อออกไปภายนอก ให้ติดต่อผู้ให้บริการโฮสต์)\n";
    }
    exit;
}

// ?fresh=1 (page load) accepts only data <= 15 s old, so a refresh shows new data without every reload hitting upstream
$ttl = (isset($_GET['fresh']) && !isset($_GET['img'])) ? 15 : CACHE_TTL;
$data = null; $jpg = null; $stale = false;

if ($cacheJson && is_file($cacheJson) && (time() - filemtime($cacheJson) < $ttl)) {
    $data = json_decode(file_get_contents($cacheJson), true);
}
if ($data === null && !isset($_GET['img'])) {
    $res = scrape();
    if ($res) {
        list($data, $jpg) = $res;
        if ($cacheJson) {
            @file_put_contents($cacheJson, to_json($data), LOCK_EX);
            if ($jpg !== null) @file_put_contents($cacheImg, $jpg, LOCK_EX);
        }
    } elseif ($cacheJson && is_file($cacheJson)) {   // upstream down: serve last good copy
        $data  = json_decode(file_get_contents($cacheJson), true);
        $stale = true;
    }
}

if (isset($_GET['img'])) {
    if (!$cacheImg || !is_file($cacheImg)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: no-cache');
    readfile($cacheImg);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
if ($data === null) {
    http_response_code(502);
    $last = end($FETCH_LOG);
    echo to_json(array(
        'error'  => 'เซิร์ฟเวอร์ดึงข้อมูลจาก pakkretconnect.com ไม่ได้ (เปิด api/pakkret.php?debug=1 เพื่อดูสาเหตุ)',
        'detail' => $last ? $last : null,
    ));
    exit;
}

$data['stale'] = $stale;
if ($cacheImg && is_file($cacheImg)) {
    $data['image_url'] = 'api/pakkret.php?img=1&t=' . filemtime($cacheImg);
} elseif ($jpg !== null) {
    $data['image_url'] = 'data:image/jpeg;base64,' . base64_encode($jpg);   // no writable folder: inline it
} else {
    $data['image_url'] = null;
}
echo to_json($data);
