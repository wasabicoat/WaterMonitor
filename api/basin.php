<?php
/**
 * Upstream Chao Phraya (ThaiWater) basin feed: key stations, basin stations,
 * water gates and main dams feeding into Nonthaburi.
 *
 *   api/basin.php          -> JSON: fetched_at, stale, key_stations[], stations[], gates[], dams[], errors[]
 *   api/basin.php?debug=1  -> diagnostics
 */
require __DIR__ . '/lib.php';

// watergate_load decodes to ~3.6 MB of arrays; give the process room.
@ini_set('memory_limit', '256M');

define('TW_BASE', 'https://api-v3.thaiwater.net/api/v1/thaiwater30/');
define('BASIN_TTL', 600);                 // 10 min cache
define('RECENT_HOURS', 6);                // "recent" = reading within the last 6 h
define('BASIN_PROVINCES', array('12', '13', '14', '10'));   // Nonthaburi, Pathum Thani, Ayutthaya, Bangkok
define('GATE_PROVINCES', array('12', '13', '10'));
define('KEY_STATIONS', array('C.2', 'C.13', 'C.3', 'C.7A', 'C.35', 'CPY014'));   // upstream -> downstream
define('MAIN_DAMS', array('ภูมิพล', 'สิริกิติ์', 'แควน้อย', 'ป่าสัก'));

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

/** Normalize one waterlevel_load row into the basin station shape. */
function wl_item($r, $scale) {
    $st  = isset($r['station']) ? $r['station'] : array();
    $geo = isset($r['geocode']) ? $r['geocode'] : array();
    $dt  = isset($r['waterlevel_datetime']) ? (string)$r['waterlevel_datetime'] : '';
    $pct = num_or_null(isset($r['storage_percent']) ? $r['storage_percent'] : null);
    return array(
        'code'        => isset($st['tele_station_oldcode']) ? (string)$st['tele_station_oldcode'] : '',
        'name'        => isset($st['tele_station_name']['th']) ? (string)$st['tele_station_name']['th'] : '',
        'province'    => isset($geo['province_name']['th']) ? (string)$geo['province_name']['th'] : '',
        'province_code' => isset($geo['province_code']) ? (string)$geo['province_code'] : '',
        'amphoe'      => isset($geo['amphoe_name']['th']) ? (string)$geo['amphoe_name']['th'] : '',
        'river'       => isset($r['river_name']) ? (string)$r['river_name'] : '',
        'lat'         => num_or_null(isset($st['tele_station_lat']) ? $st['tele_station_lat'] : null),
        'lng'         => num_or_null(isset($st['tele_station_long']) ? $st['tele_station_long'] : null),
        'datetime'    => $dt,
        'wl'          => num_or_null(isset($r['waterlevel_msl']) ? $r['waterlevel_msl'] : null),
        'wl_prev'     => num_or_null(isset($r['waterlevel_msl_previous']) ? $r['waterlevel_msl_previous'] : null),
        'bank'        => num_or_null(isset($st['min_bank']) ? $st['min_bank'] : null),
        'pct'         => $pct,
        'discharge'   => num_or_null(isset($r['discharge']) ? $r['discharge'] : null),
        'qmax'        => num_or_null(isset($st['qmax']) ? $st['qmax'] : null),
        'diff_bank'   => num_or_null(isset($r['diff_wl_bank']) ? $r['diff_wl_bank'] : null),
        'diff_text'   => isset($r['diff_wl_bank_text']) ? (string)$r['diff_wl_bank_text'] : '',
        'situation'   => tw_situation($pct, $scale),
        'recent'      => (strtotime($dt) >= time() - RECENT_HOURS * 3600),
    );
}

