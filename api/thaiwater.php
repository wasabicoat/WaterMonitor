<?php
/**
 * ThaiWater (HII/สสน.) telemetry water-level stations for Nonthaburi province.
 * Same data as https://nonthaburi.thaiwater.net/wl (that site can't be framed and its API has no CORS).
 *
 *   api/thaiwater.php          -> JSON: fetched_at, stale, stations[] with last-48h series
 *   api/thaiwater.php?debug=1  -> diagnostics
 */
require __DIR__ . '/lib.php';

define('TW_API', 'https://api-v3.thaiwater.net/api/v1/thaiwater30/');
define('TW_PROVINCE', '12');       // Nonthaburi
define('TW_TTL', 300);             // upstream updates every 10 min; 5 min cache is plenty
define('TW_HOURS', 48);            // history window per station

/** Situation from % of bank level, using the scale the API returns (first matching rule wins). */
function tw_situation($pct, $scale) {
    if ($pct === null) return array('text' => 'ไม่มีข้อมูล', 'color' => '#9aa8b5');
    foreach ($scale as $s) {
        $t = (float)$s['term'];
        $op = $s['operator'];
        $hit = ($op === '>' && $pct > $t) || ($op === '>=' && $pct >= $t) || ($op === '<' && $pct < $t) || ($op === '<=' && $pct <= $t);
        if ($hit) return array('text' => $s['situation'], 'color' => $s['color']);
    }
    return array('text' => '-', 'color' => '#9aa8b5');
}

function num_or_null($v) {
    return ($v === null || $v === '') ? null : (float)$v;
}

/** Fetch + normalize. Returns array or null if the station list can't be fetched. */
function tw_scrape() {
    $body = fetch_url(TW_API . 'provinces/waterlevel?province_code=' . TW_PROVINCE, 20);
    $j = $body !== null ? json_decode($body, true) : null;
    if (!is_array($j) || !isset($j['data']) || !is_array($j['data'])) return null;
    $scale = isset($j['scale']['scale']) && is_array($j['scale']['scale']) ? $j['scale']['scale'] : array();

    $start = date('Y-m-d', time() - TW_HOURS * 3600);
    $end   = date('Y-m-d');
    $since = date('Y-m-d H:i', time() - TW_HOURS * 3600);

    $out = array();
    foreach ($j['data'] as $r) {
        $st = isset($r['station']) ? $r['station'] : array();
        $id = isset($st['id']) ? (int)$st['id'] : 0;
        $pct = num_or_null(isset($r['storage_percent']) ? $r['storage_percent'] : null);

        $series = array();
        $g = fetch_url(TW_API . 'public/waterlevel_graph?station_type=tele_waterlevel&station_id=' . $id . '&start_date=' . $start . '&end_date=' . $end, 20);
        $gj = $g !== null ? json_decode($g, true) : null;
        if (isset($gj['data']['graph_data']) && is_array($gj['data']['graph_data'])) {
            foreach ($gj['data']['graph_data'] as $p) {
                if (!isset($p['datetime']) || $p['value'] === null || $p['datetime'] < $since) continue;
                $series[] = array($p['datetime'], (float)$p['value']);
            }
        }

        $out[] = array(
            'id'        => $id,
            'code'      => isset($st['tele_station_oldcode']) ? (string)$st['tele_station_oldcode'] : '',
            'name'      => isset($st['tele_station_name']['th']) ? (string)$st['tele_station_name']['th'] : '',
            'river'     => isset($r['river_name']) ? (string)$r['river_name'] : '',
            'amphoe'    => isset($r['geocode']['amphoe_name']['th']) ? (string)$r['geocode']['amphoe_name']['th'] : '',
            'datetime'  => isset($r['waterlevel_datetime']) ? (string)$r['waterlevel_datetime'] : '',
            'wl'        => num_or_null(isset($r['waterlevel_msl']) ? $r['waterlevel_msl'] : null),
            'wl_prev'   => num_or_null(isset($r['waterlevel_msl_previous']) ? $r['waterlevel_msl_previous'] : null),
            'bank'      => num_or_null(isset($st['min_bank']) ? $st['min_bank'] : null),
            'pct'       => $pct,
            'diff_bank' => num_or_null(isset($r['diff_wl_bank']) ? $r['diff_wl_bank'] : null),
            'diff_text' => isset($r['diff_wl_bank_text']) ? (string)$r['diff_wl_bank_text'] : '',
            'situation' => tw_situation($pct, $scale),
            'series'    => $series,
        );
    }
    return array('fetched_at' => date('c'), 'scale' => $scale, 'stations' => $out);
}

$dir   = cache_dir();
$cache = $dir ? "$dir/thaiwater.json" : null;

if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ThaiWater diagnostics\n=====================\n";
    echo 'PHP ' . PHP_VERSION . ' · cURL ' . (function_exists('curl_init') ? 'yes' : 'NO') . ' · OpenSSL ' . (extension_loaded('openssl') ? 'yes' : 'NO') . "\n";
    echo 'Cache: ' . ($dir ? $dir : 'none') . "\n\n";
    $d = tw_scrape();
    foreach ($FETCH_LOG as $l) echo '  - ' . to_json($l) . "\n";
    if ($d) {
        foreach ($d['stations'] as $s) echo "\n  " . $s['code'] . ' ' . $s['name'] . ': ' . $s['wl'] . ' ม.รทก. (' . $s['pct'] . '% ตลิ่ง) ' . $s['datetime'] . ' · series ' . count($s['series']);
        echo "\n\nRESULT: OK\n";
    } else {
        echo "\nRESULT: FAIL\n";
    }
    exit;
}

if ($cache && is_file($cache) && time() - filemtime($cache) < TW_TTL) {
    $d = json_decode(file_get_contents($cache), true);
    if (is_array($d)) { $d['stale'] = false; send_json($d); }
}
$d = tw_scrape();
if ($d) {
    if ($cache) @file_put_contents($cache, to_json($d), LOCK_EX);
    $d['stale'] = false;
    send_json($d);
}
if ($cache && is_file($cache)) {                      // upstream down: last good copy
    $d = json_decode(file_get_contents($cache), true);
    if (is_array($d)) { $d['stale'] = true; send_json($d); }
}
send_json(array('error' => 'ดึงข้อมูล ThaiWater ไม่ได้ (เปิด api/thaiwater.php?debug=1)'), 502);
