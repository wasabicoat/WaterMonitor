<?php
/**
 * Rangsit CDP (cdp.rangsitcity.go.th) water-level camera points.
 * The Next.js page embeds the points as escaped JSON; we unescape and regex them out.
 * The snapshot JPEG is loaded by the browser directly — we never proxy images.
 *
 *   api/rangsit.php          -> JSON: fetched_at, stale, source, points[]
 *   api/rangsit.php?debug=1  -> diagnostics
 */
require __DIR__ . '/lib.php';

define('CDP_URL', 'https://cdp.rangsitcity.go.th/');
define('CDP_TTL', 60);

/** Fetch + parse the camera points. Returns array of points or null if none found. */
function cdp_scrape() {
    $html = fetch_url(CDP_URL, 20);
    if ($html === null) return null;

    // The points are embedded as escaped JSON (\" quotes); unescape so the regex can match.
    $s = str_replace('\\"', '"', $html);

    // "watch" may be null for a point without an assessment; keep the point anyway
    $pat = '/\{"id":(\d+),"name":"([^"]*)","description":(?:null|"[^"]*"),"latitude":([-\d.]+),"longitude":([-\d.]+),"watch":(?:null|\{(.*?)\})\}/u';
    if (!preg_match_all($pat, $s, $m, PREG_SET_ORDER)) return null;

    $points = array();
    $seen = array();
    foreach ($m as $mm) {
        $id = (int)$mm[1];
        if (isset($seen[$id])) continue;   // dedupe by id, keep first
        $seen[$id] = true;

        $body = isset($mm[5]) ? $mm[5] : '';
        $level = '';
        if (preg_match('/"level":"([A-Z_]+)"/', $body, $x)) $level = $x[1];
        $label = '';
        if (preg_match('/"label":"([^"]*)"/', $body, $x)) $label = $x[1];
        $reason = '';
        if (preg_match('/"cvReason":"((?:[^"\\\\]|\\\\.)*)"/', $body, $x)) {
            $dec = json_decode('"' . $x[1] . '"');
            if (is_string($dec)) $reason = $dec;
        }
        $updated_at = '';
        if (preg_match('/"updatedAt":"([^"]+)"/', $body, $x)) $updated_at = $x[1];
        $updated_by = '';
        if (preg_match('/"updatedBy":"([^"]*)"/', $body, $x)) $updated_by = $x[1];

        $points[] = array(
            'id'         => $id,
            'name'       => $mm[2],
            'label'      => $label,
            'lat'        => (float)$mm[3],
            'lng'        => (float)$mm[4],
            'level'      => $level,
            'reason'     => $reason,
            'updated_at' => $updated_at,
            'updated_by' => $updated_by,
            'image'      => 'https://cdp.rangsitcity.go.th/api/flood/snapshot/' . $id,
        );
    }
    return $points ? $points : null;
}

$dir   = cache_dir();
$cache = $dir ? "$dir/rangsit.json" : null;

if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Rangsit CDP diagnostics\n=========================\n";
    echo 'PHP ' . PHP_VERSION . ' · cURL ' . (function_exists('curl_init') ? 'yes' : 'NO') . ' · OpenSSL ' . (extension_loaded('openssl') ? 'yes' : 'NO') . "\n";
    echo 'Cache: ' . ($dir ? $dir : 'none') . "\n\n";
    $points = cdp_scrape();
    foreach ($FETCH_LOG as $l) echo '  - ' . to_json($l) . "\n";
    if ($points) {
        echo "\npoints: " . count($points) . "\n";
        foreach ($points as $p) echo "  " . $p['id'] . ' ' . $p['name'] . ' ' . $p['level'] . "\n";
        echo "\nRESULT: OK\n";
    } else {
        echo "\nRESULT: FAIL\n";
    }
    exit;
}

if ($cache && is_file($cache) && time() - filemtime($cache) < CDP_TTL) {
    $d = json_decode(file_get_contents($cache), true);
    if (is_array($d)) { $d['stale'] = false; send_json($d); }
}
$points = cdp_scrape();
if ($points) {
    $d = array('fetched_at' => date('c'), 'source' => CDP_URL, 'points' => $points);
    if ($cache) @file_put_contents($cache, to_json($d), LOCK_EX);
    $d['stale'] = false;
    send_json($d);
}
if ($cache && is_file($cache)) {                      // upstream down: last good copy
    $d = json_decode(file_get_contents($cache), true);
    if (is_array($d)) { $d['stale'] = true; send_json($d); }
}
send_json(array('error' => 'ดึงข้อมูลกล้องรังสิตไม่ได้ (เปิด api/rangsit.php?debug=1)'), 502);
