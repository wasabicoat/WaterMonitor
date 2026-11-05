<?php
/**
 * Flood news for Bangkok and its neighbouring provinces, newest first.
 *
 *   items  one merged list:
 *            fb    public Facebook posts about flooding that Google News indexed (site:facebook.com)
 *            news  Google News search across Thai outlets (no picture in that feed)
 *            rss   outlet feeds that carry a picture (Matichon, Khaosod, Prachachat, Thairath, Amarin)
 *          Google's search is loose (it also returns e.g. power cuts), so every item must mention flooding AND a place
 *          in Bangkok or a neighbouring province.
 *   pages  latest posts of each Facebook page in $NEWS_PAGES (any topic), from a Google News search of that page.
 *          Facebook's own page plugin no longer shows a timeline to visitors who are not logged in, so the page tabs
 *          are built from this list instead.
 *
 * Facebook posts come with Google links. The page can embed a post (photos / video, Facebook's own player) only with
 * the real post URL, so Google links are resolved to post URLs — a few per refresh, newest first, because Google shows
 * a bot check when links are resolved quickly. Results are kept (cache/news_links.json) and a refusal pauses resolving
 * for an hour. An unresolved post keeps its Google link, which still opens the post.
 *
 *   api/news.php          -> JSON {fetched_at, stale, items: [[kind, title, url, source, unix, img, snippet]],
 *                                  pages: {slug: [[text, url, unix]]}, page_list: [[group, slug, name]], errors[]}
 *   api/news.php?debug=1  -> diagnostics
 */
require __DIR__ . '/lib.php';
@ini_set('memory_limit', '256M');

define('NEWS_TTL', 600);              // merged list: 10 min
define('NEWS_PAGES_TTL', 900);        // page lists: 15 min
define('NEWS_MAX_AGE', 3 * 86400);    // merged list keeps 3 days
define('NEWS_LIMIT', 80);
define('NEWS_PAGE_LIMIT', 15);
define('NEWS_RESOLVE_PER_RUN', 4);
define('NEWS_UA', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36');

// Facebook pages shown as tabs: [group, page slug, name]
$NEWS_PAGES = array(
    array('หน่วยงาน กทม.', 'bkk.best', 'ศูนย์ป้องกันน้ำท่วม กทม. (สำนักการระบายน้ำ)'),
    array('หน่วยงาน กทม.', 'bangkokbma', 'กรุงเทพมหานคร'),
    array('ข่าวจราจร / ด่วน', 'fm91trafficpro', 'สวพ.FM91'),
    array('ข่าวจราจร / ด่วน', 'js100radio', 'JS100'),
    array('หน่วยงานปริมณฑล', 'nakornnont', 'เทศบาลนครนนทบุรี'),
    array('หน่วยงานปริมณฑล', 'PakkretMunicipality', 'เทศบาลนครปากเกร็ด'),
    array('หน่วยงานปริมณฑล', 'rangsitcity2016', 'เทศบาลนครรังสิต'),
    array('หน่วยงานปริมณฑล', 'prnonthaburi', 'ประชาสัมพันธ์จังหวัดนนทบุรี'),
    array('ปภ. / กรมอุตุฯ', 'ddpmrc1', 'ศูนย์ ปภ. เขต 1 ปทุมธานี'),
    array('ปภ. / กรมอุตุฯ', 'tmd.go.th', 'กรมอุตุนิยมวิทยา'),
    array('เพจในพื้นที่', 'konnontnews', 'ข่าวคนนนท์'),
    array('เพจในพื้นที่', 'DonMueangDistrictOffice', 'สำนักงานเขตดอนเมือง'),
    array('เพจในพื้นที่', 'PathumThaninina', 'ปทุมธานีที่นี่มีแต่เรื่อง'),
    array('เพจในพื้นที่', 'PathumJaa', 'คนข่าวปทุมจ๋า'),
);
$NEWS_AREA = 'กรุงเทพ OR กทม OR นนทบุรี OR ปทุมธานี OR รังสิต OR ปากเกร็ด OR สมุทรปราการ OR นครปฐม OR สมุทรสาคร';
$NEWS_GN = array(
    'fb'   => 'น้ำท่วม (' . $NEWS_AREA . ') site:facebook.com when:2d',
    'news' => 'น้ำท่วม (' . $NEWS_AREA . ') -site:facebook.com when:2d',
);
$NEWS_RSS = array(
    'มติชน'      => 'https://www.matichon.co.th/feed',
    'ข่าวสด'     => 'https://www.khaosod.co.th/feed',
    'ประชาชาติ'  => 'https://www.prachachat.net/feed',
    'ไทยรัฐ'     => 'https://www.thairath.co.th/rss/news',
    'อมรินทร์ทีวี' => 'https://www.amarintv.com/rss/news',
);
$NEWS_FLOOD_RE = '/น้ำท่วม|ท่วมขัง|น้ำขัง|น้ำล้น|อุทกภัย|ระบายน้ำ|น้ำทะเลหนุน|ฝนตกหนัก|ฝนถล่ม|เขื่อนเจ้าพระยา|ระดับน้ำ/u';
$NEWS_AREA_RE  = '/กรุงเทพ|กทม|นนทบุรี|นนท์|ปทุมธานี|ปทุมฯ|รังสิต|ปากเกร็ด|บางบัวทอง|บางใหญ่|สมุทรปราการ|นครปฐม|สมุทรสาคร|ดอนเมือง|หลักสี่|บางเขน|ลาดกระบัง|มีนบุรี|หนองจอก|บางนา|เจ้าพระยา/u';

function news_clean($s) {
    $s = html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $s));
}
function news_cut($s, $n) {
    if (!function_exists('mb_strlen') || mb_strlen($s, 'UTF-8') <= $n) return $s;
    return rtrim(mb_substr($s, 0, $n, 'UTF-8')) . '…';
}
function news_time($s) { $t = strtotime((string)$s); return $t ? $t : 0; }
function news_gn_url($q) {
    return 'https://news.google.com/rss/search?' . http_build_query(array('q' => $q, 'hl' => 'th', 'gl' => 'TH', 'ceid' => 'TH:th'));
}

