<?php
/**
 * Extra situation layers for the all-source map, merged into one small JSON so the page makes one request:
 *
 *   wl  ThaiWater water-level stations of the Chao Phraya basin (same set as waterchart.thaiwater.net/basin/chaophraya)
 *       [code, name, province, lat, lng, wl_msl, bank_msl, pct, color, situation, datetime, trend(-1/0/1)]
 *   rf  Bangkok road-flood sensors (floodbangkok.bangkok.go.th — the BMA DDS feed tehx and rutunnam also show)
 *       [code, name, district, lat, lng, now_cm, max_cm, unix_time, malfunction(0/1)]
 *   lf  Flood reports from Longdo Traffic (traffic.longdo.com, iTIC / DOH / users)
 *       [title, lat, lng, start_time, detail, impassable(0/1)]
 *
 *   api/mapdata.php          -> JSON {fetched_at, stale, wl, rf, lf, errors[]}
 *   api/mapdata.php?debug=1  -> diagnostics
 *
 * Each part has its own TTL and keeps its last good copy when its upstream fails.
 */
require __DIR__ . '/lib.php';
@ini_set('memory_limit', '256M');   // waterlevel_load decodes to a few MB of arrays

define('MD_TW_URL', 'https://api-v3.thaiwater.net/api/v1/thaiwater30/public/waterlevel_load?basin_code=6,7,8,9,10,11,12,13,14,15,26');
define('MD_RF_URL', 'https://floodbangkok.bangkok.go.th/bkk/dds/services/api/floods/v1/items/sensor_now?limit=-1&fields=flood_now,flood_max,timestamp,'
    . 'sensor_profile.code,sensor_profile.name,sensor_profile.district,sensor_profile.lat,sensor_profile.long,sensor_profile.device_status');
define('MD_LF_URL', 'https://traffic.longdo.com/incident.json');
define('MD_RECENT_H', 6);   // ThaiWater: only readings from the last 6 h

$MD_TTL = array('wl' => 600, 'rf' => 180, 'lf' => 180);

function md_num($v) { return ($v === null || $v === '') ? null : (float)$v; }

function md_situation($pct, $scale) {
    if ($pct === null) return array('ไม่มีข้อมูล', '#9aa8b5');
    foreach ($scale as $s) {
        $t = (float)$s['term'];
        $op = $s['operator'];
        if (($op === '>' && $pct > $t) || ($op === '>=' && $pct >= $t) || ($op === '<' && $pct < $t) || ($op === '<=' && $pct <= $t))
            return array((string)$s['situation'], (string)$s['color']);
    }
    return array('-', '#9aa8b5');
}

/** ThaiWater basin stations -> compact rows, or null on failure. */
function md_wl() {
    $b = fetch_url(MD_TW_URL, 40);
    $j = $b !== null ? json_decode($b, true) : null;
    if (!is_array($j) || !isset($j['waterlevel_data']['data']) || !is_array($j['waterlevel_data']['data'])) return null;
    $scale = isset($j['scale']['data']['scale']) && is_array($j['scale']['data']['scale']) ? $j['scale']['data']['scale'] : array();
    $out = array();
    $since = time() - MD_RECENT_H * 3600;
    foreach ($j['waterlevel_data']['data'] as $r) {
        $st = isset($r['station']) ? $r['station'] : array();
        $lat = md_num(isset($st['tele_station_lat']) ? $st['tele_station_lat'] : null);
        $lng = md_num(isset($st['tele_station_long']) ? $st['tele_station_long'] : null);
        $dt = isset($r['waterlevel_datetime']) ? (string)$r['waterlevel_datetime'] : '';
        $wl = md_num(isset($r['waterlevel_msl']) ? $r['waterlevel_msl'] : null);
        if ($lat === null || $lng === null || $wl === null || strtotime($dt) < $since) continue;
        $pct = md_num(isset($r['storage_percent']) ? $r['storage_percent'] : null);
        $prev = md_num(isset($r['waterlevel_msl_previous']) ? $r['waterlevel_msl_previous'] : null);
        $sit = md_situation($pct, $scale);
        $out[] = array(
            isset($st['tele_station_oldcode']) ? (string)$st['tele_station_oldcode'] : '',
            isset($st['tele_station_name']['th']) ? (string)$st['tele_station_name']['th'] : '',
            isset($r['geocode']['province_name']['th']) ? (string)$r['geocode']['province_name']['th'] : '',
            round($lat, 5), round($lng, 5), $wl,
            md_num(isset($st['min_bank']) ? $st['min_bank'] : null),
            $pct === null ? null : round($pct, 1),
            $sit[1], $sit[0], $dt,
            $prev === null || abs($wl - $prev) < 0.005 ? 0 : ($wl > $prev ? 1 : -1),
        );
    }
    return $out;
}

