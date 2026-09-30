<?php
// Official MediaMTX player page: its video segments have no CORS headers, so they can't be played from our own page.
$nonthaburiPlayer = 'https://stream.firsttech.co.th/live/nakornnont.stream/?muted=1&autoplay=1&controls=1';
$pathumPlayer = 'http://101.109.253.60:8999/';   // เทศบาลเมืองปทุมธานี (own player page; segments have no CORS either)
$fbLiveUrl = 'https://www.facebook.com/thaitcity/videos/1433118242123871/';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เฝ้าระวังระดับน้ำ เจ้าพระยา</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #eef3f7; --card: #ffffff; --text: #16222e; --muted: #5d6b79; --line: #d9e2ea;
  --accent: #0a6fb5; --ok: #1f9d55; --warn: #d69e00; --crit: #d63031;
}
@media (prefers-color-scheme: dark) {
  :root { --bg: #0e1620; --card: #17222e; --text: #e6edf3; --muted: #9aa8b5; --line: #26333f; --accent: #4aa8ee; }
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; scroll-padding-top: 56px; }
[hidden] { display: none !important; }
body { margin: 0; font-family: 'Sarabun', system-ui, sans-serif; background: var(--bg); color: var(--text); }
header { padding: 20px 16px 8px; max-width: 1280px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: 8px; }
h1 { margin: 0; font-size: 1.6rem; }
header p { margin: 2px 0 0; color: var(--muted); }
#clock { font-variant-numeric: tabular-nums; color: var(--muted); }
.hdr-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.viewers { font-size: .9rem; color: var(--muted); display: inline-flex; align-items: center; gap: 6px; }
.viewers::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--ok); box-shadow: 0 0 0 3px color-mix(in srgb, var(--ok) 25%, transparent); }
.viewers b { color: var(--text); font-variant-numeric: tabular-nums; }
@media (max-width: 600px) { .hdr-right { align-items: flex-start; } }
main { max-width: 1280px; margin: 0 auto; padding: 8px 16px 32px; display: grid; gap: 16px; grid-template-columns: 1fr 1fr; }
@media (max-width: 900px) { main { grid-template-columns: 1fr; } }
.card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; }
.card h2 { margin: 0; font-size: 1.15rem; padding: 14px 16px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--line); }
.card .body { padding: 14px 16px; }
.live { font-size: .75rem; font-weight: 700; color: #fff; background: var(--crit); padding: 2px 8px; border-radius: 99px; }
.media { position: relative; background: #000; aspect-ratio: 16 / 9; }
.media video, .media img, .media canvas { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
.media .msg { position: absolute; inset: 0; display: grid; place-items: center; color: #ccc; text-align: center; padding: 16px; }
.level { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.level .num { font-size: 2.6rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.badge { padding: 3px 12px; border-radius: 99px; font-weight: 600; color: #fff; }
.badge.ok { background: var(--ok); } .badge.warn { background: var(--warn); color: #000; } .badge.crit { background: var(--crit); }
.meta { color: var(--muted); font-size: .9rem; margin-top: 4px; }
.gauge { margin-top: 14px; }
.gauge .bar { position: relative; height: 14px; border-radius: 7px; background: linear-gradient(90deg, var(--ok) 0 50%, var(--warn) 50% 76.7%, var(--crit) 76.7% 100%); }
.gauge .pin { position: absolute; top: -5px; width: 4px; height: 24px; background: var(--text); border-radius: 2px; transform: translateX(-2px); transition: left .5s; }
.gauge .scale { display: flex; justify-content: space-between; font-size: .8rem; color: var(--muted); margin-top: 4px; }
.tabs { display: flex; gap: 6px; margin: 16px 0 8px; flex-wrap: wrap; }
.tabs button { font: inherit; border: 1px solid var(--line); background: transparent; color: var(--text); padding: 4px 12px; border-radius: 99px; cursor: pointer; }
.tabs button.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.card { min-width: 0; }
.card.wide { grid-column: 1 / -1; }
.chart-wrap { position: relative; height: 320px; }
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 8px 0 14px; }
.stat { border: 1px solid var(--line); border-radius: 10px; padding: 8px 12px; display: flex; flex-direction: column; min-width: 0; }
.stat small { color: var(--muted); font-size: .8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stat b { font-size: 1.4rem; font-variant-numeric: tabular-nums; }
details.data { margin: 14px 0 8px; }
details.data summary { cursor: pointer; color: var(--accent); font-weight: 600; }
.table-wrap { max-height: 320px; overflow: auto; margin-top: 8px; border: 1px solid var(--line); border-radius: 10px; }
table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
th, td { padding: 6px 12px; text-align: left; border-bottom: 1px solid var(--line); white-space: nowrap; }
th { position: sticky; top: 0; background: var(--card); color: var(--muted); font-weight: 600; }
.dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
@media (max-width: 600px) {
  header { padding: 14px 16px 4px; }
  h1 { font-size: 1.25rem; }
  header p, #clock { font-size: .85rem; }
  main { padding: 8px 12px 24px; gap: 12px; }
  .card h2 { font-size: 1rem; padding: 12px; }
  .card .body { padding: 12px; }
  .level .num { font-size: 2rem; }
  .stats { grid-template-columns: repeat(2, 1fr); }
  .stat b { font-size: 1.2rem; }
  .chart-wrap { height: 240px; }
  .tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
  .tabs button { flex: 0 0 auto; }
  footer { padding: 0 12px 20px; }
}
.legend { font-size: .85rem; color: var(--muted); line-height: 1.7; }
.legend span { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
a { color: var(--accent); }
footer { max-width: 1280px; margin: 0 auto; padding: 0 16px 24px; color: var(--muted); font-size: .85rem; }

/* ---------- Nonthaburi additions ---------- */
.secnav { position: sticky; top: 0; z-index: 10; background: var(--bg); }
.secnav .inner { max-width: 1280px; margin: 0 auto; padding: 8px 16px; display: flex; gap: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.secnav a { font: inherit; border: 1px solid var(--line); background: transparent; color: var(--text); padding: 4px 12px; border-radius: 99px; text-decoration: none; white-space: nowrap; flex: 0 0 auto; }
.secnav a:hover { border-color: var(--accent); }
.fbwrap { position: relative; aspect-ratio: 16 / 9; background: #000; }
.fbwrap iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.nbtools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 10px; }
.nbtools input[type="search"] { font: inherit; border: 1px solid var(--line); background: var(--bg); color: var(--text); padding: 4px 12px; border-radius: 99px; flex: 1 1 200px; min-width: 0; }
.camgrid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
@media (max-width: 600px) { .camgrid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); } }
figure.cam { margin: 0; cursor: pointer; }
figure.cam img { display: block; aspect-ratio: 16 / 9; object-fit: cover; background: #000; border-radius: 8px; width: 100%; }
figure.cam figcaption { font-size: .85rem; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text); }
figure.cam.err img { display: none; }
figure.cam.err::after { content: 'ภาพไม่พร้อมใช้งาน'; display: flex; align-items: center; justify-content: center; aspect-ratio: 16 / 9; background: var(--line); color: var(--muted); border-radius: 8px; font-size: .8rem; text-align: center; }
.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.chip { padding: 3px 12px; border-radius: 99px; font-size: .85rem; font-weight: 600; }
.lightbox { position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 50; display: flex; align-items: center; justify-content: center; padding: 16px; }
.lightbox .lb-inner { max-width: 900px; width: 100%; color: #fff; text-align: center; }
.lightbox .lb-inner img { max-width: 100%; max-height: 70vh; border-radius: 8px; }
.lightbox .lb-close { position: absolute; top: 16px; right: 16px; background: none; border: 0; color: #fff; font-size: 2rem; line-height: 1; cursor: pointer; }
.lightbox .lb-cap { margin-top: 10px; font-size: .95rem; }
.lightbox .lb-readings { margin-top: 6px; color: #ccc; font-size: .85rem; }
@media (max-width: 600px) {
  .secnav .inner { padding: 8px 12px; }
}
.media iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.linkbtn { font: inherit; cursor: pointer; background: none; border: 0; color: var(--accent); padding: 0; }
.twgrid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
.tw { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; min-width: 0; }
.tw-head small { display: block; color: var(--muted); }
.twbar { height: 10px; border-radius: 5px; background: var(--line); overflow: hidden; margin: 8px 0 4px; }
.twbar div { height: 100%; transition: width .5s; }
.tw-chart { position: relative; height: 160px; margin-top: 8px; }

/* ---------- Basin (upstream) / Rangsit / links additions ---------- */
.card .body { min-width: 0; }
.bs-flow { display: flex; align-items: stretch; gap: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 8px; }
.bs-tile { flex: 0 0 auto; width: 190px; border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.bs-tile .bs-code { font-weight: 700; }
.bs-tile small { color: var(--muted); display: block; }
.bs-tile .bs-big { font-size: 1.35rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.2; }
.bs-tile .badge { align-self: flex-start; font-size: .8rem; padding: 2px 10px; }
.bs-tile.old { opacity: .7; }
.bs-arrow { flex: 0 0 auto; align-self: center; color: var(--muted); font-size: 1.3rem; }
.bs-dams { margin: 14px 0; }
.bs-dams .tw { padding: 10px 12px; }
.bs-sub { margin: 14px 0 6px; font-weight: 600; }
#bsProv { margin: 8px 0; }
.camgrid.rs { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
@media (max-width: 600px) { .camgrid.rs { grid-template-columns: 1fr; } }
figure.cam .rs-info { cursor: pointer; margin-top: 4px; }
figure.cam .rs-info .badge { font-size: .8rem; padding: 2px 10px; display: inline-block; }
ul.linklist { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; grid-template-columns: 1fr; }
@media (min-width: 700px) { ul.linklist { grid-template-columns: 1fr 1fr; } }
ul.linklist li { border: 1px solid var(--line); border-radius: 10px; padding: 8px 12px; min-width: 0; overflow-wrap: anywhere; }
ul.linklist a { font-weight: 600; }
ul.linklist small { display: block; color: var(--muted); }
</style>
</head>
<body>
<header>
  <div>
    <h1>🌊 เฝ้าระวังระดับน้ำ แม่น้ำเจ้าพระยา</h1>
    <p>ท่าน้ำนนทบุรี · ท่าน้ำปากเกร็ด · ท่าน้ำปทุมธานี · ท่าน้ำท่าอิฐ · สถานีวัดน้ำนนทบุรี</p>
  </div>
  <div class="hdr-right">
    <span class="viewers" id="viewers" hidden title="จำนวนผู้ที่เปิดหน้านี้อยู่ในขณะนี้">กำลังดู <b id="viewersN">–</b> คน</span>
    <div id="clock"></div>
  </div>
</header>

<nav class="secnav"><div class="inner">
  <a href="#river">ท่าน้ำ</a>
  <a href="#pathum">ปทุมธานี</a>
  <a href="#fblive">ท่าอิฐ</a>
  <a href="#history">ระดับน้ำย้อนหลัง</a>
  <a href="#thaiwater">ThaiWater</a>
  <a href="#basin">ต้นน้ำ</a>
  <a href="#rangsit">กล้องรังสิต</a>
  <a href="#links">ลิงก์กล้องอื่น</a>
  <a href="#nbcctv">CCTV นนทบุรี</a>
  <a href="#stations">สถานีวัดน้ำ</a>
</div></nav>

<main>
  <!-- ท่าน้ำนนท์ -->
  <section class="card" id="river">
    <h2>📹 ท่าน้ำนนทบุรี <span class="live">LIVE</span></h2>
    <div class="media">
      <iframe id="nonFrame" data-src="<?= htmlspecialchars($nonthaburiPlayer) ?>" data-refresh="30" title="กล้องท่าน้ำนนทบุรี" allow="autoplay; fullscreen; picture-in-picture"></iframe>
    </div>
    <div class="body">
      <div class="meta">ภาพสดจากกล้อง CCTV ท่าน้ำนนท์ · <button type="button" class="linkbtn" data-reload="nonFrame">เชื่อมต่อใหม่</button> · เล่นอัตโนมัติเมื่อเลื่อนมาเห็น</div>
      <p class="legend" style="margin-top:12px">สังเกตระดับน้ำเทียบกับขอบเขื่อน/ท่าเรือจากภาพสด
        ใช้ค่าจาก Sensor ปากเกร็ดด้านข้างเป็นตัวเลขอ้างอิงของแม่น้ำช่วงเดียวกัน (ห่างกันราว 10 กม.)</p>
    </div>
  </section>

  <!-- ท่าน้ำปากเกร็ด -->
  <section class="card">
    <h2>📡 ท่าน้ำปากเกร็ด (หัวถนน)</h2>
    <div class="media">
      <img id="pkImg" alt="ภาพจากกล้องท่าน้ำปากเกร็ด" hidden>
      <canvas id="pkOverlay"></canvas>
      <div class="msg" id="pkMsg">กำลังโหลดข้อมูล…</div>
    </div>
    <div class="body">
      <div class="level">
        <span>ระดับน้ำ:</span><span class="num" id="pkLevel">–</span><span>เมตร รทก.</span>
        <span class="badge" id="pkBadge" hidden></span>
      </div>
      <div class="meta" id="pkTime"></div>
      <div class="gauge">
        <div class="bar"><div class="pin" id="pkPin" style="left:0"></div></div>
        <div class="scale"><span>0</span><span>1.50</span><span>2.30</span><span>3.00 ม.</span></div>
      </div>

      <div class="legend" style="margin-top:12px">
        <div><span style="background:var(--ok)"></span>ปกติ: ไม่เกิน 1.50 ม. รทก.</div>
        <div><span style="background:var(--warn)"></span>เฝ้าระวัง: 1.50–2.30 ม. รทก. (ประชาชนริมน้ำพื้นที่เสี่ยงควรยกของขึ้นที่สูง)</div>
        <div><span style="background:var(--crit)"></span>วิกฤต: ตั้งแต่ 2.30 ม. รทก. ขึ้นไป</div>
      </div>
    </div>
  </section>

  <!-- ท่าน้ำ เทศบาลเมืองปทุมธานี -->
  <section class="card" id="pathum">
    <h2>📹 ท่าน้ำ เทศบาลเมืองปทุมธานี <span class="live">LIVE</span></h2>
    <div class="media">
      <iframe id="ptFrame" data-src="<?= htmlspecialchars($pathumPlayer) ?>" data-refresh="10" title="กล้องท่าน้ำ เทศบาลเมืองปทุมธานี" allow="autoplay; fullscreen"></iframe>
    </div>
    <div class="body">
      <div class="meta">ภาพสดจากกล้องท่าน้ำ เทศบาลเมืองปทุมธานี · <button type="button" class="linkbtn" data-reload="ptFrame">เชื่อมต่อใหม่</button> · <a href="<?= htmlspecialchars($pathumPlayer) ?>" target="_blank" rel="noopener">เปิดหน้าต้นทาง</a></div>
      <p class="legend" style="margin-top:12px">กล้องนี้ส่งภาพเป็นช่วงละประมาณ 1 นาที ภาพจึงอาจช้ากว่าเวลาจริงหลายนาที</p>
    </div>
  </section>

  <!-- Facebook: ท่าน้ำท่าอิฐ (player loads on click: the Facebook embed pulls several MB of scripts) -->
  <section class="card" id="fblive">
    <h2>📺 ถ่ายทอดสด ท่าน้ำท่าอิฐ <span class="live">LIVE</span></h2>
    <div class="fbwrap"><iframe id="fbFrame" data-src="<?= htmlspecialchars('https://www.facebook.com/plugins/video.php?href=' . rawurlencode($fbLiveUrl) . '&show_text=false&autoplay=true&mute=1&width=1280') ?>" data-refresh="15" title="Facebook Live" scrolling="no" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture; web-share"></iframe></div>
    <div class="body"><div class="meta">ที่มา: <a href="<?= htmlspecialchars($fbLiveUrl) ?>" target="_blank" rel="noopener">เปิดบน Facebook</a>
       · หากไลฟ์จบแล้ว วิดีโออาจแสดงเป็นย้อนหลังหรือไม่พร้อมใช้งาน</div>
      <p class="legend" style="margin-top:12px"><button type="button" class="linkbtn" data-reload="fbFrame">โหลดใหม่</button> · เล่นอัตโนมัติ (ปิดเสียง) เมื่อเลื่อนมาเห็น</p></div>
  </section>

  <!-- กราฟระดับน้ำ (ข้อมูลจาก eonGraph) -->
  <section class="card wide" id="history">
    <h2>📈 ข้อมูลระดับน้ำย้อนหลัง ท่าน้ำปากเกร็ด</h2>
    <div class="body">
      <div class="tabs" id="pkTabs" role="tablist">
        <button data-k="daily" class="active">รายวัน (24 ชม.)</button>
        <button data-k="weekly">รายสัปดาห์</button>
        <button data-k="monthly">รายเดือน</button>
        <button data-k="yearly">รายปี</button>
        <button data-k="multiyear">ทุกปี</button>
      </div>
      <div class="stats">
        <div class="stat"><small>สูงสุด</small><b id="stMax">–</b><small id="stMaxAt"></small></div>
        <div class="stat"><small>ต่ำสุด</small><b id="stMin">–</b><small id="stMinAt"></small></div>
        <div class="stat"><small>เฉลี่ย</small><b id="stAvg">–</b><small>ม. รทก.</small></div>
        <div class="stat"><small>ค่าล่าสุด</small><b id="stLast">–</b><small id="stLastAt"></small></div>
      </div>
      <div class="chart-wrap"><canvas id="pkChart"></canvas></div>
      <details class="data">
        <summary>ดูตารางข้อมูล</summary>
        <div class="table-wrap"><table><thead><tr><th>ช่วงเวลา</th><th>ระดับน้ำ (ม. รทก.)</th><th>สถานะ</th></tr></thead><tbody id="pkTable"></tbody></table></div>
      </details>
      <div class="meta">ที่มา: <a href="https://www.pakkretconnect.com/liffwater/eonGraph" target="_blank" rel="noopener">pakkretconnect.com/liffwater/eonGraph</a> · ค่ารายสัปดาห์/เดือน/ปี เป็นค่าเฉลี่ย</div>
    </div>
  </section>

  <!-- ThaiWater (สสน.) telemetry, Nonthaburi province -->
  <section class="card wide" id="thaiwater">
    <h2>📡 สถานีโทรมาตร สสน. จังหวัดนนทบุรี</h2>
    <div class="body">
      <div class="twgrid" id="twGrid"></div>
      <div class="meta" id="twMsg"></div>
      <div class="meta">ที่มา: <a href="https://nonthaburi.thaiwater.net/wl" target="_blank" rel="noopener">ศูนย์ข้อมูลน้ำระดับจังหวัดนนทบุรี (ThaiWater / สสน.)</a> · ข้อมูลทุก 10 นาที · กราฟย้อนหลัง 48 ชม. · % คือระดับน้ำเทียบกับระดับตลิ่ง</div>
    </div>
  </section>

  <!-- ต้นน้ำเจ้าพระยา (ThaiWater basin) -->
  <section class="card wide" id="basin">
    <h2>🌊 น้ำจากต้นน้ำเจ้าพระยา → นนทบุรี</h2>
    <div class="body">
      <div class="bs-flow" id="bsKey"></div>
      <p class="legend">ปริมาณน้ำไหลผ่าน (ลบ.ม./วินาที) ที่นครสวรรค์ (C.2) และท้ายเขื่อนเจ้าพระยา (C.13) ใช้คาดการณ์น้ำที่จะมาถึงนนทบุรีในอีก 2–4 วัน</p>
      <div class="bs-sub">เขื่อนหลัก</div>
      <div class="twgrid bs-dams" id="bsDams"></div>
      <div class="bs-sub">สถานีวัดน้ำ นนทบุรี · ปทุมธานี · อยุธยา · กรุงเทพฯ</div>
      <div class="meta" id="bsSummary"></div>
      <div class="tabs" id="bsProv">
        <button data-p="" class="active">ทั้งหมด</button>
        <button data-p="นนทบุรี">นนทบุรี</button>
        <button data-p="ปทุมธานี">ปทุมธานี</button>
        <button data-p="พระนครศรีอยุธยา">พระนครศรีอยุธยา</button>
        <button data-p="กรุงเทพมหานคร">กรุงเทพมหานคร</button>
      </div>
      <div class="table-wrap"><table><thead><tr>
        <th>สถานี</th><th>จังหวัด</th><th>ลำน้ำ</th><th>ระดับ (ม.รทก.)</th><th>% ตลิ่ง</th><th>สถานการณ์</th><th>เวลา</th>
      </tr></thead><tbody id="bsTable"></tbody></table></div>
      <details class="data">
        <summary id="bsGatesSum">ประตูระบายน้ำ</summary>
        <div class="table-wrap"><table><thead><tr><th>ประตู</th><th>จังหวัด</th><th>หน้า (ม.รทก.)</th><th>ท้าย (ม.รทก.)</th><th>เวลา</th></tr></thead><tbody id="bsGates"></tbody></table></div>
      </details>
      <div class="meta" id="bsMsg"></div>
      <div class="meta">ที่มา: ThaiWater (สสน.) · อัปเดตทุก 10 นาที · ค้นพบแหล่งข้อมูลผ่าน <a href="https://rutunnam.vercel.app" target="_blank" rel="noopener">รู้ทันน้ำ</a></div>
    </div>
  </section>

  <!-- กล้องวัดระดับน้ำ รังสิต -->
  <section class="card wide" id="rangsit">
    <h2>📷 กล้องวัดระดับน้ำ รังสิต–ปทุมธานี (AI อ่านระดับน้ำ)</h2>
    <div class="body">
      <div class="camgrid rs" id="rsGrid"></div>
      <div class="meta" id="rsMsg"></div>
      <div class="meta">ที่มา: ศูนย์ข้อมูลภัยพิบัติ เทศบาลนครรังสิต (<a href="https://cdp.rangsitcity.go.th/" target="_blank" rel="noopener">cdp.rangsitcity.go.th</a>) · ระดับน้ำประเมินอัตโนมัติจากภาพ (OpenCV) โดยต้นทาง</div>
    </div>
  </section>

  <!-- ลิงก์กล้องอื่น -->
  <section class="card wide" id="links">
    <h2>🔗 กล้องและข้อมูลน้ำจากแหล่งอื่น</h2>
    <div class="body">
      <ul class="linklist">
        <li><a href="https://wmsc.rid.go.th/cctv2/" target="_blank" rel="noopener">ศูนย์กล้องลุ่มน้ำเจ้าพระยา (กรมชลประทาน)</a><small>เว็บไซต์ระบุว่ารองรับ Firefox</small></li>
        <li><a href="https://www.thaiclouderp.com/CCTV_MONITOR/web/pakkred" target="_blank" rel="noopener">กล้องถนนปากเกร็ด (เทศบาลนครปากเกร็ด)</a></li>
        <li><a href="https://rangsitcity.go.th/cctvrangsitcity/" target="_blank" rel="noopener">กล้องจราจรเทศบาลนครรังสิต</a></li>
        <li><a href="https://www.muangpathum.org/th/z_main.jsp" target="_blank" rel="noopener">เทศบาลเมืองปทุมธานี (กล้องสวนเทพปทุมฯ)</a></li>
        <li><a href="https://telemetry.dwr.go.th/reportCctv" target="_blank" rel="noopener">ศูนย์กล้องระดับน้ำทั่วประเทศ (กรมทรัพยากรน้ำ)</a></li>
        <li><a href="https://disaster.gistda.or.th/flood" target="_blank" rel="noopener">แผนที่น้ำท่วม 7 วัน (GISTDA)</a></li>
        <li><a href="https://rutunnam.vercel.app" target="_blank" rel="noopener">รู้ทันน้ำ — แผนที่สถานการณ์น้ำ</a><small>แหล่งอ้างอิงรายชื่อกล้องและแหล่งข้อมูลในส่วนนี้</small></li>
      </ul>
      <p class="legend">กล้องของ กทม. (dds.bangkok.go.th) ไม่ได้นำมาแสดง เพราะภาพไม่อัปเดตมาตั้งแต่ ส.ค. 2569</p>
    </div>
  </section>

  <!-- CCTV เทศบาลนครนนทบุรี -->
  <section class="card wide" id="nbcctv">
    <h2>📷 CCTV เทศบาลนครนนทบุรี <small id="nbCamCount" class="meta"></small></h2>
    <div class="body">
      <div class="nbtools">
        <div class="tabs" id="nbZone">
          <button data-z="" class="active">ทั้งหมด</button>
          <button data-z="A">โซน A</button>
          <button data-z="B">โซน B</button>
        </div>
        <input id="nbSearch" type="search" placeholder="ค้นหาสถานี เช่น A1 หรือ คลอง…">
      </div>
      <div class="camgrid" id="nbGrid"></div>
      <div class="meta" id="nbEmpty" hidden>ไม่พบกล้องที่ตรงกับการค้นหา</div>
      <div class="meta" id="nbMsg"></div>
      <div class="meta">ที่มา: <a href="http://182.52.224.70/?page=cctv" target="_blank" rel="noopener">ระบบศูนย์ป้องกันและแก้ไขปัญหาน้ำท่วม เทศบาลนครนนทบุรี</a> · ภาพอัปเดตทุก 1–2 นาที (ต้นทางส่งภาพช้า)</div>
    </div>
  </section>

  <!-- สถานีวัดระดับน้ำ เทศบาลนครนนทบุรี -->
  <section class="card wide" id="stations">
    <h2>🌊 สถานีวัดระดับน้ำ เทศบาลนครนนทบุรี</h2>
    <div class="body">
      <div class="chips" id="nbChips"></div>
      <div class="table-wrap"><table><thead><tr>
        <th>สถานี</th><th>ประเภท</th><th>หน้า / ผิวถนน (ม.)</th><th>ท้าย (ม.)</th><th>ฝนวันนี้ (มม.)</th><th>สถานะ</th><th>เวลา</th>
      </tr></thead><tbody id="nbTable"></tbody></table></div>
      <div class="meta" id="nbNote"></div>
    </div>
  </section>
</main>

<footer>
  ข้อมูล: <a href="https://www.pakkretconnect.com/liffwater/eon" target="_blank" rel="noopener">Pakkret Connect</a> ·
  CCTV: stream.firsttech.co.th · เทศบาลเมืองปทุมธานี · เทศบาลนครนนทบุรี · ท่าอิฐ: Facebook
  · <a href="https://nonthaburi.thaiwater.net/wl" target="_blank" rel="noopener">ThaiWater (สสน.)</a> · ข้อมูลอัปเดตอัตโนมัติ (หยุดชั่วคราวเมื่อไม่ได้เปิดแท็บนี้)
</footer>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const REFRESH_MS = 60000;
const $ = id => document.getElementById(id);

// Polling that pauses while the tab is hidden, and catches up as soon as it's visible again
const tasks = [];
function every(ms, fn) {
  const t = { ms, fn, last: Date.now() };
  tasks.push(t);
  setInterval(() => { if (document.hidden) return; t.last = Date.now(); fn(); }, ms);
}
document.addEventListener('visibilitychange', () => {
  if (document.hidden) return;
  tasks.forEach(t => { if (Date.now() - t.last >= t.ms) { t.last = Date.now(); t.fn(); } });
});

// ---------- live viewer counter ----------
// Random per-tab id (sessionStorage: a reload keeps it, a new tab gets its own). Ping every 30 s while
// the tab is visible; hidden tabs stop pinging and drop out of the count within ~70 s.
const viewerId = (() => {
  try {
    let v = sessionStorage.getItem('wm-viewer');
    if (!v) { v = Math.random().toString(36).slice(2, 12) + Date.now().toString(36); sessionStorage.setItem('wm-viewer', v); }
    return v;
  } catch (_) { return Math.random().toString(36).slice(2, 12) + Date.now().toString(36); }
})();
async function pingViewers() {
  try {
    const r = await fetch('api/viewers.php?id=' + viewerId, { cache: 'no-store' });
    const d = await r.json();
    if (d.online != null) { $('viewersN').textContent = d.online.toLocaleString('th-TH'); $('viewers').hidden = false; }
  } catch (_) { /* counter is optional; ignore */ }
}
pingViewers();
every(30000, pingViewers);
window.addEventListener('pagehide', () => {   // closing/leaving the page: drop out of the count right away
  if (navigator.sendBeacon) navigator.sendBeacon('api/viewers.php?id=' + viewerId + '&leave=1');
});

// ---------- clock ----------
const clock = $('clock');
setInterval(() => { if (!document.hidden) clock.textContent = new Date().toLocaleString('th-TH'); }, 1000);

// ---------- live players (iframes) ----------
// Each player loads (= plays, from the live edge) when scrolled into view, is unloaded when it
// leaves the screen or the tab is hidden (saves bandwidth/CPU), and is reloaded every
// data-refresh minutes while on screen to recover from stalled streams.
const liveFrames = [...document.querySelectorAll('iframe[data-src]')];
const onScreen = new Set();
function playFrame(f) {
  clearTimeout(f._stopT);
  if (f.dataset.on) return;
  f.src = f.dataset.src;
  f.dataset.on = '1';
  f._since = Date.now();
}
function stopFrame(f) {
  if (!f.dataset.on) return;
  f.src = 'about:blank';
  delete f.dataset.on;
}
function reloadFrame(f) { stopFrame(f); setTimeout(() => playFrame(f), 100); }

if (window.IntersectionObserver) {
  const io = new IntersectionObserver(entries => entries.forEach(en => {
    const f = en.target;
    if (en.isIntersecting) {   // start only if it stays on screen (not while just scrolling past)
      onScreen.add(f);
      clearTimeout(f._playT);
      f._playT = setTimeout(() => { if (onScreen.has(f) && !document.hidden) playFrame(f); }, 600);
    } else {                   // grace period: a brief scroll away doesn't restart the stream
      onScreen.delete(f);
      clearTimeout(f._playT); clearTimeout(f._stopT);
      f._stopT = setTimeout(() => stopFrame(f), 3000);
    }
  }), { threshold: 0.25 });
  liveFrames.forEach(f => io.observe(f));
} else {
  liveFrames.forEach(f => { onScreen.add(f); playFrame(f); });
}
document.addEventListener('visibilitychange', () => {
  if (document.hidden) liveFrames.forEach(stopFrame);
  else onScreen.forEach(playFrame);
});
setInterval(() => {                          // periodic refresh of players that are playing
  if (document.hidden) return;
  liveFrames.forEach(f => {
    const min = +f.dataset.refresh || 0;
    if (min && f.dataset.on && Date.now() - f._since > min * 60000) reloadFrame(f);
  });
}, 30000);
document.querySelectorAll('[data-reload]').forEach(b => { b.onclick = () => reloadFrame($(b.dataset.reload)); });

// ---------- Pak Kret ----------
let pkData = null, chart = null, currentTab = 'daily';

function levelClass(v, t) { return v >= t.critical ? 'crit' : v >= t.watch ? 'warn' : 'ok'; }

function drawOverlay() {
  const img = $('pkImg'), cv = $('pkOverlay');
  const ov = pkData && pkData.overlay;
  if (!ov || !img.naturalWidth) return;
  cv.width = img.naturalWidth; cv.height = img.naturalHeight;
  const ctx = cv.getContext('2d');
  ctx.clearRect(0, 0, cv.width, cv.height);
  ctx.lineWidth = 6;
  for (const c of ['red', 'yellow', 'green']) {
    ctx.strokeStyle = c;
    (ov[c] || []).forEach(l => { ctx.beginPath(); ctx.moveTo(l.x1, l.y1); ctx.lineTo(l.x2, l.y2); ctx.stroke(); });
    ctx.fillStyle = c; ctx.font = 'bold 28px Sarabun, sans-serif';
    (ov[c + 'Text'] || []).forEach(t => ctx.fillText(t.text, t.x, t.y));
  }
}
$('pkImg').onload = () => { $('pkImg').hidden = false; $('pkMsg').hidden = true; drawOverlay(); };

function renderChart() {
  const h = pkData && pkData.history && pkData.history[currentTab];
  if (!h) return;
  const t = pkData.thresholds;
  const css = getComputedStyle(document.documentElement);
  const flat = v => h.labels.map(() => v);
  const cfg = {
    type: 'line',
    data: { labels: h.labels, datasets: [
      { label: 'ระดับน้ำ (ม. รทก.)', data: h.values, borderColor: css.getPropertyValue('--accent'), backgroundColor: 'rgba(10,111,181,.15)', fill: true, tension: .35, pointRadius: 2 },
      { label: 'เฝ้าระวัง', data: flat(t.watch), borderColor: css.getPropertyValue('--warn'), borderDash: [6, 4], pointRadius: 0, borderWidth: 1.5 },
      { label: 'วิกฤต', data: flat(t.critical), borderColor: css.getPropertyValue('--crit'), borderDash: [6, 4], pointRadius: 0, borderWidth: 1.5 },
    ]},
    options: {
      maintainAspectRatio: false, animation: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: css.getPropertyValue('--muted'), boxWidth: 12 } } },
      scales: {
        x: { ticks: { color: css.getPropertyValue('--muted'), maxRotation: 0, autoSkip: true }, grid: { color: css.getPropertyValue('--line') } },
        y: { suggestedMin: 0, suggestedMax: 2.6, ticks: { color: css.getPropertyValue('--muted') }, grid: { color: css.getPropertyValue('--line') } },
      },
    },
  };
  if (window.innerWidth < 600) {
    cfg.options.plugins.legend.labels.font = { size: 10 };
    cfg.options.scales.x.ticks.maxTicksLimit = 6;
    cfg.options.scales.x.ticks.font = cfg.options.scales.y.ticks.font = { size: 10 };
  }
  const key = currentTab + '|' + (window.innerWidth < 600);
  if (chart && chart.$key === key) {
    chart.data.labels = cfg.data.labels;
    chart.data.datasets.forEach((ds, i) => { ds.data = cfg.data.datasets[i].data; });
    chart.update('none');
  } else {
    if (chart) chart.destroy();
    chart = new Chart($('pkChart'), cfg);
    chart.$key = key;
  }
  renderStats(h, t);
}

function renderStats(h, t) {
  const v = h.values, n = v.length;
  if (!n) return;
  const iMax = v.indexOf(Math.max(...v)), iMin = v.indexOf(Math.min(...v));
  $('stMax').textContent = v[iMax].toFixed(2);  $('stMaxAt').textContent = h.labels[iMax];
  $('stMin').textContent = v[iMin].toFixed(2);  $('stMinAt').textContent = h.labels[iMin];
  $('stAvg').textContent = (v.reduce((a, b) => a + b, 0) / n).toFixed(2);
  $('stLast').textContent = v[n - 1].toFixed(2); $('stLastAt').textContent = h.labels[n - 1];
  const name = { ok: 'ปกติ', warn: 'เฝ้าระวัง', crit: 'วิกฤต' };
  $('pkTable').innerHTML = h.labels.map((l, i) => {
    const c = levelClass(v[i], t);
    const safe = String(l).replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
    return `<tr><td>${safe}</td><td>${v[i].toFixed(2)}</td><td><span class="dot" style="background:var(--${c})"></span>${name[c]}</td></tr>`;
  }).reverse().join('');
}

let resizeT;
window.addEventListener('resize', () => { clearTimeout(resizeT); resizeT = setTimeout(renderChart, 250); });

$('pkTabs').onclick = e => {
  const b = e.target.closest('button'); if (!b) return;
  currentTab = b.dataset.k;
  [...$('pkTabs').children].forEach(x => x.classList.toggle('active', x === b));
  renderChart();
};

async function loadPakkret(force = false) {
  try {
    const r = await fetch('api/pakkret.php' + (force ? '?fresh=1' : ''), { cache: 'no-store' });
    const text = await r.text();
    let d;
    try { d = JSON.parse(text); } catch (_) {
      // Not JSON: PHP isn't running (raw source returned), a PHP fatal error, or a 404
      if (text.trimStart().startsWith('<' + '?php')) throw new Error('เซิร์ฟเวอร์ไม่ได้รัน PHP (ได้ซอร์สโค้ดกลับมา)');
      throw new Error('api/pakkret.php ตอบกลับผิดรูปแบบ (HTTP ' + r.status + ') — เปิด api/pakkret.php?debug=1 เพื่อดูสาเหตุ');
    }
    if (!r.ok) throw new Error((d.error || r.status) + (d.detail && d.detail.error ? ' — ' + d.detail.error : ''));
    pkData = d;
    const t = d.thresholds;
    $('pkLevel').textContent = d.level != null ? d.level.toFixed(2) : '–';
    if (d.level != null) {
      const cls = levelClass(d.level, t);
      const b = $('pkBadge');
      b.hidden = false; b.className = 'badge ' + cls;
      b.textContent = d.status || { ok: 'ปกติ', warn: 'เฝ้าระวัง', crit: 'วิกฤต' }[cls];
      $('pkPin').style.left = Math.min(100, Math.max(0, d.level / 3 * 100)) + '%';
    }
    $('pkTime').textContent = 'เวลาวัด: ' + (d.time || '-') + (d.stale ? ' (ข้อมูลเก่า — ต้นทางไม่ตอบสนอง)' : '');
    if (d.image_url) $('pkImg').src = d.image_url;
    renderChart();
  } catch (err) {
    $('pkMsg').hidden = false;
    $('pkMsg').textContent = 'โหลดข้อมูลปากเกร็ดไม่สำเร็จ: ' + err.message;
  }
}
loadPakkret(true);                          // page load/refresh: data at most 15 s old
every(REFRESH_MS, () => loadPakkret());

// ---------- Nonthaburi (CCTV grid + stations table) ----------
function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

const NB_STATUS = {
  success: { t: 'ปกติ', c: 'var(--ok)' },
  warning: { t: 'เฝ้าระวัง', c: 'var(--warn)' },
  danger: { t: 'วิกฤต', c: 'var(--crit)' },
  black: { t: 'ขาดการติดต่อ', c: '#6b7280' },
  white: { t: 'ไม่มีข้อมูล', c: 'var(--line)' },
};
function nbStatusInfo(s) { return NB_STATUS[s] || NB_STATUS.white; }
const NB_RANK = { danger: 0, warning: 1, black: 2, success: 3, white: 4 };

function worstStatus(st) {
  const cands = [st.wl_up, st.wl_down].filter(x => x && x.status).map(x => x.status);
  if (!cands.length) return 'white';
  cands.sort((a, b) => (NB_RANK[a] != null ? NB_RANK[a] : 4) - (NB_RANK[b] != null ? NB_RANK[b] : 4));
  return cands[0];
}

function nbReadingsLine(st) {
  const parts = [];
  if (st.wl_up) parts.push((st.type === 'road' ? 'ผิวถนน ' : 'หน้า ') + st.wl_up.now.toFixed(2) + ' ม.');
  if (st.wl_down) parts.push('ท้าย ' + st.wl_down.now.toFixed(2) + ' ม.');
  if (st.rf) parts.push('ฝนวันนี้ ' + st.rf.day.toFixed(1) + ' มม.');
  if (st.date) parts.push('อัปเดต ' + String(st.date).slice(-5));
  return parts.join(' · ');
}

function nbTrendHtml(sensor) {
  if (!sensor) return '–';
  const arrow = sensor.trend === 'up' ? '▲' : sensor.trend === 'down' ? '▼' : '';
  const color = nbStatusInfo(sensor.status).c;
  return sensor.now.toFixed(2) + (arrow ? ' <span style="color:' + color + '">' + arrow + '</span>' : '');
}

let nbStations = [];
let nbCamKey = null;
let nbObserver = null;
let nbVisible = new Set();
let nbZone = '';

function nbCamSetKey(stations) {
  return stations.map(s => (s.cams || []).map(c => c.name).join(',')).join('|');
}

// data-src is a versioned URL (&v = snapshot mtime): the browser caches it, so an image is downloaded
// again only when the server has a new snapshot. `bust` forces a re-request (used for the placeholder).
function nbLoadImg(img, bust) {
  const ds = img.getAttribute('data-src');
  if (!ds) return;
  const src = bust ? ds + '&r=' + Date.now() : ds;
  if (img.getAttribute('src') !== src) img.src = src;
}
function nbThumbSrc(cam) { return cam.url + '&w=480&v=' + (cam.v || 0); }

// After each station refresh: point tiles at their new snapshot versions; reload only changed, visible ones
function nbSyncVersions(stations) {
  let k = 0;
  [...$('nbGrid').querySelectorAll('figure.cam')].forEach(fig => {
    const st = stations.find(s => s.code === fig.dataset.code);
    const cam = st && st.cams && st.cams[Number(fig.dataset.cam)];
    const img = fig.querySelector('img');
    if (!cam || !img) return;
    const ds = nbThumbSrc(cam);
    if (img.getAttribute('data-src') === ds) return;
    img.setAttribute('data-src', ds);
    if (nbVisible.has(img) || !nbObserver) setTimeout(() => nbLoadImg(img), (k++) * 300);   // staggered
  });
}

function nbSetupObserver() {
  if (nbObserver) nbObserver.disconnect();
  nbVisible = new Set();
  const imgs = [...$('nbGrid').querySelectorAll('img[data-src]')];
  if (!window.IntersectionObserver) {
    imgs.forEach(nbLoadImg);
    return;
  }
  nbObserver = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (en.isIntersecting) { nbVisible.add(en.target); nbLoadImg(en.target); }
      else nbVisible.delete(en.target);
    });
  }, { rootMargin: '200px' });
  imgs.forEach(img => nbObserver.observe(img));
}

function nbApplyFilter() {
  const q = $('nbSearch').value.trim().toLowerCase();
  [...$('nbGrid').querySelectorAll('figure.cam')].forEach(fig => {
    const zoneOk = !nbZone || fig.dataset.zone === nbZone;
    const textOk = !q || fig.dataset.text.indexOf(q) !== -1;
    fig.hidden = !(zoneOk && textOk);
  });
  const any = [...$('nbGrid').querySelectorAll('figure.cam')].some(f => !f.hidden);
  $('nbEmpty').hidden = any;
}
$('nbZone').onclick = e => {
  const b = e.target.closest('button'); if (!b) return;
  nbZone = b.dataset.z;
  [...$('nbZone').children].forEach(x => x.classList.toggle('active', x === b));
  nbApplyFilter();
};
$('nbSearch').oninput = nbApplyFilter;

function openLightbox(src, captionHtml, readingsText, alt) {
  const box = document.createElement('div');
  box.className = 'lightbox';
  box.innerHTML =
    '<div class="lb-inner">' +
      '<img src="' + src + '" alt="' + esc(alt || '') + '">' +
      '<div class="lb-cap">' + captionHtml + '</div>' +
      '<div class="lb-readings">' + esc(readingsText || '') + '</div>' +
    '</div>' +
    '<button type="button" class="lb-close" aria-label="ปิด">×</button>';
  document.body.appendChild(box);
  document.body.style.overflow = 'hidden';
  function close() {
    if (box.parentNode) box.parentNode.removeChild(box);
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
  }
  function onKey(e) { if (e.key === 'Escape') close(); }
  box.addEventListener('click', e => { if (e.target === box) close(); });
  box.querySelector('.lb-close').onclick = close;
  document.addEventListener('keydown', onKey);
}

function nbOpenLightbox(st, cam, idx) {
  const cams = st.cams || [];
  const capHtml = '<code>' + esc(st.code) + '</code> ' + esc(st.name) + (cams.length > 1 ? ' · Cam' + (idx + 1) : '');
  openLightbox(cam.url + '&v=' + (cam.v || 0), capHtml, nbReadingsLine(st), st.code + ' ' + st.name);
}

function nbBuildGrid(stations) {
  const parts = [];
  let n = 0;
  stations.forEach(st => {
    const cams = st.cams || [];
    if (!cams.length) return;
    const worst = worstStatus(st);
    const dotColor = nbStatusInfo(worst).c;
    const plainLabel = esc(st.code + ' ' + st.name);
    const capLabel = '<code>' + esc(st.code) + '</code> ' + esc(st.name);
    cams.forEach((cam, i) => {
      n++;
      const suffix = cams.length > 1 ? ' · Cam' + (i + 1) : '';
      parts.push(
        '<figure class="cam" data-zone="' + esc(st.zone || '') + '" data-text="' + esc((st.code + ' ' + st.name).toLowerCase()) + '" data-code="' + esc(st.code) + '" data-cam="' + i + '">' +
          '<img alt="' + plainLabel + '" loading="lazy" decoding="async" data-src="' + nbThumbSrc(cam) + '">' +
          '<figcaption><span class="dot" style="background:' + dotColor + '"></span>' + capLabel + suffix + '</figcaption>' +
        '</figure>'
      );
    });
  });
  $('nbGrid').innerHTML = parts.join('');
  $('nbCamCount').textContent = '(' + n + ' กล้อง)';
  [...$('nbGrid').querySelectorAll('figure.cam')].forEach(fig => {
    const img = fig.querySelector('img');
    img.onerror = () => fig.classList.add('err');
    img.onload = () => {
      fig.classList.remove('err');
      // Tiny SVG placeholder = snapshot not cached yet (server is fetching it): retry soon
      if (img.naturalWidth < 100) setTimeout(() => { if (nbVisible.has(img) || !nbObserver) nbLoadImg(img, true); }, 8000);
    };
    fig.addEventListener('click', () => {
      const code = fig.dataset.code, idx = Number(fig.dataset.cam);
      const st = nbStations.find(s => s.code === code);
      if (st && st.cams && st.cams[idx]) nbOpenLightbox(st, st.cams[idx], idx);
    });
  });
  nbSetupObserver();
  nbApplyFilter();
}

function nbRenderTable(stations) {
  const online = stations.filter(s => s.online && (s.wl_up || s.wl_down));
  online.sort((a, b) => {
    const ra = NB_RANK[worstStatus(a)] ?? 4, rb = NB_RANK[worstStatus(b)] ?? 4;
    if (ra !== rb) return ra - rb;
    return String(a.code).localeCompare(String(b.code), 'th', { numeric: true });
  });
  const counts = {};
  online.forEach(s => { const w = worstStatus(s); counts[w] = (counts[w] || 0) + 1; });

  $('nbTable').innerHTML = online.map(s => {
    const worst = worstStatus(s);
    const info = nbStatusInfo(worst);
    const type = s.type === 'road' ? 'ผิวถนน' : 'ประตูระบายน้ำ';
    const time = esc(String(s.date || '').slice(-5));
    return '<tr><td><code>' + esc(s.code) + '</code> ' + esc(s.name) + '</td>' +
      '<td>' + type + '</td>' +
      '<td>' + nbTrendHtml(s.wl_up) + '</td>' +
      '<td>' + nbTrendHtml(s.wl_down) + '</td>' +
      '<td>' + (s.rf ? s.rf.day.toFixed(1) : '–') + '</td>' +
      '<td><span class="dot" style="background:' + info.c + '"></span>' + info.t + '</td>' +
      '<td>' + time + '</td></tr>';
  }).join('');

  const chipOrder = ['danger', 'warning', 'success', 'black'];
  $('nbChips').innerHTML = chipOrder.filter(k => counts[k]).map(k => {
    const info = nbStatusInfo(k);
    const textColor = k === 'warning' ? '#000' : '#fff';
    return '<span class="chip" style="background:' + info.c + ';color:' + textColor + '">' + info.t + ' ' + counts[k] + '</span>';
  }).join('');

  const offline = stations.length - stations.filter(s => s.online).length;
  $('nbNote').textContent = 'ไม่แสดง ' + offline + ' สถานีที่ไม่ได้อัปเดตเกิน 2 ชั่วโมง';
}

async function loadNonthaburi() {
  try {
    const r = await fetch('api/nonthaburi.php', { cache: 'no-store' });
    const text = await r.text();
    let d;
    try { d = JSON.parse(text); } catch (_) {
      throw new Error('api/nonthaburi.php ตอบกลับผิดรูปแบบ (HTTP ' + r.status + ')');
    }
    if (!r.ok) throw new Error(d.error || r.status);
    const stations = d.stations || [];
    nbStations = stations;
    const key = nbCamSetKey(stations);
    if (nbCamKey === null || key !== nbCamKey) {
      nbCamKey = key;
      nbBuildGrid(stations);
    } else {
      nbSyncVersions(stations);
    }
    nbRenderTable(stations);
    $('nbMsg').textContent = d.stale ? 'ข้อมูลสถานีเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)' : '';
  } catch (err) {
    $('nbMsg').textContent = 'โหลดข้อมูล CCTV/สถานีไม่สำเร็จ: ' + err.message;
  }
}
loadNonthaburi();
every(60000, loadNonthaburi);

// ---------- ThaiWater (สสน.) telemetry ----------
const twCharts = {};

function twTextColor(hex) {       // black text on light badge colors (e.g. yellow)
  const m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
  if (!m) return '#fff';
  const n = parseInt(m[1], 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
  return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#000' : '#fff';
}

function twRenderChart(st, canvas) {
  const css = getComputedStyle(document.documentElement);
  // Axis shows "HH:MM", or "DD/MM" at midnight; tooltip shows the full "DD/MM HH:MM"
  const labels = st.series.map(p => p[0].slice(8, 10) + '/' + p[0].slice(5, 7) + ' ' + p[0].slice(11, 16));
  const values = st.series.map(p => p[1]);
  const bank = st.bank != null ? labels.map(() => st.bank) : [];
  const c = twCharts[st.id];
  if (c) {
    c.data.labels = labels;
    c.data.datasets[0].data = values;
    c.data.datasets[1].data = bank;
    c.update('none');
    return;
  }
  twCharts[st.id] = new Chart(canvas, {
    type: 'line',
    data: { labels, datasets: [
      { label: 'ระดับน้ำ (ม.รทก.)', data: values, borderColor: css.getPropertyValue('--accent'), backgroundColor: 'rgba(10,111,181,.12)', fill: true, tension: .3, pointRadius: 0, borderWidth: 2 },
      { label: 'ตลิ่ง', data: bank, borderColor: css.getPropertyValue('--crit'), borderDash: [6, 4], pointRadius: 0, borderWidth: 1.5 },
    ]},
    options: {
      maintainAspectRatio: false, animation: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: css.getPropertyValue('--muted'), boxWidth: 12, font: { size: 11 } } } },
      scales: {
        x: { ticks: { color: css.getPropertyValue('--muted'), maxRotation: 0, autoSkip: true, maxTicksLimit: 7, font: { size: 10 },
               callback(v) { const l = this.getLabelForValue(v); return l.endsWith(' 00:00') ? l.slice(0, 5) : l.slice(6); } }, grid: { display: false } },
        y: { ticks: { color: css.getPropertyValue('--muted'), font: { size: 10 } }, grid: { color: css.getPropertyValue('--line') } },
      },
    },
  });
}

function twRender(stations) {
  const grid = $('twGrid');
  stations.forEach(st => {
    let tile = grid.querySelector('[data-id="' + st.id + '"]');
    if (!tile) {
      tile = document.createElement('div');
      tile.className = 'tw';
      tile.dataset.id = st.id;
      tile.innerHTML = '<div class="tw-head"><b class="tw-name"></b><small class="tw-sub"></small></div>' +
        '<div class="level"><span class="num tw-wl"></span><span>ม.รทก.</span><span class="badge tw-badge"></span></div>' +
        '<div class="twbar"><div class="tw-fill"></div></div>' +
        '<div class="meta tw-meta"></div>' +
        '<div class="tw-chart"><canvas></canvas></div>';
      grid.appendChild(tile);
    }
    const sit = st.situation || {};
    const q = sel => tile.querySelector(sel);
    q('.tw-name').textContent = st.name;
    q('.tw-sub').textContent = [st.code, st.river, st.amphoe ? 'อ.' + st.amphoe : ''].filter(Boolean).join(' · ');
    q('.tw-wl').textContent = st.wl != null ? st.wl.toFixed(2) : '–';
    const badge = q('.tw-badge');
    badge.textContent = sit.text || '';
    badge.style.background = sit.color || 'var(--line)';
    badge.style.color = twTextColor(sit.color);
    const fill = q('.tw-fill');
    fill.style.width = Math.max(0, Math.min(100, st.pct || 0)) + '%';
    fill.style.background = sit.color || 'var(--line)';
    const trend = st.wl != null && st.wl_prev != null ? (st.wl > st.wl_prev ? ' ▲' : st.wl < st.wl_prev ? ' ▼' : '') : '';
    q('.tw-meta').textContent = [
      st.bank != null ? 'ตลิ่ง ' + st.bank.toFixed(2) + ' ม.รทก.' : '',
      st.diff_bank != null ? (st.diff_text || 'ต่างจากตลิ่ง (ม.)').replace(' (ม.)', '') + ' ' + st.diff_bank.toFixed(2) + ' ม.' : '',
      st.pct != null ? st.pct.toFixed(1) + '% ของตลิ่ง' + trend : '',
      st.datetime ? 'เวลา ' + st.datetime.slice(-5) : '',
    ].filter(Boolean).join(' · ');
    if (st.series && st.series.length) twRenderChart(st, q('canvas'));
  });
}

async function loadThaiwater() {
  try {
    const r = await fetch('api/thaiwater.php', { cache: 'no-store' });
    const text = await r.text();
    let d;
    try { d = JSON.parse(text); } catch (_) { throw new Error('api/thaiwater.php ตอบกลับผิดรูปแบบ (HTTP ' + r.status + ')'); }
    if (!r.ok) throw new Error(d.error || r.status);
    twRender(d.stations || []);
    $('twMsg').textContent = d.stale ? 'ข้อมูลเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)' : '';
  } catch (err) {
    $('twMsg').textContent = 'โหลดข้อมูล ThaiWater ไม่สำเร็จ: ' + err.message;
  }
}
loadThaiwater();
every(300000, loadThaiwater);

// ---------- Basin: upstream Chao Phraya (ThaiWater) ----------
let bsStations = [], bsProv = '';

function bsFmt(v, d) { return v == null ? '–' : Number(v).toFixed(d); }

function bsRenderKey(list) {
  const parts = [];
  list.forEach((st, i) => {
    const sit = st.situation || {};
    const big = st.discharge != null
      ? Number(st.discharge).toLocaleString('th-TH') + ' <small style="display:inline">ลบ.ม./วิ</small>'
      : bsFmt(st.wl, 2) + ' <small style="display:inline">ม.รทก.</small>';
    const sub = st.discharge != null && st.qmax
      ? Math.round(st.discharge / st.qmax * 100) + '% ของความจุลำน้ำ'
      : (st.discharge != null && st.wl != null ? 'ระดับ ' + bsFmt(st.wl, 2) + ' ม.รทก.' : '');
    if (i) parts.push('<span class="bs-arrow" aria-hidden="true">→</span>');
    parts.push(
      '<div class="bs-tile' + (st.recent ? '' : ' old') + '">' +
        '<div><span class="bs-code">' + esc(st.code) + '</span> ' + esc(st.name) + '<small>' + esc(st.province) + '</small></div>' +
        '<div class="bs-big">' + big + '</div>' +
        (sub ? '<small>' + esc(sub) + '</small>' : '') +
        (sit.text ? '<span class="badge" style="background:' + esc(sit.color || 'var(--line)') + ';color:' + twTextColor(sit.color) + '">' + esc(sit.text) + '</span>' : '') +
        '<small>' + (st.pct != null ? Number(st.pct).toFixed(0) + '% ตลิ่ง · ' : '') + esc(String(st.datetime || '').slice(-5)) + (st.recent ? '' : ' (ข้อมูลเก่า)') + '</small>' +
      '</div>'
    );
  });
  $('bsKey').innerHTML = parts.join('');
}

function bsRenderDams(list) {
  $('bsDams').innerHTML = list.map(d => {
    const p = Number(d.storage_pct) || 0;
    const color = p > 100 ? 'var(--crit)' : p > 80 ? 'var(--warn)' : 'var(--ok)';
    return '<div class="tw"><div class="tw-head"><b>' + esc(d.name) + '</b></div>' +
      '<div class="twbar"><div style="width:' + Math.max(0, Math.min(100, p)) + '%;background:' + color + '"></div></div>' +
      '<div class="meta">เก็บกัก ' + bsFmt(d.storage_pct, 1) + '% · ระบายวันนี้ ' + bsFmt(d.released, 2) + ' ล้าน ลบ.ม. · ไหลเข้า ' + bsFmt(d.inflow, 2) + '</div>' +
      '<div class="meta">ข้อมูลวันที่ ' + esc(d.date) + '</div></div>';
  }).join('');
}

function bsRenderTable() {
  const rows = bsStations.filter(s => !bsProv || s.province === bsProv);
  $('bsTable').innerHTML = rows.map(s => {
    const sit = s.situation || {};
    const arrow = s.wl != null && s.wl_prev != null ? (s.wl > s.wl_prev ? ' ▲' : s.wl < s.wl_prev ? ' ▼' : '') : '';
    return '<tr><td><code>' + esc(s.code) + '</code> ' + esc(s.name) + '</td>' +
      '<td>' + esc(s.province) + '</td><td>' + esc(s.river) + '</td>' +
      '<td>' + bsFmt(s.wl, 2) + arrow + '</td>' +
      '<td>' + (s.pct != null ? Number(s.pct).toFixed(0) + '%' : '–') + '</td>' +
      '<td><span class="dot" style="background:' + esc(sit.color || 'var(--line)') + '"></span>' + esc(sit.text || '–') + '</td>' +
      '<td>' + esc(String(s.datetime || '').slice(-5)) + '</td></tr>';
  }).join('') || '<tr><td colspan="7">ไม่มีข้อมูลสถานี</td></tr>';
}

$('bsProv').onclick = e => {
  const b = e.target.closest('button'); if (!b) return;
  bsProv = b.dataset.p;
  [...$('bsProv').children].forEach(x => x.classList.toggle('active', x === b));
  bsRenderTable();
};

function bsRenderGates(list) {
  $('bsGatesSum').textContent = 'ประตูระบายน้ำ (' + list.length + ')';
  $('bsGates').innerHTML = list.map(g =>
    '<tr><td>' + esc(g.name) + '</td><td>' + esc(g.province) + '</td><td>' + bsFmt(g.up, 2) + '</td><td>' + bsFmt(g.down, 2) +
    '</td><td>' + esc(String(g.datetime || '').slice(-5)) + '</td></tr>'
  ).join('') || '<tr><td colspan="5">ไม่มีข้อมูลประตูระบายน้ำ</td></tr>';
}

async function loadBasin() {
  try {
    const r = await fetch('api/basin.php', { cache: 'no-store' });
    const text = await r.text();
    let d;
    try { d = JSON.parse(text); } catch (_) { throw new Error('api/basin.php ตอบกลับผิดรูปแบบ (HTTP ' + r.status + ')'); }
    if (!r.ok) throw new Error(d.error || r.status);
    bsStations = d.stations || [];
    bsRenderKey(d.key_stations || []);
    bsRenderDams(d.dams || []);
    bsRenderTable();
    bsRenderGates(d.gates || []);
    let over = 0, many = 0;
    bsStations.forEach(s => {
      const t = s.situation && s.situation.text;
      if (t === 'น้ำล้นตลิ่ง') over++; else if (t === 'น้ำมาก') many++;
    });
    $('bsSummary').textContent = 'ล้นตลิ่ง ' + over + ' สถานี · น้ำมาก ' + many + ' สถานี';
    const msgs = [];
    if (d.stale) msgs.push('ข้อมูลเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)');
    const errs = d.errors || [];
    if (errs.indexOf('gates') !== -1) msgs.push('ข้อมูลประตูระบายน้ำไม่พร้อมใช้งาน');
    if (errs.indexOf('dams') !== -1) msgs.push('ข้อมูลเขื่อนไม่พร้อมใช้งาน');
    $('bsMsg').textContent = msgs.join(' · ');
  } catch (err) {
    $('bsMsg').textContent = 'โหลดข้อมูลต้นน้ำไม่สำเร็จ: ' + err.message;
  }
}
loadBasin();
every(600000, loadBasin);

// ---------- Rangsit CDP cameras ----------
const RS_LEVEL = {
  CRITICAL: { t: 'วิกฤต', c: 'var(--crit)' },
  WARNING: { t: 'เตือนภัย', c: 'var(--warn)' },
  WATCH: { t: 'เฝ้าระวัง', c: 'var(--warn)' },
  NORMAL: { t: 'ปกติ', c: 'var(--ok)' },
};
let rsPoints = [], rsKey = null, rsObserver = null, rsVisible = new Set();

function rsLevelInfo(level) { return RS_LEVEL[level] || { t: level || '–', c: 'var(--line)' }; }
function rsTime(iso) {
  const t = new Date(iso);
  return isNaN(t) ? '' : t.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', hour12: false });
}
function rsLoadImg(img) {
  const ds = img.getAttribute('data-src');
  if (ds) img.src = ds + '?t=' + Date.now();
}
function rsInfoHtml(p) {
  const lv = rsLevelInfo(p.level);
  const txt = lv.c === 'var(--warn)' ? '#000' : lv.c === 'var(--line)' ? 'var(--text)' : '#fff';
  const tm = rsTime(p.updated_at);
  return '<span class="badge" style="background:' + lv.c + ';color:' + txt + '">' + esc(lv.t) + '</span>' +
    (p.reason ? '<div class="meta">' + esc(p.reason) + '</div>' : '') +
    (tm ? '<div class="meta">อัปเดต ' + esc(tm) + '</div>' : '');
}
function rsOpen(p) {
  const lv = rsLevelInfo(p.level);
  const tm = rsTime(p.updated_at);
  const cap = esc(p.name) + ' · ' + esc(lv.t);
  const reading = (p.reason || '') + (tm ? (p.reason ? ' · ' : '') + 'อัปเดต ' + tm : '');
  openLightbox(p.image + '?t=' + Date.now(), cap, reading, p.name);
}
function rsBuildGrid(points) {
  $('rsGrid').innerHTML = points.map(p =>
    '<figure class="cam" data-id="' + esc(p.id) + '">' +
      '<img alt="' + esc(p.name) + '" loading="lazy" decoding="async" data-src="' + esc(p.image) + '">' +
      '<figcaption>' + esc(p.name) + '</figcaption>' +
      '<div class="rs-info"></div>' +
    '</figure>'
  ).join('');
  if (rsObserver) rsObserver.disconnect();
  rsVisible = new Set();
  const figs = [...$('rsGrid').querySelectorAll('figure.cam')];
  figs.forEach(fig => {
    const img = fig.querySelector('img');
    img.onerror = () => fig.classList.add('err');
    img.onload = () => fig.classList.remove('err');
    fig.addEventListener('click', () => {
      const p = rsPoints.find(x => String(x.id) === fig.dataset.id);
      if (p) rsOpen(p);
    });
  });
  const imgs = figs.map(f => f.querySelector('img'));
  if (!window.IntersectionObserver) { imgs.forEach(rsLoadImg); return; }
  rsObserver = new IntersectionObserver(entries => entries.forEach(en => {
    if (en.isIntersecting) { rsVisible.add(en.target); rsLoadImg(en.target); }
    else rsVisible.delete(en.target);
  }), { rootMargin: '200px' });
  imgs.forEach(img => rsObserver.observe(img));
}

async function loadRangsit() {
  try {
    const r = await fetch('api/rangsit.php', { cache: 'no-store' });
    const text = await r.text();
    let d;
    try { d = JSON.parse(text); } catch (_) { throw new Error('api/rangsit.php ตอบกลับผิดรูปแบบ (HTTP ' + r.status + ')'); }
    if (!r.ok) throw new Error(d.error || r.status);
    rsPoints = d.points || [];
    const key = rsPoints.map(p => p.id + '@' + p.image).join('|');
    const rebuilt = key !== rsKey;
    if (rebuilt) { rsKey = key; rsBuildGrid(rsPoints); }
    rsPoints.forEach(p => {
      const fig = $('rsGrid').querySelector('figure.cam[data-id="' + p.id + '"]');
      if (!fig) return;
      fig.querySelector('.rs-info').innerHTML = rsInfoHtml(p);
      if (!rebuilt) {
        const img = fig.querySelector('img');
        if (rsVisible.has(img) || !rsObserver) rsLoadImg(img);
      }
    });
    if (!rsPoints.length) $('rsGrid').innerHTML = '';
    $('rsMsg').textContent = d.stale ? 'ข้อมูลเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)' : (rsPoints.length ? '' : 'ไม่พบกล้อง');
  } catch (err) {
    $('rsMsg').textContent = 'โหลดข้อมูลกล้องรังสิตไม่สำเร็จ: ' + err.message;
  }
}
loadRangsit();
every(60000, loadRangsit);
</script>
</body>
</html>