function news_curl($url, $timeout, $follow = true) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_USERAGENT => NEWS_UA, CURLOPT_ENCODING => ''));
    return $ch;
}
function news_log($ch, $b, $url) {
    global $FETCH_LOG;
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $FETCH_LOG[] = array('url' => strlen($url) > 160 ? substr($url, 0, 160) . '…' : $url, 'http' => $code,
        'bytes' => is_string($b) ? strlen($b) : 0, 'error' => curl_error($ch));
    return ($b !== false && $b !== null && $code === 200) ? $b : null;
}
/** GET one URL; null unless HTTP 200. Redirects are not followed when $follow is false (Google's bot check redirects). */
function news_get($url, $timeout = 15, $follow = true) {
    if (!function_exists('curl_init')) return fetch_url($url, $timeout);
    $ch = news_curl($url, $timeout, $follow);
    $b = curl_exec($ch);
    $r = news_log($ch, $b, $url);
    curl_close($ch);
    return $r;
}
/** GET several URLs in parallel: array key => body|null. */
function news_multi($urls, $timeout = 20) {
    $out = array();
    if (!function_exists('curl_multi_init')) {
        foreach ($urls as $k => $u) $out[$k] = news_get($u, $timeout);
        return $out;
    }
    $mh = curl_multi_init();
    $hs = array();
    foreach ($urls as $k => $u) { $hs[$k] = news_curl($u, $timeout); curl_multi_add_handle($mh, $hs[$k]); }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $st === CURLM_OK);
    foreach ($hs as $k => $ch) {
        $out[$k] = news_log($ch, curl_multi_getcontent($ch), $urls[$k]);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}
