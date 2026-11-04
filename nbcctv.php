<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CCTV เทศบาลนครนนทบุรี</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>">
</head>

<body>
  <header>
    <div>
      <h1>📷 CCTV เทศบาลนครนนทบุรี</h1>
      <p><a href="./">← กลับหน้าหลัก (แผนที่กล้องทุกแหล่ง)</a></p>
    </div>
  </header>

  <main>
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
        <div class="meta">ที่มา: <a href="http://182.52.224.70/?page=cctv" target="_blank"
            rel="noopener">ระบบศูนย์ป้องกันและแก้ไขปัญหาน้ำท่วม เทศบาลนครนนทบุรี</a> · ภาพอัปเดตทุก 1–2 นาที
          (ต้นทางส่งภาพช้า)</div>
      </div>
    </section>

  </main>

  <script>
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
      return stations.map(s => (s.cams || []).map(c => c.name + (c.off ? '!' : '')).join(',')).join('|');
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
        if (!cam || !img || cam.off) return;
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
            '<figure class="cam' + (cam.off ? ' err off' : '') + '" data-zone="' + esc(st.zone || '') + '" data-text="' + esc((st.code + ' ' + st.name).toLowerCase()) + '" data-code="' + esc(st.code) + '" data-cam="' + i + '">' +
            '<img alt="' + plainLabel + '" loading="lazy" decoding="async"' + (cam.off ? '' : ' data-src="' + nbThumbSrc(cam) + '"') + '>' +
            '<figcaption><span class="dot" style="background:' + dotColor + '"></span>' + capLabel + suffix + '</figcaption>' +
            (cam.off ? '<div class="meta">⛔ กล้องไม่ส่งภาพ (ต้นทางออฟไลน์)</div>' : cam.old ? '<div class="meta">⚠️ ภาพอาจไม่อัปเดต</div>' : '') +
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
        $('nbMsg').textContent = d.stale ? 'ข้อมูลสถานีเป็นข้อมูลเก่า (ต้นทางไม่ตอบสนอง)' : '';
      } catch (err) {
        $('nbMsg').textContent = 'โหลดข้อมูล CCTV ไม่สำเร็จ: ' + err.message;
      }
    }
    loadNonthaburi();
    every(60000, loadNonthaburi);
  </script>
</body>

</html>
