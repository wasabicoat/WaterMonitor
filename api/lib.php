<?php
/**
 * Shared helpers for the API proxies (PHP 7.0+): cache folder, HTTP fetch, JSON output.
 */

$DEBUG = isset($_GET['debug']);
// PHP warnings printed into the response would corrupt the JSON/image the page expects.
ini_set('display_errors', $DEBUG ? '1' : '0');
error_reporting(E_ALL);
@set_time_limit(90);
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

/**
 * Fetch a URL with cURL if available, else file_get_contents. Returns body or null.
 * Retries without SSL verification only for https (outdated CA bundles on NAS/shared hosts).
 */
function fetch_url($url, $timeout = 15) {
    global $FETCH_LOG;
    $log = array('url' => $url);
    // Plain UA on purpose: a Chrome UA plus cURL's Accept-Encoding trips some bot filters (HTTP 403).
    $ua = 'Mozilla/5.0 (WaterMonitor)';
    $isHttps = stripos($url, 'https://') === 0;

    if (function_exists('curl_init')) {
        foreach ($isHttps ? array(true, false) : array(true) as $verify) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_SSL_VERIFYPEER => $verify,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            ));
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $log['elapsed'] = round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME), 3);
            $log['method'] = 'curl' . ($verify ? '' : ' (no SSL verify)');
            $log['http'] = $code;
            $log['error'] = curl_error($ch);
            $log['bytes'] = is_string($body) ? strlen($body) : 0;
            curl_close($ch);
            $FETCH_LOG[] = $log;
            if ($body !== false && $code === 200) return $body;
            if ($code !== 0) break;   // got an HTTP answer; SSL was not the problem
        }
        return null;
    }

    if ($isHttps && !extension_loaded('openssl')) {
        $log['error'] = 'PHP ไม่มี cURL และไม่มี OpenSSL จึงเปิด https:// ไม่ได้ — เปิด extension curl และ openssl (Synology: Web Station > Script Language Settings > PHP profile > Extensions)';
        $FETCH_LOG[] = $log;
        return null;
    }
    if (!ini_get('allow_url_fopen')) {
        $log['error'] = 'ไม่มี cURL และ allow_url_fopen ถูกปิด — เซิร์ฟเวอร์ดึงข้อมูลจากเว็บอื่นไม่ได้';
        $FETCH_LOG[] = $log;
        return null;
    }
    foreach ($isHttps ? array(true, false) : array(true) as $verify) {
        $ctx = stream_context_create(array(
            'http' => array('timeout' => $timeout, 'header' => "User-Agent: $ua\r\n", 'ignore_errors' => true),
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
    return null;
}

function to_json($v) {
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    return json_encode($v, $flags);
}

function send_json($v, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo to_json($v);
    exit;
}