function news_post($url, $body) {
    if (!function_exists('curl_init')) return null;
    $ch = news_curl($url, 12, false);
    curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded;charset=UTF-8')));
    $b = curl_exec($ch);
    $r = news_log($ch, $b, $url);
    curl_close($ch);
    return $r;
}

/** Google News article id -> publisher URL; null on failure (caller backs off). */
function news_resolve($gid) {
    $pg = news_get('https://news.google.com/articles/' . $gid . '?hl=th&gl=TH&ceid=TH:th', 10, false);
    if ($pg === null || !preg_match('/data-n-a-sg="([^"]+)"/', $pg, $sg) || !preg_match('/data-n-a-ts="([^"]+)"/', $pg, $ts)) return null;
    $inner = json_encode(array('garturlreq', array(array('X', 'X', array('X', 'X'), null, null, 1, 1, 'US:en', null, 1, null, null, null, null, null, 0, 1),
        'X', 'X', 1, array(1, 1, 1), 1, 1, null, 0, 0, null, 0), $gid, (int)$ts[1], $sg[1]));
    $r = news_post('https://news.google.com/_/DotsSplashUi/data/batchexecute', 'f.req=' . rawurlencode(json_encode(array(array(array('Fbv4je', $inner))))));
    if ($r === null || !preg_match('/\\\\"garturlres\\\\",\\\\"(https?:[^"\\\\]+)/', $r, $m)) return null;
    return $m[1];
}

/** Google News RSS body -> rows [kind, title, url, source, unix, '', '']; $filter = keep only flood + area items. */
function news_gn_parse($b, $kind, $filter) {
    global $NEWS_FLOOD_RE, $NEWS_AREA_RE;
    if ($b === null || !preg_match_all('/<item>(.*?)<\/item>/s', $b, $m)) return null;
    $out = array();
    foreach ($m[1] as $it) {
        if (!preg_match('/<title>(.*?)<\/title>/s', $it, $t) || !preg_match('/<link>(.*?)<\/link>/s', $it, $l)) continue;
        $src = preg_match('/<source[^>]*>(.*?)<\/source>/s', $it, $s) ? news_clean($s[1]) : '';
        $title = news_clean($t[1]);
        if ($src !== '') $title = preg_replace('/\s+-\s+' . preg_quote($src, '/') . '$/u', '', $title);
        if ($filter && (!preg_match($NEWS_FLOOD_RE, $title) || !preg_match($NEWS_AREA_RE, $title))) continue;
        $out[] = array($kind, $title, trim($l[1]), $src, preg_match('/<pubDate>(.*?)<\/pubDate>/', $it, $d) ? news_time($d[1]) : 0, '', '');
    }
    return $out;
}

/** Outlet RSS body -> flood + area rows, with a picture when the feed has one. */
function news_rss_parse($b, $name) {
    global $NEWS_FLOOD_RE, $NEWS_AREA_RE;
    if ($b === null || !preg_match_all('/<item[\s>](.*?)<\/item>/s', $b, $m)) return null;
    $out = array();
    foreach ($m[1] as $it) {
        $cdata = function ($tag) use ($it) {
            if (!preg_match('/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/s', $it, $x)) return '';
            return preg_replace('/^\s*<!\[CDATA\[(.*)\]\]>\s*$/s', '$1', $x[1]);
        };
        $title = news_clean($cdata('title'));
        $desc = $cdata('description');
        $text = news_clean($desc);
        $cats = preg_match_all('/<category[^>]*>(.*?)<\/category>/s', $it, $c) ? news_clean(implode(' ', $c[1])) : '';
        $hay = $title . ' ' . $text . ' ' . $cats;
        if (!preg_match($NEWS_FLOOD_RE, $hay) || !preg_match($NEWS_AREA_RE, $hay)) continue;
        $img = '';
        if (preg_match('/<(?:enclosure|media:content|media:thumbnail)[^>]+url="([^"]+)"/', $it, $x)) $img = $x[1];
        elseif (preg_match('/<img[^>]+src="(https:[^"]+)"/', $desc . $cdata('content:encoded'), $x)) $img = $x[1];
        $img = html_entity_decode($img, ENT_QUOTES, 'UTF-8');
        $out[] = array('rss', $title, trim(news_clean($cdata('link'))), $name, news_time($cdata('pubDate')),
            strpos($img, 'https://') === 0 ? $img : '', news_cut($text, 180));
    }
    return $out;
}

