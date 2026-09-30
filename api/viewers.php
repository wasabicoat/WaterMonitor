<?php
/**
 * Live viewer counter: each open page pings every 30 s with a random per-tab id;
 * "online" = ids seen within the last ONLINE_WINDOW seconds. No IPs or cookies are stored.
 *
 *   api/viewers.php?id=<id>            -> heartbeat, returns {"online": n}
 *   api/viewers.php?id=<id>&leave=1    -> tab closed (sendBeacon), removes the id
 *   api/viewers.php                    -> just read {"online": n}
 */
require __DIR__ . '/lib.php';

define('ONLINE_WINDOW', 70);   // heartbeat is 30 s; allow one missed beat

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
if ($id !== '' && !preg_match('/^[a-z0-9]{8,32}$/', $id)) send_json(array('error' => 'bad id'), 400);

$dir = cache_dir();
if (!$dir) send_json(array('online' => null));

$fh = @fopen("$dir/viewers.json", 'c+');
if (!$fh) send_json(array('online' => null));
flock($fh, LOCK_EX);                         // serialize read-modify-write across PHP workers
$raw = stream_get_contents($fh);
$seen = json_decode($raw ? $raw : '{}', true);
if (!is_array($seen)) $seen = array();

$now = time();
foreach ($seen as $k => $t) if ($now - (int)$t > ONLINE_WINDOW) unset($seen[$k]);
if ($id !== '') {
    if (isset($_GET['leave'])) unset($seen[$id]);
    else $seen[$id] = $now;
}

ftruncate($fh, 0);
rewind($fh);
fwrite($fh, json_encode((object)$seen));
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);

send_json(array('online' => count($seen)));