/** Bangkok road-flood sensors -> compact rows, or null on failure. */
function md_rf() {
    $b = fetch_url(MD_RF_URL, 25);
    $j = $b !== null ? json_decode($b, true) : null;
    if (!is_array($j) || !isset($j['data']) || !is_array($j['data'])) return null;
    $out = array();
    foreach ($j['data'] as $r) {
        $p = isset($r['sensor_profile']) && is_array($r['sensor_profile']) ? $r['sensor_profile'] : null;
        if (!$p || !isset($p['lat'], $p['long'])) continue;
        $ts = isset($r['timestamp']) ? strtotime($r['timestamp'] . 'Z') : false;   // Directus stores UTC without a zone
        $out[] = array(
            (string)$p['code'], (string)$p['name'], isset($p['district']) ? (string)$p['district'] : '',
            round((float)$p['lat'], 5), round((float)$p['long'], 5),
            round((float)(isset($r['flood_now']) ? $r['flood_now'] : 0), 1),
            round((float)(isset($r['flood_max']) ? $r['flood_max'] : 0), 1),
            $ts ? $ts : 0,
            (isset($p['device_status']) && $p['device_status'] !== 'normal') ? 1 : 0,
        );
    }
    return $out;
}

/** Longdo Traffic flood reports -> compact rows, or null on failure. */
function md_lf() {
    $b = fetch_url(MD_LF_URL, 20);
    $j = $b !== null ? json_decode($b, true) : null;
    if (!is_array($j) || !isset($j['item']) || !is_array($j['item'])) return null;
    $out = array();
    $seen = array();
    foreach ($j['item'] as $e) {
        $title = isset($e['title']) ? (string)$e['title'] : '';
        // Event type 6 = น้ำท่วม; also keep warnings whose title is about flooding
        if ((string)(isset($e['severity']) ? $e['severity'] : '') !== '6' && strpos($title, 'น้ำท่วม') === false) continue;
        // Long floods are re-posted with a later stop_time: drop expired copies and repeats of the same report
        $stop = isset($e['stop_time']) ? strtotime((string)$e['stop_time']) : false;
        if ($stop && $stop < time()) continue;
        if (isset($seen[$title])) continue;
        $seen[$title] = true;
        $lat = md_num(isset($e['latitude']) ? $e['latitude'] : null);
        $lng = md_num(isset($e['longitude']) ? $e['longitude'] : null);
        if ($lat === null || $lng === null) continue;
        $txt = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(array('<br />', '<br>', '</p>'), ' ', isset($e['comment']) ? (string)$e['comment'] : '')), ENT_QUOTES, 'UTF-8')));
        if (function_exists('mb_substr') && mb_strlen($txt, 'UTF-8') > 220) $txt = mb_substr($txt, 0, 220, 'UTF-8') . '…';
        $out[] = array($title, round($lat, 5), round($lng, 5), isset($e['start_time']) ? (string)$e['start_time'] : '', $txt,
            strpos($title, 'ผ่านไม่ได้') !== false ? 1 : 0);
    }
    return $out;
}

$dir   = cache_dir();
$cache = $dir ? "$dir/mapdata.json" : null;
$old   = ($cache && is_file($cache)) ? json_decode(file_get_contents($cache), true) : null;
if (!is_array($old)) $old = array();

if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Map layers diagnostics\n======================\n";
    echo 'PHP ' . PHP_VERSION . ' · cURL ' . (function_exists('curl_init') ? 'yes' : 'NO') . ' · Cache: ' . ($dir ? $dir : 'none') . "\n\n";
    $ok = true;
    foreach (array('wl' => 'md_wl', 'rf' => 'md_rf', 'lf' => 'md_lf') as $k => $fn) {
        $FETCH_LOG = array();
        $d = $fn();
        echo "$k: " . ($d === null ? 'FAIL' : count($d) . ' rows') . "\n";
        foreach ($FETCH_LOG as $l) echo '  - ' . to_json($l) . "\n";
        if ($d === null) $ok = false;
    }
    echo "\nRESULT: " . ($ok ? 'OK' : 'PARTIAL') . "\n";
    exit;
}

// Refresh only the parts whose TTL ran out; a failed part keeps its last good rows.
$now = time();
$out = array('wl' => array(), 'rf' => array(), 'lf' => array(), 't' => array(), 'errors' => array());
$changed = false;
$fns = array('wl' => 'md_wl', 'rf' => 'md_rf', 'lf' => 'md_lf');
$lock = $cache ? @fopen("$cache.lock", 'c') : false;
foreach ($fns as $k => $fn) {
    $t = isset($old['t'][$k]) ? (int)$old['t'][$k] : 0;
    $out[$k] = isset($old[$k]) && is_array($old[$k]) ? $old[$k] : array();
    $out['t'][$k] = $t;
    if ($now - $t < $MD_TTL[$k]) continue;
    // One refresher at a time; others serve the cached copy
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) { if ($t) continue; flock($lock, LOCK_EX); }
    $d = $fn();
    if ($d !== null) { $out[$k] = $d; $out['t'][$k] = $now; $changed = true; }
    else $out['errors'][] = $k;
    if ($lock) flock($lock, LOCK_UN);
}
if ($lock) fclose($lock);
if ($changed && $cache) {
    $save = $out; unset($save['errors']);
    @file_put_contents($cache, to_json($save), LOCK_EX);
}
$out['fetched_at'] = date('c');
$out['stale'] = count($out['errors']) > 0;
if (!$out['wl'] && !$out['rf'] && !$out['lf'] && count($out['errors']) === 3)
    send_json(array('error' => 'ดึงข้อมูลชั้นแผนที่ไม่ได้ (เปิด api/mapdata.php?debug=1)'), 502);
send_json($out);