/** Normalised title prefix, to spot the same story from Google News and an outlet feed. */
function news_key($title) {
    $t = preg_replace('/[^\p{L}\p{N}]+/u', '', $title);
    return function_exists('mb_substr') ? mb_substr($t, 0, 28, 'UTF-8') : substr($t, 0, 80);
}
function news_gid($url) { return preg_match('#news\.google\.com/rss/articles/([^?]+)#', $url, $g) ? $g[1] : null; }

/** Page lists, refreshed every NEWS_PAGES_TTL; a page whose search fails keeps its previous list. */
function news_pages($dir, &$errors) {
    global $NEWS_PAGES;
    $f = $dir ? "$dir/news_pages.json" : null;
    $old = ($f && is_file($f)) ? json_decode(file_get_contents($f), true) : null;
    if (is_array($old) && time() - (int)$old['t'] < NEWS_PAGES_TTL) return $old['pages'];
    $pages = is_array($old) && isset($old['pages']) ? $old['pages'] : array();
    $urls = array();
    foreach ($NEWS_PAGES as $p) $urls[$p[1]] = news_gn_url('site:facebook.com/' . $p[1] . ' when:7d');
    foreach (news_multi($urls) as $slug => $b) {
        $rows = news_gn_parse($b, 'fb', false);
        if ($rows === null) { $errors[] = 'page-' . $slug; continue; }
        usort($rows, function ($a, $b) { return $b[4] - $a[4]; });
        $list = array();
        foreach (array_slice($rows, 0, NEWS_PAGE_LIMIT) as $r) $list[] = array(news_cut($r[1], 300), $r[2], $r[4]);
        $pages[$slug] = $list;
    }
    if ($f) @file_put_contents($f, to_json(array('t' => time(), 'pages' => $pages)), LOCK_EX);
    return $pages;
}

function news_build($dir, &$errors) {
    global $NEWS_GN, $NEWS_RSS;
    $urls = array();
    foreach ($NEWS_GN as $kind => $q) $urls['gn:' . $kind] = news_gn_url($q);
    foreach ($NEWS_RSS as $name => $u) $urls['rss:' . $name] = $u;
    $rows = array();
    foreach (news_multi($urls) as $k => $b) {
        list($type, $name) = explode(':', $k, 2);
        $r = $type === 'gn' ? news_gn_parse($b, $name, true) : news_rss_parse($b, $name);
        if ($r === null) $errors[] = $k; else $rows = array_merge($rows, $r);
    }
    $pages = news_pages($dir, $errors);
    if (!$rows && !$pages) return null;
    usort($rows, function ($a, $b) { return $b[4] - $a[4]; });

    // Resolve Google links of Facebook posts: newest first across the merged list and the page lists
    $lf = $dir ? "$dir/news_links.json" : null;
    $links = ($lf && is_file($lf)) ? json_decode(file_get_contents($lf), true) : null;
    if (!is_array($links) || !isset($links['map'])) $links = array('map' => array(), 'pause' => 0);
    $want = array();
    foreach ($rows as $r) if ($r[0] === 'fb' && ($g = news_gid($r[2])) && !isset($links['map'][$g])) $want[$g] = $r[4];
    foreach ($pages as $list) foreach ($list as $r) if (($g = news_gid($r[1])) && !isset($links['map'][$g])) $want[$g] = $r[2];
    arsort($want);
    $budget = time() < (int)$links['pause'] ? 0 : NEWS_RESOLVE_PER_RUN;
    $dirty = false;
    foreach ($want as $g => $t) {
        if ($budget-- <= 0) break;
        $u = news_resolve($g);
        $dirty = true;
        if ($u === null) { $links['pause'] = time() + 3600; $errors[] = 'fb-links-paused'; break; }
        $links['map'][$g] = array($u, time());
    }
    if ($dirty && $lf) {
        foreach ($links['map'] as $k => $v) if ($v[1] < time() - 10 * 86400) unset($links['map'][$k]);
        @file_put_contents($lf, to_json($links), LOCK_EX);
    }
    $real = function ($url) use ($links) { $g = news_gid($url); return ($g && isset($links['map'][$g])) ? $links['map'][$g][0] : $url; };

    // Merged list: real post URLs, page name from the URL, drop old items and repeats (keep the copy with a picture)
    $seen = array();
    $out = array();
    foreach ($rows as $r) {
        if ($r[4] < time() - NEWS_MAX_AGE) continue;
        if ($r[0] === 'fb') {
            $r[2] = $real($r[2]);
            $r[1] = news_cut($r[1], 300);
            $r[3] = preg_match('#^https://(?:www|m)\.facebook\.com/([^/?]+)/#', $r[2], $p) && !ctype_digit($p[1]) ? rawurldecode($p[1]) : 'Facebook';
        }
        $k = news_key($r[1]);
        if ($k === '' || isset($seen[$k])) {
            if ($k !== '' && $r[5] !== '' && $out[$seen[$k]][5] === '') $out[$seen[$k]] = $r;
            continue;
        }
        $seen[$k] = count($out);
        $out[] = $r;
        if (count($out) >= NEWS_LIMIT) break;
    }
    usort($out, function ($a, $b) { return $b[4] - $a[4]; });   // a replaced repeat may carry an older time
    foreach ($pages as $slug => $list) foreach ($list as $i => $r) $pages[$slug][$i][1] = $real($r[1]);
    return array('items' => $out, 'pages' => (object)$pages);
}

