(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };

  /* ---------- Đếm ngược ---------- */
  var root = $('.t4');
  var target = root && root.dataset.target ? new Date(root.dataset.target.replace(/-/g, '/').replace('T', ' ')) : null;
  var cd = { d: $('#cdD'), h: $('#cdH'), m: $('#cdM'), s: $('#cdS') };
  function tick() {
    if (!target || isNaN(target)) return;
    var diff = Math.max(0, target - new Date());
    var s = Math.floor(diff / 1000);
    cd.d.textContent = Math.floor(s / 86400);
    cd.h.textContent = Math.floor(s % 86400 / 3600);
    cd.m.textContent = Math.floor(s % 3600 / 60);
    cd.s.textContent = s % 60;
  }
  tick(); setInterval(tick, 1000);

  /* ---------- Số cột timeline (để kẻ đường line đủ dài) ---------- */
  var track = $('.tl-track');
  if (track) track.style.setProperty('--n', track.children.length);

  /* ---------- Lấy màu trung bình của story_image → phủ mờ timeline/footer ---------- */
  var tl = $('.tl-bg');
  if (tl && tl.dataset.toneSrc) {
    var img = new Image();
    img.onload = function () {
      try {
        var c = document.createElement('canvas'); c.width = c.height = 8;
        var x = c.getContext('2d'); x.drawImage(img, 0, 0, 8, 8);
        var d = x.getImageData(0, 0, 8, 8).data, r = 0, g = 0, b = 0, n = d.length / 4;
        for (var i = 0; i < d.length; i += 4) { r += d[i]; g += d[i + 1]; b += d[i + 2]; }
        r = r / n; g = g / n; b = b / n;
        // pha với trắng để ra tông pastel nhẹ nhàng
        var mix = function (v) { return Math.round(v * 0.7 + 255 * 0.3); };
        root.style.setProperty('--tone', 'rgb(' + mix(r) + ',' + mix(g) + ',' + mix(b) + ')');
      } catch (e) { /* giữ màu mặc định */ }
    };
    img.src = tl.dataset.toneSrc;
  }

  /* ---------- Sổ lưu bút ---------- */
  var list = $('#wishList'), btn = $('#sendWish'), note = $('#wishNote');
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function showNote(t) { note.textContent = t; note.hidden = !t; }

  if (btn) btn.addEventListener('click', function () {
    var name = $('#wishName').value.trim(), msg = $('#wishMessage').value.trim();
    if (!name || !msg) { showNote('Vui lòng nhập tên và lời chúc.'); return; }
    showNote(''); btn.disabled = true;

    var fd = new FormData();
    fd.append('action', 'wish');
    fd.append('name', name);
    fd.append('message', msg);

    fetch(window.location.href, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        var res; try { res = JSON.parse(t); } catch (e) { console.log(t); throw new Error('Máy chủ trả về dữ liệu không hợp lệ.'); }
        if (!res.ok) throw new Error(res.error || 'Không gửi được lời chúc.');
        var el = document.createElement('article');
        el.className = 'wish new';
        el.innerHTML = '<header><b>' + esc(res.name) + '</b><time>' + esc(res.time) + '</time></header><p>' + esc(res.message).replace(/\n/g, '<br>') + '</p>';
        list.insertBefore(el, list.firstChild);
        list.scrollTop = 0;
        $('#wishName').value = ''; $('#wishMessage').value = '';
      })
      .catch(function (err) { showNote(err.message); })
      .then(function () { btn.disabled = false; });
  });

  /* ---------- Modal quà ---------- */
  var modal = $('#giftModal');
  function openGift() { modal.hidden = false; }
  function closeGift() { modal.hidden = true; }
  ['#openGift', '#dockGift'].forEach(function (s) { var e = $(s); if (e) e.addEventListener('click', openGift); });
  $('#closeGift').addEventListener('click', closeGift);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeGift(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeGift(); });

  /* ---------- Album: lightbox ---------- */
  (function () {
    var lb = $('#lightbox'); if (!lb || !window.T20) return;
    var P = window.T20.photos, cur = 0, img = $('#lbImg'), cnt = $('#lbCount');
    function show(n) { cur = (n + P.length) % P.length; img.src = P[cur]; cnt.textContent = (cur + 1) + ' / ' + P.length; }
    function open(n) { show(n); lb.hidden = false; document.body.style.overflow = 'hidden'; }
    function close() { lb.hidden = true; document.body.style.overflow = ''; }
    Array.prototype.forEach.call(document.querySelectorAll('.alb-ph'), function (b) {
      b.addEventListener('click', function () { open(parseInt(b.dataset.i, 10)); });
    });
    $('#lbClose').addEventListener('click', close);
    $('#lbPrev').addEventListener('click', function () { show(cur - 1); });
    $('#lbNext').addEventListener('click', function () { show(cur + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
    document.addEventListener('keydown', function (e) {
      if (lb.hidden) return;
      if (e.key === 'Escape') close(); else if (e.key === 'ArrowLeft') show(cur - 1); else if (e.key === 'ArrowRight') show(cur + 1);
    });
    var sx = null;   // vuốt trái/phải
    lb.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; sx = null;
      if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1));
    });
  })();

  /* ---------- Nhạc ---------- */
  var audio = $('#weddingMusic'), mb = $('#musicBtn');
  if (audio && mb) {
    var set = function (on) { mb.classList.toggle('on', on); };
    var start = function () { audio.play().then(function () { set(true); }).catch(function () {}); };
    mb.addEventListener('click', function () { audio.paused ? start() : (audio.pause(), set(false)); });
    // Chrome chặn autoplay → phát ở lần chạm đầu tiên
    var first = function () { start(); ['touchstart', 'click'].forEach(function (ev) { document.removeEventListener(ev, first); }); };
    ['touchstart', 'click'].forEach(function (ev) { document.addEventListener(ev, first, { once: false, passive: true }); });
  }
})();