<?php
// Official MediaMTX player page: its video segments have no CORS headers, so they can't be played from our own page.
$nonthaburiPlayer = 'https://stream.firsttech.co.th/live/nakornnont.stream/?muted=1&autoplay=1&controls=1';
$pathumPlayer = 'http://101.109.253.60:8999/';   // เทศบาลเมืองปทุมธานี (own player page; segments have no CORS either)
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
  <link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>">
</head>

<body>
  <header>
    <div>
      <h1>🌊 เฝ้าระวังระดับน้ำ แม่น้ำเจ้าพระยา</h1>
      <p>ท่าน้ำนนทบุรี · ท่าน้ำปากเกร็ด · ท่าน้ำปทุมธานี · สถานีวัดน้ำนนทบุรี</p>
    </div>
    <div class="hdr-right">
      <span class="viewers" id="viewers" hidden title="จำนวนผู้ที่เปิดหน้านี้อยู่ในขณะนี้">กำลังดู <b
          id="viewersN">–</b> คน</span>
      <div id="clock"></div>
    </div>
  </header>

  <nav class="secnav">
    <div class="inner">
      <a href="#river">ท่าน้ำ</a>
      <a href="#pathum">ปทุมธานี</a>
      <a href="#history">ระดับน้ำย้อนหลัง</a>
      <a href="#thaiwater">ThaiWater</a>
      <a href="#basin">ต้นน้ำ</a>
      <a href="#rangsit">กล้องรังสิต</a>
      <a href="#links">ลิงก์กล้องอื่น</a>
      <a href="#allcctv">CCTV ทุกแหล่ง</a>
      <a href="#stations">สถานีวัดน้ำ</a>
    </div>
  </nav>

  <main>
    <!-- ท่าน้ำนนท์ -->
    <section class="card" id="river">
      <h2>📹 ท่าน้ำนนทบุรี <span class="live">LIVE</span></h2>
      <div class="media">
        <iframe id="nonFrame" data-src="<?= htmlspecialchars($nonthaburiPlayer) ?>" data-refresh="30"
          title="กล้องท่าน้ำนนทบุรี" allow="autoplay; fullscreen; picture-in-picture"></iframe>
      </div>
      <div class="body">
        <div class="meta">ภาพสดจากกล้อง CCTV ท่าน้ำนนท์ · <button type="button" class="linkbtn"
            data-reload="nonFrame">เชื่อมต่อใหม่</button> · เล่นอัตโนมัติเมื่อเลื่อนมาเห็น</div>
        <p class="legend" style="margin-top:12px">สังเกตระดับน้ำเทียบกับขอบเขื่อน/ท่าเรือจากภาพสด
          ใช้ค่าจาก Sensor ปากเกร็ดด้านข้างเป็นตัวเลขอ้างอิงของแม่น้ำช่วงเดียวกัน (ห่างกันราว 10 กม.)</p>
      </div>
    </section>

    <!-- ท่าน้ำปากเกร็ด -->
    <section class="card" id="pkriver">
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
          <div class="bar">
            <div class="pin" id="pkPin" style="left:0"></div>
          </div>
          <div class="scale"><span>0</span><span>1.50</span><span>2.30</span><span>3.00 ม.</span></div>
        </div>

        <div class="legend" style="margin-top:12px">
          <div><span style="background:var(--ok)"></span>ปกติ: ไม่เกิน 1.50 ม. รทก.</div>
          <div><span style="background:var(--warn)"></span>เฝ้าระวัง: 1.50–2.30 ม. รทก.
            (ประชาชนริมน้ำพื้นที่เสี่ยงควรยกของขึ้นที่สูง)</div>
          <div><span style="background:var(--crit)"></span>วิกฤต: ตั้งแต่ 2.30 ม. รทก. ขึ้นไป</div>
        </div>
      </div>
    </section>

    <!-- ท่าน้ำ เทศบาลเมืองปทุมธานี -->
    <section class="card" id="pathum">
      <h2>📹 ท่าน้ำ เทศบาลเมืองปทุมธานี <span class="live">LIVE</span></h2>
      <div class="media">
        <iframe id="ptFrame" data-src="<?= htmlspecialchars($pathumPlayer) ?>" data-refresh="10"
          title="กล้องท่าน้ำ เทศบาลเมืองปทุมธานี" allow="autoplay; fullscreen"></iframe>
      </div>
      <div class="body">
        <div class="meta">ภาพสดจากกล้องท่าน้ำ เทศบาลเมืองปทุมธานี · <button type="button" class="linkbtn"
            data-reload="ptFrame">เชื่อมต่อใหม่</button> · <a href="<?= htmlspecialchars($pathumPlayer) ?>"
            target="_blank" rel="noopener">เปิดหน้าต้นทาง</a></div>
        <p class="legend" style="margin-top:12px">กล้องนี้ส่งภาพเป็นช่วงละประมาณ 1 นาที ภาพจึงอาจช้ากว่าเวลาจริงหลายนาที
        </p>
      </div>
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
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ช่วงเวลา</th>
                  <th>ระดับน้ำ (ม. รทก.)</th>
                  <th>สถานะ</th>
                </tr>
              </thead>
              <tbody id="pkTable"></tbody>
            </table>
          </div>
        </details>
        <div class="meta">ที่มา: <a href="https://www.pakkretconnect.com/liffwater/eonGraph" target="_blank"
            rel="noopener">pakkretconnect.com/liffwater/eonGraph</a> · ค่ารายสัปดาห์/เดือน/ปี เป็นค่าเฉลี่ย</div>
      </div>
    </section>

    <!-- ThaiWater (สสน.) telemetry, Nonthaburi province -->
    <section class="card wide" id="thaiwater">
      <h2>📡 สถานีโทรมาตร สสน. จังหวัดนนทบุรี</h2>
      <div class="body">
        <div class="twgrid" id="twGrid"></div>
        <div class="meta" id="twMsg"></div>
        <div class="meta">ที่มา: <a href="https://nonthaburi.thaiwater.net/wl" target="_blank"
            rel="noopener">ศูนย์ข้อมูลน้ำระดับจังหวัดนนทบุรี (ThaiWater / สสน.)</a> · ข้อมูลทุก 10 นาที · กราฟย้อนหลัง
          48 ชม. · % คือระดับน้ำเทียบกับระดับตลิ่ง</div>
      </div>
    </section>

    <!-- ต้นน้ำเจ้าพระยา (ThaiWater basin) -->
    <section class="card wide" id="basin">
      <h2>🌊 น้ำจากต้นน้ำเจ้าพระยา → นนทบุรี</h2>
      <div class="body">
        <div class="bs-flow" id="bsKey"></div>
        <p class="legend">ปริมาณน้ำไหลผ่าน (ลบ.ม./วินาที) ที่นครสวรรค์ (C.2) และท้ายเขื่อนเจ้าพระยา (C.13)
          ใช้คาดการณ์น้ำที่จะมาถึงนนทบุรีในอีก 2–4 วัน</p>
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
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>สถานี</th>
                <th>จังหวัด</th>
                <th>ลำน้ำ</th>
                <th>ระดับ (ม.รทก.)</th>
                <th>% ตลิ่ง</th>
                <th>สถานการณ์</th>
                <th>เวลา</th>
              </tr>
            </thead>
            <tbody id="bsTable"></tbody>
          </table>
        </div>
        <details class="data">
          <summary id="bsGatesSum">ประตูระบายน้ำ</summary>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ประตู</th>
                  <th>จังหวัด</th>
                  <th>หน้า (ม.รทก.)</th>
                  <th>ท้าย (ม.รทก.)</th>
                  <th>เวลา</th>
                </tr>
              </thead>
              <tbody id="bsGates"></tbody>
            </table>
          </div>
        </details>
        <div class="meta" id="bsMsg"></div>
        <div class="meta">ที่มา: ThaiWater (สสน.) · อัปเดตทุก 10 นาที · ค้นพบแหล่งข้อมูลผ่าน <a
            href="https://rutunnam.vercel.app" target="_blank" rel="noopener">รู้ทันน้ำ</a></div>
      </div>
    </section>

    <!-- กล้องวัดระดับน้ำ รังสิต -->
    <section class="card wide" id="rangsit">
      <h2>📷 กล้องวัดระดับน้ำ รังสิต–ปทุมธานี (AI อ่านระดับน้ำ)</h2>
      <div class="body">
        <div class="camgrid rs" id="rsGrid"></div>
        <div class="meta" id="rsMsg"></div>
        <div class="meta">ที่มา: ศูนย์ข้อมูลภัยพิบัติ เทศบาลนครรังสิต (<a href="https://cdp.rangsitcity.go.th/"
            target="_blank" rel="noopener">cdp.rangsitcity.go.th</a>) · ระดับน้ำประเมินอัตโนมัติจากภาพ (OpenCV)
          โดยต้นทาง</div>
      </div>
    </section>

    <!-- ลิงก์กล้องอื่น -->
    <section class="card wide" id="links">
      <h2>🔗 กล้องและข้อมูลน้ำจากแหล่งอื่น</h2>
      <div class="body">
        <ul class="linklist">
          <li><a href="https://wmsc.rid.go.th/cctv2/" target="_blank" rel="noopener">ศูนย์กล้องลุ่มน้ำเจ้าพระยา
              (กรมชลประทาน)</a><small>เว็บไซต์ระบุว่ารองรับ Firefox</small></li>
          <li><a href="https://flood.pop.in.th/" target="_blank" rel="noopener">Flood Bangkok (POPNIX)</a><small>เว็บรวมกล้องอีกแห่ง
              มีกล้องสถานีเรดาร์ชายฝั่ง GISTDA และกรมทางหลวงชนบทเพิ่มเติม</small></li>
          <li><a href="https://www.skylinewebcams.com/en/webcam/thailand.html" target="_blank" rel="noopener">SkylineWebcams ประเทศไทย</a><small>เว็บกล้องเชิงพาณิชย์
              ไม่อนุญาตให้นำภาพไปแสดงบนเว็บอื่น จึงใส่เป็นลิงก์</small></li>
          <li><a href="http://www.bmatraffic.com/index.aspx" target="_blank" rel="noopener">กล้องจราจร กทม. (bmatraffic)</a><small>ขณะนี้ต้นทางไม่ส่งภาพ (Bad Gateway) จึงยังไม่นำมาแสดง</small></li>
          <li><a href="https://www.thaiclouderp.com/CCTV_MONITOR/web/pakkred" target="_blank"
              rel="noopener">กล้องถนนปากเกร็ด (เทศบาลนครปากเกร็ด)</a></li>
          <li><a href="https://rangsitcity.go.th/cctvrangsitcity/" target="_blank"
              rel="noopener">กล้องจราจรเทศบาลนครรังสิต</a></li>
          <li><a href="https://www.muangpathum.org/th/z_main.jsp" target="_blank" rel="noopener">เทศบาลเมืองปทุมธานี
              (กล้องสวนเทพปทุมฯ)</a></li>
          <li><a href="https://telemetry.dwr.go.th/reportCctv" target="_blank"
              rel="noopener">ศูนย์กล้องระดับน้ำทั่วประเทศ (กรมทรัพยากรน้ำ)</a></li>
          <li><a href="https://disaster.gistda.or.th/flood" target="_blank" rel="noopener">แผนที่น้ำท่วม 7 วัน
              (GISTDA)</a></li>
          <li><a href="https://rutunnam.vercel.app" target="_blank" rel="noopener">รู้ทันน้ำ —
              แผนที่สถานการณ์น้ำ</a><small>แหล่งอ้างอิงรายชื่อกล้องและแหล่งข้อมูลในส่วนนี้</small></li>
        </ul>
        <p class="legend">กล้องของ กทม. (dds.bangkok.go.th) ไม่ได้นำมาแสดง เพราะภาพไม่อัปเดตมาตั้งแต่ ส.ค. 2569</p>
      </div>
    </section>

    <!-- CCTV ทุกแหล่ง (cctv.maholan.net + พิกัดปากเกร็ดจาก thaiclouderp) -->
    <section class="card wide" id="allcctv">
      <h2>📷 CCTV ทุกแหล่ง <small id="acCount" class="meta"></small></h2>
      <div class="body">
        <div class="nbtools">
          <select id="acRegion" aria-label="จังหวัด"></select>
          <input id="acSearch" type="search" placeholder="ค้นหา ชื่อกล้อง เขต หรือหน่วยงาน เช่น ปากเกร็ด…">
          <label class="meta"><input type="checkbox" id="acInView" checked> แสดงเฉพาะกล้องในกรอบแผนที่</label>
          <label class="meta"><input type="checkbox" id="acDead" checked> แสดงกล้องที่ไม่ทำงานบนแผนที่ (สีเทา)</label>
        </div>
        <div id="acMap" role="region" aria-label="แผนที่ตำแหน่งกล้อง"></div>
        <div class="meta" id="acMapNote">
          <div class="mapkey">
            <span><i style="--s:20px;--c:#7c3aed"></i>กล้องท่าน้ำ (กดเพื่อดูภาพสด)</span>
            <span><i style="--s:16px;--c:#0d9488"></i>CCTV เทศบาลนครนนทบุรี (มีระดับน้ำ)</span>
            <span><i style="--s:16px;--c:#db2777"></i>กล้องวัดระดับน้ำ รังสิต (AI อ่านระดับน้ำ)</span>
            <span><i style="--s:12px;--c:#0a6fb5"></i>ภาพอัปเดตปกติ</span>
            <span><i style="--s:12px;--c:#e8890c"></i>ภาพอาจไม่อัปเดต</span>
            <span><i style="--s:8px;--c:#8a8f98"></i>ต้นทางหยุดส่งภาพ (เช่น กทม., iTIC)</span>
          </div>
          ชี้ที่จุดเพื่อดูภาพตัวอย่าง · กดจุดเพื่อดูภาพ · เลื่อน/ซูมแผนที่แล้วรายการกล้องด้านล่างจะเปลี่ยนตาม
        </div>
        <div class="camgrid" id="acGrid"></div>
        <div class="meta" id="acMsg">กำลังโหลดรายชื่อกล้อง…</div>
        <div class="meta">ที่มา: <a href="https://cctv.maholan.net/" target="_blank" rel="noopener">Flood CCTV Thailand</a>
          (รวมกล้อง เทศบาลนครปากเกร็ด, กรมทางหลวง, ปภ., iTIC, ThaiWater ฯลฯ) · พิกัดกล้องปากเกร็ด:
          <a href="https://www.thaiclouderp.com/CCTV_MONITOR/web/pakkred" target="_blank" rel="noopener">thaiclouderp</a> ·
          กล้องที่ซ้ำกันแสดงเฉพาะตัวที่ดีที่สุด · กล้องที่ต้นทางหยุดส่งภาพแสดงเป็นจุดสีเทาบนแผนที่เท่านั้น · ภาพอัปเดตทุก 1 นาที (เฉพาะกล้องที่อยู่บนจอ)</div>
      </div>
    </section>

    <!-- สถานีวัดระดับน้ำ เทศบาลนครนนทบุรี -->
    <section class="card wide" id="stations">
      <h2>🌊 สถานีวัดระดับน้ำ เทศบาลนครนนทบุรี</h2>
      <div class="body">
        <div class="chips" id="nbChips"></div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>สถานี</th>
                <th>ประเภท</th>
                <th>หน้า / ผิวถนน (ม.)</th>
                <th>ท้าย (ม.)</th>
                <th>ฝนวันนี้ (มม.)</th>
                <th>สถานะ</th>
                <th>เวลา</th>
              </tr>
            </thead>
            <tbody id="nbTable"></tbody>
          </table>
        </div>
        <div class="meta" id="nbNote"></div>
        <div class="meta" id="nbMsg"></div>
      </div>
    </section>
    <!-- หน้าเดิมของ CCTV นนทบุรี (ย้ายไปหน้าแยก) -->
    <section class="card wide pagelink">
      <a class="bigbtn" href="nbcctv.php">📷 CCTV เทศบาลนครนนทบุรี (มุมมองเดิม แยกตามโซน) →</a>
    </section>
  </main>

  <footer>
    ข้อมูล: <a href="https://www.pakkretconnect.com/liffwater/eon" target="_blank" rel="noopener">Pakkret Connect</a> ·
    CCTV: stream.firsttech.co.th · เทศบาลเมืองปทุมธานี · เทศบาลนครนนทบุรี · <a href="https://cctv.maholan.net/" target="_blank" rel="noopener">Flood CCTV Thailand</a>
    · <a href="https://nonthaburi.thaiwater.net/wl" target="_blank" rel="noopener">ThaiWater (สสน.)</a> ·
    ข้อมูลอัปเดตอัตโนมัติ (หยุดชั่วคราวเมื่อไม่ได้เปิดแท็บนี้)
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
        data: {
          labels: h.labels, datasets: [
            { label: 'ระดับน้ำ (ม. รทก.)', data: h.values, borderColor: css.getPropertyValue('--accent'), backgroundColor: 'rgba(10,111,181,.15)', fill: true, tension: .35, pointRadius: 2 },
            { label: 'เฝ้าระวัง', data: flat(t.watch), borderColor: css.getPropertyValue('--warn'), borderDash: [6, 4], pointRadius: 0, borderWidth: 1.5 },
            { label: 'วิกฤต', data: flat(t.critical), borderColor: css.getPropertyValue('--crit'), borderDash: [6, 4], pointRadius: 0, borderWidth: 1.5 },
          ]
        },
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
      $('stMax').textContent = v[iMax].toFixed(2); $('stMaxAt').textContent = h.labels[iMax];
      $('stMin').textContent = v[iMin].toFixed(2); $('stMinAt').textContent = h.labels[iMin];
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
    function nbThumbSrc(cam) { return cam.url + '&w=480&v=' + (cam.v || 0); }

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
        acNbUpdate();   // map markers + tiles in "CCTV ทุกแหล่ง"
        nbRenderTable(stations);
        $('nbMsg').textContent = d.stale ? 'ข้อมูลสถานีเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)' : '';
      } catch (err) {
        $('nbMsg').textContent = 'โหลดข้อมูลสถานีไม่สำเร็จ: ' + err.message;
      }
    }
    loadNonthaburi();
    every(60000, loadNonthaburi);

    // ---------- All-source CCTV (api/cctv.php) ----------
    // Row: [id, name, region, district, source, lat, lng, old (0 ok, 1 maybe stale, 2 dead), img?].
    // Dead cameras appear only on the map, as grey dots, so people can see a camera exists there but has no image. Images come straight from the maholan CDN (or img);
    // ?t is a 1-minute bucket so all viewers share one cached URL. Only on-screen tiles load and refresh.
    // The map (Leaflet, canvas dots) is loaded only when the section is near the screen; the grid follows its view.
    const AC_MS = 60000;
    let acCams = [], acText = [], acObs = null, acMap = null, acLayer = null;
    const acVis = new Set();
    function acBase(c) { return c[8] || 'https://cctv.maholan.net/api/snap/' + encodeURIComponent(c[0]) + '?'; }
    function acSrc(c) { return acBase(c) + 't=' + Math.floor(Date.now() / AC_MS); }
    function acLoad(img) {
      const s = img.dataset.rs ? img.dataset.rs + '?t=' + Math.floor(Date.now() / AC_MS) : img.dataset.n || (img.dataset.r != null ? acRiverSnap(AC_RIVER[img.dataset.r]) : img.dataset.b + 't=' + Math.floor(Date.now() / AC_MS));
      if (s && img.getAttribute('src') !== s) img.src = s;
    }
    function acPlace(c) {
      const t = esc([c[3], c[2]].filter(Boolean).join(', ') || 'ไม่ระบุ');
      return '📍 ' + (c[5] != null ? '<a href="https://www.google.com/maps?q=' + c[5] + ',' + c[6] + '" target="_blank" rel="noopener">' + t + '</a>' : t) +
        ' · ' + esc(c[4]) + (c[7] === 2 ? ' · ⛔ ต้นทางหยุดส่งภาพ' : c[7] ? ' · ⚠️ ภาพอาจไม่อัปเดต' : '');
    }
    function acMatch(i) {
      const r = $('acRegion').value, q = $('acSearch').value.trim().toLowerCase();
      return (!r || acCams[i][2] === r) && (!q || acText[i].indexOf(q) !== -1) && (acCams[i][7] < 2 || $('acDead').checked);
    }

    function acApplyFilter() {
      const b = acMap && $('acInView').checked ? acMap.getBounds() : null;
      let n = 0;
      const reg = $('acRegion').value, q = $('acSearch').value.trim().toLowerCase();
      $('acGrid').querySelectorAll('figure').forEach(f => {
        if (f.dataset.rs != null) {   // Rangsit CDP tile
          const p = rsPoints.find(x => String(x.id) === f.dataset.rs);
          f.hidden = !(p && acRsVisible(p) && (!b || b.contains([p.lat, p.lng])));
        } else if (f.dataset.nb != null) {   // Nonthaburi municipality tile
          const st = nbStations.find(x => x.code === f.dataset.nb);
          f.hidden = !(st && (!reg || reg === 'นนทบุรี') && (!q || (st.code + ' ' + st.name).toLowerCase().indexOf(q) !== -1) &&
            (!b || (st.lat != null && b.contains([st.lat, st.lng]))));
        } else if (f.dataset.r != null) {   // riverside tile: same view/province/search rules
          const r = AC_RIVER[f.dataset.r];
          f.hidden = !((!reg || r.prov === reg) && (!q || r.n.toLowerCase().indexOf(q) !== -1) && (!b || b.contains(r.ll)));
        } else {
          const i = Number(f.dataset.i), c = acCams[i];
          f.hidden = !(acMatch(i) && (!b || (c[5] != null && b.contains([c[5], c[6]]))));
        }
        if (!f.hidden && !f.classList.contains('gone')) n++;
      });
      $('acMsg').textContent = n ? 'แสดง ' + n + ' กล้อง' : (b ? 'ไม่มีกล้องในกรอบแผนที่ — ลองซูมออกหรือเลื่อนแผนที่' : 'ไม่พบกล้องที่ตรงกับการค้นหา');
    }

    function acDrawMarkers(fit) {
      if (!acMap) return;
      acLayer.clearLayers();
      const pts = [];
      acCams.forEach((c, i) => {
        if (c[5] == null || !acMatch(i)) return;
        pts.push([c[5], c[6]]);
        if (c[7] === 2) {
          L.circleMarker([c[5], c[6]], { radius: 4, weight: 1, color: '#fff', fillOpacity: .7, fillColor: '#8a8f98' })
            .bindPopup('<b>' + esc(c[1]) + '</b><br><small>' + acPlace(c) + '</small>', { maxWidth: 280 }).addTo(acLayer);
          return;
        }
        const m = L.circleMarker([c[5], c[6]], { radius: 6, weight: 1, color: '#fff', fillOpacity: .9, fillColor: c[7] ? '#e8890c' : '#0a6fb5' });
        if (acHover) m.bindTooltip(() => acTip(acSrc(c), c[1]), { direction: 'top', offset: [0, -6], className: 'ac-tip' });
        m.bindPopup(() => '<img src="' + acSrc(c) + '" alt="' + esc(c[1]) + '" data-i="' + i + '" style="cursor:zoom-in"><b>' + esc(c[1]) + '</b><br><small>' + acPlace(c) + '</small>', { maxWidth: 280 })
          .addTo(acLayer);
      });
      acNbDraw();
      acRsDraw();
      if (fit && pts.length) acMap.fitBounds(pts, { maxZoom: 14, padding: [20, 20] });
    }

    // Riverside cameras (cards at the top of the page) — always on the map, not affected by filters.
    // Hover = still snapshot (maholan thumbnail of the stream / Pak Kret image); click = live player, which
    // loads only while its popup is open. Positions: piers on OpenStreetMap; Pathum = river bank in front of the town.
    const AC_RIVER = [
      { n: 'ท่าน้ำนนทบุรี (ไม้วัดระดับน้ำ)', ll: [13.8422, 100.4910], prov: 'นนทบุรี', card: 'river', snap: 'nont-water', player: <?= json_encode($nonthaburiPlayer) ?> },
      { n: 'ท่าน้ำปากเกร็ด (หัวถนน)', ll: [13.9150, 100.4944], prov: 'นนทบุรี', card: 'pkriver', pk: true },
      { n: 'ท่าน้ำ เทศบาลเมืองปทุมธานี', ll: [14.0140, 100.5358], prov: 'ปทุมธานี', card: 'pathum', snap: 'pathum-muang', player: <?= json_encode($pathumPlayer) ?> },
    ];
    const acHover = window.matchMedia && matchMedia('(hover: hover)').matches;   // touch screens: popup only
    function acTip(src, name) { return '<img src="' + esc(src) + '" alt=""><b>' + esc(name) + '</b>'; }
    function acRiverSnap(r) {
      if (r.snap) return acSrc([r.snap]);
      return pkData && pkData.image_url ? pkData.image_url : '';
    }
    function acRiverMarkers() {
      AC_RIVER.forEach(r => {
        r.m = L.circleMarker(r.ll, { radius: 10, weight: 2, color: '#fff', fillOpacity: .95, fillColor: '#7c3aed' })
          .bindPopup(() => {
            let media;
            if (r.player) media = '<iframe src="' + esc(r.player) + '" title="' + esc(r.n) + '" allow="autoplay; fullscreen" style="width:280px;max-width:60vw;aspect-ratio:16/9;border:0;background:#000;border-radius:6px"></iframe>';
            else media = pkData && pkData.image_url ? '<img src="' + esc(pkData.image_url) + '" alt="' + esc(r.n) + '">' +
              (pkData.level != null ? '<br>ระดับน้ำ <b>' + pkData.level.toFixed(2) + ' ม. รทก.</b>' + (pkData.status ? ' · ' + esc(pkData.status) : '') : '') : 'ยังไม่มีภาพ';
            return media + '<br><b>📍 ' + esc(r.n) + '</b><br><small><a href="#' + r.card + '">ดูการ์ดด้านบน</a></small>';
          }, { maxWidth: 300 })
          .bindTooltip(() => { const src = acRiverSnap(r); return src ? acTip(src, r.n) : esc(r.n); }, { direction: 'top', offset: [0, -8], className: 'ac-tip' })
          .addTo(acMap);
      });
    }

    // Nonthaburi municipality stations (api/nonthaburi.php via loadNonthaburi): teal markers + tiles,
    // snapshots from our own cache-backed proxy, popups add the station's water levels.
    let acNbLayer = null, acNbKey = null;
    function acNbVisible(st) {
      const reg = $('acRegion').value, q = $('acSearch').value.trim().toLowerCase();
      return st.lat != null && (st.cams || []).length && (!reg || reg === 'นนทบุรี') && (!q || (st.code + ' ' + st.name).toLowerCase().indexOf(q) !== -1);
    }
    function acNbLabel(st) { return st.code + ' ' + st.name; }
    function acNbDraw() {
      if (!acMap) return;
      if (!acNbLayer) acNbLayer = L.layerGroup().addTo(acMap);
      acNbLayer.clearLayers();
      nbStations.filter(acNbVisible).forEach(st => {
        const info = nbStatusInfo(worstStatus(st));
        const on = st.cams.filter(c => !c.off);
        if (!on.length) {   // every camera sends the "no image" frame: grey dot like other dead cameras
          if (!$('acDead').checked) return;
          L.circleMarker([st.lat, st.lng], { radius: 4, weight: 1, color: '#fff', fillOpacity: .7, fillColor: '#8a8f98' })
            .bindPopup('<b>' + esc(acNbLabel(st)) + '</b><br><small>⛔ กล้องไม่ส่งภาพ (ต้นทางออฟไลน์) · เทศบาลนครนนทบุรี<br>' + esc(nbReadingsLine(st)) + '</small>', { maxWidth: 280 })
            .addTo(acNbLayer);
          return;
        }
        const m = L.circleMarker([st.lat, st.lng], { radius: 8, weight: 2, color: '#fff', fillOpacity: .95, fillColor: '#0d9488' });
        if (acHover) m.bindTooltip(() => acTip(nbThumbSrc(on[0]), acNbLabel(st)), { direction: 'top', offset: [0, -8], className: 'ac-tip' });
        m.bindPopup(() => st.cams.map((c, k) => c.off ? '' : '<img src="' + esc(nbThumbSrc(c)) + '" alt="" data-nb="' + esc(st.code) + '" data-k="' + k + '" style="cursor:zoom-in;margin-bottom:4px">').join('') +
          '<b>' + esc(acNbLabel(st)) + '</b> <span class="dot" style="background:' + info.c + '"></span>' + info.t +
          '<br><small>' + esc(nbReadingsLine(st)) + '<br>📍 <a href="https://www.google.com/maps?q=' + st.lat + ',' + st.lng + '" target="_blank" rel="noopener">' +
          esc(st.address || 'แผนที่') + '</a> · เทศบาลนครนนทบุรี</small>', { maxWidth: 300 }).addTo(acNbLayer);
      });
    }
    function acNbTiles() {
      const box = $('acNb'); if (!box) return;
      box.innerHTML = nbStations.filter(st => st.lat != null).map(st => (st.cams || []).map((c, k) => c.off ? '' :
        '<figure class="cam ac' + (c.old ? ' old' : '') + '" data-nb="' + esc(st.code) + '" data-k="' + k + '">' +
        '<img alt="' + esc(acNbLabel(st)) + '" decoding="async" data-n="' + esc(nbThumbSrc(c)) + '">' +
        '<figcaption title="' + esc(acNbLabel(st)) + '"><span class="dot" style="background:' + nbStatusInfo(worstStatus(st)).c + '"></span><code>' + esc(st.code) + '</code> ' + esc(st.name) + (st.cams.length > 1 ? ' · Cam' + (k + 1) : '') + '</figcaption>' +
        '<div class="loc">📍 ' + esc(nbReadingsLine(st) || st.address || 'นนทบุรี') + ' · เทศบาลนครนนทบุรี' + (c.old ? ' · ⚠️ ภาพอาจไม่อัปเดต' : '') + '</div></figure>').join('')).join('');
      box.querySelectorAll('img').forEach(img => { if (acObs) acObs.observe(img); else acLoad(img); });
    }
    // New station data: rebuild tiles only when the camera set changed, else just bump image versions
    function acNbUpdate() {
      const key = nbStations.map(s => s.code + ':' + (s.cams || []).map(c => c.off + '' + c.old).join('')).join();
      if (key !== acNbKey) { acNbKey = key; acNbTiles(); }
      else $('acGrid').querySelectorAll('img[data-n]').forEach(img => {
        const f = img.closest('figure'), st = nbStations.find(x => x.code === f.dataset.nb);
        const c = st && st.cams[Number(f.dataset.k)];
        if (c) { img.dataset.n = nbThumbSrc(c); if (acVis.has(img)) acLoad(img); }
      });
      acNbDraw();
      acApplyFilter();
    }
    function acNbOpen(code, k) {
      const st = nbStations.find(x => x.code === code), c = st && st.cams[k];
      if (c) openLightbox(c.url + '&v=' + (c.v || 0), '<code>' + esc(st.code) + '</code> ' + esc(st.name) + (st.cams.length > 1 ? ' · Cam' + (k + 1) : ''), nbReadingsLine(st), acNbLabel(st));
    }

    // Rangsit CDP water-level cameras (api/rangsit.php via loadRangsit): pink markers + tiles with the AI reading.
    let acRsLayer = null, acRsKey = null;
    function acRsVisible(p) {
      const reg = $('acRegion').value, q = $('acSearch').value.trim().toLowerCase();
      return p.lat != null && (!reg || reg === 'ปทุมธานี') && (!q || ('รังสิต ' + p.name).toLowerCase().indexOf(q) !== -1);
    }
    function acRsSrc(p) { return p.image + '?t=' + Math.floor(Date.now() / AC_MS); }
    function acRsDraw() {
      if (!acMap) return;
      if (!acRsLayer) acRsLayer = L.layerGroup().addTo(acMap);
      acRsLayer.clearLayers();
      rsPoints.filter(acRsVisible).forEach(p => {
        const m = L.circleMarker([p.lat, p.lng], { radius: 8, weight: 2, color: '#fff', fillOpacity: .95, fillColor: '#db2777' });
        if (acHover) m.bindTooltip(() => acTip(acRsSrc(p), p.name), { direction: 'top', offset: [0, -8], className: 'ac-tip' });
        m.bindPopup(() => '<img src="' + esc(acRsSrc(p)) + '" alt="" data-rs="' + esc(p.id) + '" style="cursor:zoom-in"><b>' + esc(p.name) + '</b>' +
          '<div class="rs-info">' + rsInfoHtml(p) + '</div><small>📍 <a href="https://www.google.com/maps?q=' + p.lat + ',' + p.lng +
          '" target="_blank" rel="noopener">แผนที่</a> · เทศบาลนครรังสิต (AI อ่านระดับน้ำ)</small>', { maxWidth: 300 }).addTo(acRsLayer);
      });
    }
    function acRsTiles() {
      const box = $('acRs'); if (!box) return;
      box.innerHTML = rsPoints.filter(p => p.lat != null).map(p => {
        const lv = rsLevelInfo(p.level);
        return '<figure class="cam ac" data-rs="' + esc(p.id) + '">' +
          '<img alt="' + esc(p.name) + '" decoding="async" data-rs="' + esc(p.image) + '">' +
          '<figcaption title="' + esc(p.name) + '"><span class="dot" style="background:' + lv.c + '"></span>' + esc(p.name) + '</figcaption>' +
          '<div class="loc">📍 ' + esc(lv.t + (p.reason ? ' · ' + p.reason : '')) + ' · เทศบาลนครรังสิต</div></figure>';
      }).join('');
      box.querySelectorAll('img').forEach(img => { if (acObs) acObs.observe(img); else acLoad(img); });
    }
    function acRsUpdate() {
      const key = rsPoints.map(p => p.id + p.level + p.reason).join('|');
      if (key !== acRsKey) { acRsKey = key; acRsTiles(); }
      acRsDraw();
      acApplyFilter();
    }

    function acOnFilter(fit) { acDrawMarkers(fit); acApplyFilter(); }
    $('acRegion').onchange = () => acOnFilter(true);
    $('acSearch').oninput = () => acOnFilter(false);
    $('acInView').onchange = () => acApplyFilter();
    $('acDead').onchange = () => acDrawMarkers(false);

    function acLoadScript(src) {
      return new Promise((ok, fail) => { const s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = fail; document.head.appendChild(s); });
    }
    async function acInitMap() {
      const css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
      document.head.appendChild(css);
      try { await acLoadScript('https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js'); }
      catch (_) { $('acMapNote').textContent = 'โหลดแผนที่ไม่สำเร็จ — แสดงเฉพาะรายการกล้อง'; $('acMap').hidden = true; return; }
      acMap = L.map('acMap', { preferCanvas: true }).setView([13.88, 100.52], 11);   // นนทบุรี–ปากเกร็ด
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(acMap);
      acLayer = L.layerGroup().addTo(acMap);
      acRiverMarkers();
      acMap.on('moveend', acApplyFilter);
      acMap.on('popupopen', e => {   // popup image -> full-size lightbox
        e.popup.getElement().querySelectorAll('img[data-rs]').forEach(im => {
          im.onclick = () => { const p = rsPoints.find(x => String(x.id) === im.dataset.rs); acMap.closePopup(); if (p) rsOpen(p); };
        });
        e.popup.getElement().querySelectorAll('img[data-nb]').forEach(im => {
          im.onclick = () => { acMap.closePopup(); acNbOpen(im.dataset.nb, Number(im.dataset.k)); };
        });
        const img = e.popup.getElement().querySelector('img[data-i]');
        if (img) img.onclick = () => { const c = acCams[Number(img.dataset.i)]; acMap.closePopup(); openLightbox(acSrc(c), esc(c[1]) + '<br><small>' + acPlace(c) + '</small>', '', c[1]); };
      });
      acOnFilter(false);
    }
    if (window.IntersectionObserver) {
      const io = new IntersectionObserver(es => { if (es[0].isIntersecting) { io.disconnect(); acInitMap(); } }, { rootMargin: '600px' });
      io.observe($('acMap'));
    } else acInitMap();

    function acBuild() {
      const regions = {};
      const live = acCams.filter(c => c[7] < 2).length;
      acCams.forEach(c => { if (c[7] < 2) regions[c[2]] = (regions[c[2]] || 0) + 1; });
      acCams.forEach(c => { if (!(c[2] in regions)) regions[c[2]] = 0; });
      acText = acCams.map(c => (c[1] + ' ' + c[2] + ' ' + c[3] + ' ' + c[4]).toLowerCase());
      const cur = $('acRegion').value;
      $('acRegion').innerHTML = '<option value="">ทุกจังหวัด (' + live + ')</option>' +
        Object.keys(regions).map(r => '<option value="' + esc(r) + '"' + (r === cur ? ' selected' : '') + '>' + esc(r || 'ไม่ระบุ') + ' (' + regions[r] + ')</option>').join('');
      $('acCount').textContent = '(' + live + ' กล้องที่มีภาพ · ' + (acCams.length - live) + ' กล้องไม่ทำงาน)';
      $('acGrid').innerHTML = AC_RIVER.map((r, k) =>
        '<figure class="cam ac river" data-r="' + k + '">' +
        '<img alt="' + esc(r.n) + '" decoding="async" data-r="' + k + '">' +
        '<figcaption title="' + esc(r.n) + '">🟣 ' + esc(r.n) + '</figcaption>' +
        '<div class="loc">📍 ริมแม่น้ำเจ้าพระยา, ' + esc(r.prov) + ' · กดเพื่อดูภาพสด</div></figure>').join('') +
        '<div id="acNb" style="display:contents"></div><div id="acRs" style="display:contents"></div>' +
        acCams.map((c, i) => c[7] === 2 ? '' :
        '<figure class="cam ac' + (c[7] ? ' old' : '') + '" data-i="' + i + '">' +
        '<img alt="' + esc(c[1]) + '" decoding="async" data-b="' + esc(acBase(c)) + '">' +
        '<figcaption title="' + esc(c[1]) + '">' + esc(c[1]) + '</figcaption>' +
        '<div class="loc">' + acPlace(c) + '</div></figure>').join('');
      if (acObs) acObs.disconnect();
      acVis.clear();
      acObs = window.IntersectionObserver ? new IntersectionObserver(es => es.forEach(e => {
        if (e.isIntersecting) { acVis.add(e.target); acLoad(e.target); } else acVis.delete(e.target);
      }), { rootMargin: '300px' }) : null;
      $('acGrid').querySelectorAll('img').forEach(img => {
        // Camera that fails to deliver an image is hidden rather than shown as a broken tile
        img.onerror = () => { img.closest('figure').classList.add('gone'); if (acObs) acObs.unobserve(img); acVis.delete(img); };
        if (acObs) acObs.observe(img); else acLoad(img);
      });
      acNbTiles();
      acRsTiles();
      acOnFilter(false);
    }
    $('acGrid').onclick = e => {
      if (e.target.closest('a')) return;
      const f = e.target.closest('figure'); if (!f) return;
      if (f.dataset.nb != null) { acNbOpen(f.dataset.nb, Number(f.dataset.k)); return; }
      if (f.dataset.rs != null) { const p = rsPoints.find(x => String(x.id) === f.dataset.rs); if (p) rsOpen(p); return; }
      if (f.dataset.r != null) {   // riverside: open the live player in its map popup
        const r = AC_RIVER[f.dataset.r];
        if (r.m) { $('acMap').scrollIntoView({ behavior: 'smooth', block: 'center' }); r.m.openPopup(); }
        else location.hash = r.card;
        return;
      }
      const c = acCams[Number(f.dataset.i)];
      openLightbox(acSrc(c), esc(c[1]) + '<br><small>' + acPlace(c) + '</small>', '', c[1]);
    };

    async function loadAllCctv() {
      try {
        const r = await fetch('api/cctv.php');
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || r.status);
        // Server found these snapshots to be one solid colour (camera offline): treat as dead
        const blank = new Set(d.blank || []);
        (d.cams || []).forEach(c => { if (blank.has(c[0]) && c[7] < 2) c[7] = 2; });
        const key = (d.cams || []).map(c => c[0] + c[7]).join();
        if (key !== acCams.map(c => c[0] + c[7]).join()) { acCams = d.cams || []; acBuild(); }
        if (d.stale) $('acMsg').textContent = 'รายชื่อกล้องเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)';
      } catch (err) {
        $('acMsg').textContent = 'โหลดรายชื่อกล้องไม่สำเร็จ: ' + err.message;
      }
    }
    loadAllCctv();
    every(600000, loadAllCctv);
    every(AC_MS, () => acVis.forEach(acLoad));

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
        data: {
          labels, datasets: [
            { label: 'ระดับน้ำ (ม.รทก.)', data: values, borderColor: css.getPropertyValue('--accent'), backgroundColor: 'rgba(10,111,181,.12)', fill: true, tension: .3, pointRadius: 0, borderWidth: 2 },
            { label: 'ตลิ่ง', data: bank, borderColor: css.getPropertyValue('--crit'), borderDash: [6, 4], pointRadius: 0, borderWidth: 1.5 },
          ]
        },
        options: {
          maintainAspectRatio: false, animation: false,
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { labels: { color: css.getPropertyValue('--muted'), boxWidth: 12, font: { size: 11 } } } },
          scales: {
            x: {
              ticks: {
                color: css.getPropertyValue('--muted'), maxRotation: 0, autoSkip: true, maxTicksLimit: 7, font: { size: 10 },
                callback(v) { const l = this.getLabelForValue(v); return l.endsWith(' 00:00') ? l.slice(0, 5) : l.slice(6); }
              }, grid: { display: false }
            },
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
        acRsUpdate();   // map markers + tiles in "CCTV ทุกแหล่ง"
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