$dir   = cache_dir();
$cache = $dir ? "$dir/news.json" : null;

if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "News feed diagnostics\n=====================\nPHP " . PHP_VERSION . ' · cURL ' . (function_exists('curl_init') ? 'yes' : 'NO') . ' · Cache: ' . ($dir ? $dir : 'none') . "\n\n";
    $errors = array();
    $d = news_build($dir, $errors);
    foreach ($FETCH_LOG as $l) echo '  - ' . to_json($l) . "\n";
    if ($d) {
        $n = array();
        foreach ($d['items'] as $r) $n[$r[0]] = (isset($n[$r[0]]) ? $n[$r[0]] : 0) + 1;
        echo "\nitems: " . count($d['items']) . ' ' . to_json($n) . "\npages:";
        foreach ((array)$d['pages'] as $s => $l) echo " $s=" . count($l);
        echo "\nerrors: " . ($errors ? implode(', ', $errors) : 'none') . "\n\nRESULT: OK\n";
    } else echo "\nRESULT: FAIL\n";
    exit;
}

$serve = function ($stale) use ($cache) {
    $d = json_decode(file_get_contents($cache), true);
    if (!is_array($d)) return;
    $d['stale'] = $stale;
    send_json($d);
};
if ($cache && is_file($cache) && time() - filemtime($cache) < NEWS_TTL) $serve(false);
// One refresher at a time; the others get the previous copy right away
$lock = $cache ? @fopen("$cache.lock", 'c') : false;
if ($lock && !flock($lock, LOCK_EX | LOCK_NB) && is_file($cache)) $serve(false);
$errors = array();
$d = news_build($dir, $errors);
if ($d !== null) {
    $d = array('fetched_at' => date('c'), 'items' => $d['items'], 'pages' => $d['pages'], 'page_list' => $NEWS_PAGES, 'errors' => $errors);
    if ($cache) @file_put_contents($cache, to_json($d), LOCK_EX);
    $d['stale'] = false;
    send_json($d);
}
if ($cache && is_file($cache)) $serve(true);
send_json(array('error' => 'ดึงข่าวไม่ได้ (เปิด api/news.php?debug=1)'), 502);