/** Fetch + normalize. Returns array or null if the waterlevel feed can't be fetched. */
function basin_scrape() {
    $errors = array();

    // 1. waterlevel_load — the whole call fails without it
    $body = fetch_url(TW_BASE . 'public/waterlevel_load', 30);
    $j = $body !== null ? json_decode($body, true) : null;
    if (!is_array($j) || !isset($j['waterlevel_data']['data']) || !is_array($j['waterlevel_data']['data'])) return null;

    // 2. scale for situation text/color
    $scale = (isset($j['scale']['data']['scale']) && is_array($j['scale']['data']['scale'])) ? $j['scale']['data']['scale'] : array();

    // 3. normalize every row
    $items = array();
    foreach ($j['waterlevel_data']['data'] as $r) $items[] = wl_item($r, $scale);

    // 4. key stations in key-list order (skip missing)
    $key_stations = array();
    foreach (KEY_STATIONS as $code) {
        foreach ($items as $it) {
            if ($it['code'] === $code) { $key_stations[] = $it; break; }
        }
    }

    // 5. basin stations: in the 4 provinces AND recent, sorted by pct desc (nulls last)
    $stations = array();
    foreach ($items as $it) {
        if (!in_array($it['province_code'], BASIN_PROVINCES, true)) continue;
        if (!$it['recent']) continue;
        $stations[] = $it;
    }
    usort($stations, function ($a, $b) {
        if ($a['pct'] === null && $b['pct'] === null) return 0;
        if ($a['pct'] === null) return 1;
        if ($b['pct'] === null) return -1;
        // Compare, don't subtract: usort casts the return to int, so 99.75 - 99.2 would count as "equal"
        if ($a['pct'] == $b['pct']) return 0;
        return ($a['pct'] < $b['pct']) ? 1 : -1;
    });

    // 6. water gates
    $gates = array();
    $gb = fetch_url(TW_BASE . 'public/watergate_load', 30);
    $gj = $gb !== null ? json_decode($gb, true) : null;
    if (!is_array($gj) || !isset($gj['watergate_data']['data']) || !is_array($gj['watergate_data']['data'])) {
        $errors[] = 'gates';
    } else {
        foreach ($gj['watergate_data']['data'] as $r) {
            $pc = isset($r['geocode']['province_code']) ? (string)$r['geocode']['province_code'] : '';
            if (!in_array($pc, GATE_PROVINCES, true)) continue;
            $in  = num_or_null(isset($r['watergate_in']) ? $r['watergate_in'] : null);
            $out = num_or_null(isset($r['watergate_out']) ? $r['watergate_out'] : null);
            if ($in === null && $out === null) continue;
            $ti = isset($r['watergate_datetime_in']) ? (string)$r['watergate_datetime_in'] : '';
            $to = isset($r['watergate_datetime_out']) ? (string)$r['watergate_datetime_out'] : '';
            $ts = array();
            if ($ti !== '') $ts[] = strtotime($ti);
            if ($to !== '') $ts[] = strtotime($to);
            if (!$ts || max($ts) < time() - RECENT_HOURS * 3600) continue;
            // latest of the two timestamps
            $datetime = $ti;
            if ($ti === '') $datetime = $to;
            elseif ($to !== '' && strtotime($to) > strtotime($ti)) $datetime = $to;
            $st = isset($r['station']) ? $r['station'] : array();
            $gates[] = array(
                'code'     => isset($st['tele_station_oldcode']) ? (string)$st['tele_station_oldcode'] : '',
                'name'     => isset($st['tele_station_name']['th']) ? (string)$st['tele_station_name']['th'] : '',
                'province' => isset($r['geocode']['province_name']['th']) ? (string)$r['geocode']['province_name']['th'] : '',
                'datetime' => $datetime,
                'up'       => $in,
                'down'     => $out,
            );
        }
        usort($gates, function ($a, $b) {
            if ($a['province'] !== $b['province']) return strcmp($a['province'], $b['province']);
            return strcmp($a['name'], $b['name']);
        });
    }

    // 7. main dams: latest dam_date per keyword with storage_percent > 0
    $dams = array();
    $db = fetch_url(TW_BASE . 'analyst/dam', 30);
    $dj = $db !== null ? json_decode($db, true) : null;
    if (!is_array($dj) || !isset($dj['data']['dam_daily']) || !is_array($dj['data']['dam_daily'])) {
        $errors[] = 'dams';
    } else {
        foreach (MAIN_DAMS as $kw) {
            $best = null;
            foreach ($dj['data']['dam_daily'] as $r) {
                $nm = isset($r['dam']['dam_name']['th']) ? (string)$r['dam']['dam_name']['th'] : '';
                if (strpos($nm, $kw) === false) continue;
                $sp = num_or_null(isset($r['dam_storage_percent']) ? $r['dam_storage_percent'] : null);
                if ($sp === null || $sp <= 0) continue;
                $dd = isset($r['dam_date']) ? (string)$r['dam_date'] : '';
                if ($best === null || $dd > $best['date']) {
                    $best = array(
                        'name'        => $nm,
                        'date'        => $dd,
                        'storage'     => num_or_null(isset($r['dam_storage']) ? $r['dam_storage'] : null),
                        'storage_pct' => $sp,
                        'uses_pct'    => num_or_null(isset($r['dam_uses_water_percent']) ? $r['dam_uses_water_percent'] : null),
                        'inflow'      => num_or_null(isset($r['dam_inflow']) ? $r['dam_inflow'] : null),
                        'released'    => num_or_null(isset($r['dam_released']) ? $r['dam_released'] : null),
                    );
                }
            }
            if ($best !== null) $dams[] = $best;
        }
    }

    // 8.
    return array(
        'fetched_at'   => date('c'),
        'key_stations' => $key_stations,
        'stations'     => $stations,
        'gates'        => $gates,
        'dams'         => $dams,
        'errors'       => $errors,
    );
}

$dir   = cache_dir();
$cache = $dir ? "$dir/basin.json" : null;

if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Basin (upstream Chao Phraya) diagnostics\n========================================\n";
    echo 'PHP ' . PHP_VERSION . ' · cURL ' . (function_exists('curl_init') ? 'yes' : 'NO') . ' · OpenSSL ' . (extension_loaded('openssl') ? 'yes' : 'NO') . "\n";
    echo 'Cache: ' . ($dir ? $dir : 'none') . "\n\n";
    $d = basin_scrape();
    foreach ($FETCH_LOG as $l) echo '  - ' . to_json($l) . "\n";
    if ($d) {
        echo "\nkey_stations: " . count($d['key_stations']) . "\n";
        echo "stations:     " . count($d['stations']) . "\n";
        echo "gates:        " . count($d['gates']) . "\n";
        echo "dams:         " . count($d['dams']) . "\n";
        echo 'errors:       ' . (count($d['errors']) ? implode(', ', $d['errors']) : 'none') . "\n";
        echo "\nRESULT: OK\n";
    } else {
        echo "\nRESULT: FAIL\n";
    }
    exit;
}

if ($cache && is_file($cache) && time() - filemtime($cache) < BASIN_TTL) {
    $d = json_decode(file_get_contents($cache), true);
    if (is_array($d)) { $d['stale'] = false; send_json($d); }
}
$d = basin_scrape();
if ($d) {
    if ($cache) @file_put_contents($cache, to_json($d), LOCK_EX);
    $d['stale'] = false;
    send_json($d);
}
if ($cache && is_file($cache)) {                      // upstream down: last good copy
    $d = json_decode(file_get_contents($cache), true);
    if (is_array($d)) { $d['stale'] = true; send_json($d); }
}
send_json(array('error' => 'ดึงข้อมูลลุ่มเจ้าพระยาจาก ThaiWater ไม่ได้ (เปิด api/basin.php?debug=1)'), 502);
