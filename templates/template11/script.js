/* TEMPLATE 11 – RED ROMANCE – script.js (JavaScript thuần, KHÔNG chứa PHP) */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------- Khoá cuộn khi mở modal / lightbox ---------- */
  function lock(on) { document.body.style.overflow = on ? 'hidden' : ''; }

  /* ---------- Nhạc ---------- */
  var audio = $('#weddingMusic'), musicBtn = $('#musicBtn'), userToggled = false;
  function setPlaying(p) { if (musicBtn) musicBtn.classList.toggle('playing', p); }
  function playMusic() {
    if (!audio) return;
    var pr = audio.play();
    if (pr && pr.then) pr.then(function () { setPlaying(true); }).catch(function () { setPlaying(false); });
    else setPlaying(true);
  }
  if (musicBtn) {
    musicBtn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      userToggled = true;
      if (!audio) return;
      if (audio.paused) playMusic(); else { audio.pause(); setPlaying(false); }
    });
  }
  // Chrome chặn autoplay → tự bật nhạc ở lần chạm đầu tiên của khách
  function firstTouch() {
    document.removeEventListener('pointerdown', firstTouch);
    if (audio && audio.paused && !userToggled) playMusic();
  }
  document.addEventListener('pointerdown', firstTouch);

  /* ---------- Đếm ngược ---------- */
  var cd = $('.cd');
  if (cd) {
    var target = new Date(cd.getAttribute('data-target') || '').getTime();
    var out = {};
    $$('b[data-u]', cd).forEach(function (b) { out[b.getAttribute('data-u')] = b; });
    var pad = function (n) { return n < 10 ? '0' + n : String(n); };
    var tick = function () {
      var diff = isNaN(target) ? 0 : Math.max(0, target - Date.now());
      var s = Math.floor(diff / 1000);
      var v = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
      for (var k in out) out[k].textContent = (k === 'd' ? String(v[k]) : pad(v[k])).replace(/^(\d)$/, '0$1');
    };
    tick(); setInterval(tick, 1000);
  }

  /* ---------- Hộp quà (modal QR) ---------- */
  var giftModal = $('#giftModal');
  function openGift() { if (giftModal) { giftModal.hidden = false; lock(true); } }
  function closeGift() { if (giftModal) { giftModal.hidden = true; lock(false); } }
  ['#openGift', '#fabGift'].forEach(function (sel) {
    var b = $(sel); if (b) b.addEventListener('click', openGift);
  });
  if (giftModal) {
    giftModal.addEventListener('click', function (ev) {
      if (ev.target === giftModal || ev.target.closest('[data-close]')) closeGift();
    });
  }

  /* ---------- Album + Lightbox ---------- */
  var lb = $('#lightbox'), lbImg = $('#lbImg'), photos = window.T11_PHOTOS || [], cur = 0;
  function show(i) {
    if (!photos.length || !lbImg) return;
    cur = (i + photos.length) % photos.length;
    lbImg.src = photos[cur];
    lbImg.alt = 'Ảnh ' + (cur + 1) + '/' + photos.length;
  }
  function openLb(i) { if (!lb || !photos.length) return; show(i); lb.hidden = false; lock(true); }
  function closeLb() { if (!lb) return; lb.hidden = true; lbImg.removeAttribute('src'); lock(false); }
  $$('.alb-grid .ph').forEach(function (el) {
    el.addEventListener('click', function () { openLb(parseInt(el.getAttribute('data-index'), 10) || 0); });
  });
  if (lb) {
    $('.lb-x', lb).addEventListener('click', closeLb);
    $('.lb-nav.prev', lb).addEventListener('click', function (e) { e.stopPropagation(); show(cur - 1); });
    $('.lb-nav.next', lb).addEventListener('click', function (e) { e.stopPropagation(); show(cur + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
    var sx = null;   // vuốt trái/phải trên điện thoại
    lb.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      if (sx === null) return;
      var dx = e.changedTouches[0].clientX - sx; sx = null;
      if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1));
    });
  }
  document.addEventListener('keydown', function (e) {
    if (lb && !lb.hidden) {
      if (e.key === 'Escape') closeLb();
      else if (e.key === 'ArrowLeft') show(cur - 1);
      else if (e.key === 'ArrowRight') show(cur + 1);
    } else if (giftModal && !giftModal.hidden && e.key === 'Escape') closeGift();
  });

  /* ---------- Gửi lời chúc ---------- */
  var sendBtn = $('#sendWish'), nameEl = $('#wishName'), msgEl = $('#wishMessage'),
      statusEl = $('#wishStatus'), listEl = $('#wishList');
  function status(t, err) { if (statusEl) { statusEl.textContent = t || ''; statusEl.style.color = err ? '#b3261e' : ''; } }
  function addWish(name, msg) {
    if (!listEl) return;
    var a = document.createElement('article'); a.className = 'wish';
    var b = document.createElement('b'); b.textContent = name;
    var p = document.createElement('p'); p.textContent = msg;
    var t = document.createElement('time'); t.textContent = 'Vừa xong';
    a.appendChild(b); a.appendChild(p); a.appendChild(t);
    listEl.insertBefore(a, listEl.firstChild);
    listEl.scrollTop = 0;
  }
  if (sendBtn) {
    sendBtn.addEventListener('click', function () {
      var name = (nameEl.value || '').trim(), msg = (msgEl.value || '').trim();
      if (!name || !msg) { status('Vui lòng nhập tên và lời chúc.', true); return; }
      var cfg = window.T11 || {};
      var fd = new FormData();
      fd.append('t11_action', 'wish');
      fd.append('name', name);
      fd.append('message', msg);
      sendBtn.disabled = true; status('Đang gửi…');
      fetch(cfg.api || location.href, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function (r) {
          return r.text().then(function (txt) {
            var j = null;
            try { j = JSON.parse(txt); } catch (e) { console.error('[T11] Phản hồi không phải JSON:', txt.slice(0, 500)); }
            return { ok: r.ok, json: j };
          });
        })
        .then(function (res) {
          if (res.json && res.json.success) {
            addWish(name, msg);
            nameEl.value = ''; msgEl.value = '';
            status('Cảm ơn bạn! Lời chúc đã được gửi.');
          } else {
            status((res.json && res.json.message) || 'Chưa gửi được lời chúc. Vui lòng thử lại.', true);
          }
        })
        .catch(function (err) { console.error('[T11]', err); status('Lỗi kết nối. Vui lòng thử lại.', true); })
        .then(function () { sendBtn.disabled = false; });
    });
  }

  /* ---------- Lấp lánh (canvas) ---------- */
  var cv = $('#sparkles');
  if (cv && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var ctx = cv.getContext('2d'), W = 0, H = 0, dots = [], N = 38;
    var resize = function () { W = cv.width = innerWidth; H = cv.height = innerHeight; };
    var mk = function (init) {
      return { x: Math.random() * W, y: init ? Math.random() * H : -10, r: Math.random() * 1.8 + .6,
               v: Math.random() * .5 + .25, a: Math.random() * Math.PI * 2, s: Math.random() * .04 + .015 };
    };
    resize(); addEventListener('resize', resize);
    for (var i = 0; i < N; i++) dots.push(mk(true));
    (function frame() {
      ctx.clearRect(0, 0, W, H);
      dots.forEach(function (d, i) {
        d.y += d.v; d.a += d.s;
        if (d.y > H + 10) dots[i] = mk(false);
        ctx.globalAlpha = .35 + .45 * Math.abs(Math.sin(d.a));
        ctx.fillStyle = '#f4d98c';
        ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, 6.2832); ctx.fill();
      });
      requestAnimationFrame(frame);
    })();
  }
})();