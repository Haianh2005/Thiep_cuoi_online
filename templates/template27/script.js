/* TEMPLATE 27 – script */
(function () {
  'use strict';
  var C = window.T27 || {};
  var $ = function (id) { return document.getElementById(id); };
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Nhạc ---------- */
  var audio = $('weddingMusic'), mBtn = $('musicBtn');
  if (audio && mBtn) {
    var setState = function () { mBtn.classList.toggle('playing', !audio.paused); };
    var tryPlay = function () { audio.play().then(setState).catch(function () {}); };
    mBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (audio.paused) { tryPlay(); } else { audio.pause(); setState(); }
    });
    var first = function () {
      document.removeEventListener('pointerdown', first);
      if (audio.paused) tryPlay();
    };
    document.addEventListener('pointerdown', first); // Chrome chặn autoplay → phát khi chạm lần đầu
    audio.addEventListener('play', setState);
    audio.addEventListener('pause', setState);
  }

  /* ---------- Countdown ---------- */
  if (C.target && $('cdD')) {
    var m = C.target.match(/^(\d+)-(\d+)-(\d+)T(\d+):(\d+)/);
    if (m) {
      var end = new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]).getTime();
      var tick = function () {
        var d = Math.max(0, end - Date.now()), s = Math.floor(d / 1000);
        $('cdD').textContent = Math.floor(s / 86400);
        $('cdH').textContent = Math.floor(s % 86400 / 3600);
        $('cdM').textContent = Math.floor(s % 3600 / 60);
        $('cdS').textContent = s % 60;
      };
      tick(); setInterval(tick, 1000);
    }
  }

  /* ---------- Album ---------- */
  var photos = C.photos || [], cur = 0, MAX = 4;
  var thumbsEl = $('albumThumbs'), bigImg = $('albumBigImg'), bigEl = $('albumBig');
  function renderAlbum(animate) {
    if (!bigImg || !photos.length) return;
    if (animate) {
      bigImg.classList.add('fade');
      setTimeout(function () { bigImg.src = photos[cur]; bigImg.classList.remove('fade'); }, 120);
    } else { bigImg.src = photos[cur]; }
    var others = [];
    photos.forEach(function (p, i) { if (i !== cur) others.push(i); });
    var html = '';
    others.slice(0, MAX).forEach(function (idx, k) {
      var extra = (k === MAX - 1 && others.length > MAX) ? others.length - MAX : 0;
      html += '<button type="button" class="thumb" data-i="' + idx + '" aria-label="Xem ảnh ' + (idx + 1) + '">' +
              '<img src="' + esc(photos[idx]) + '" alt="" loading="lazy" draggable="false">' +
              (extra ? '<span class="more">+' + extra + '</span>' : '') + '</button>';
    });
    thumbsEl.innerHTML = html;
  }
  if (thumbsEl && bigImg) {
    thumbsEl.addEventListener('click', function (e) {
      var b = e.target.closest('.thumb');
      if (!b) return;
      cur = +b.getAttribute('data-i');
      renderAlbum(true);
    });
    // vuốt trái/phải trên ảnh lớn
    var sx = null;
    bigEl.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    bigEl.addEventListener('touchend', function (e) {
      if (sx === null || photos.length < 2) return;
      var dx = e.changedTouches[0].clientX - sx; sx = null;
      if (Math.abs(dx) < 40) return;
      cur = (cur + (dx < 0 ? 1 : -1) + photos.length) % photos.length;
      renderAlbum(true);
    }, { passive: true });
    renderAlbum(false);
  }

  /* ---------- Sổ lưu bút ---------- */
  var btn = $('sendWish'), nameEl = $('wishName'), msgEl = $('wishMessage'),
      listEl = $('wishList'), note = $('wishNote');
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function nowText() {
    var d = new Date();
    return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()) + ' ' +
           pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
  }
  if (btn) {
    btn.addEventListener('click', function () {
      var name = nameEl.value.trim(), msg = msgEl.value.trim();
      note.textContent = '';
      if (!name || !msg) { note.textContent = 'Vui lòng nhập tên và lời chúc.'; return; }
      btn.disabled = true;
      var fd = new FormData();
      fd.append('t27_wish', '1');
      fd.append('name', name);
      fd.append('message', msg);
      fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) {
          return r.text().then(function (t) {
            var j = {};
            try { j = JSON.parse(t); } catch (e) {}
            return { ok: r.ok, j: j };
          });
        })
        .then(function (res) {
          var j = res.j || {};
          if (!res.ok || j.success !== true) {
            note.textContent = j.message || j.error || 'Gửi chưa được, bạn thử lại nhé.';
            return;
          }
          var item = document.createElement('article');
          item.className = 'wish-item';
          item.innerHTML = '<header><strong>' + esc(name) + '</strong><time>' + nowText() + '</time></header>' +
                           '<p>' + esc(msg).replace(/\n/g, '<br>') + '</p>';
          listEl.insertBefore(item, listEl.firstChild);
          listEl.scrollTop = 0;
          msgEl.value = '';
          note.textContent = 'Đã gửi, cảm ơn bạn!';
        })
        .catch(function () { note.textContent = 'Không kết nối được, bạn thử lại nhé.'; })
        .then(function () { btn.disabled = false; });
    });
  }

  /* ---------- Modal quà mừng ---------- */
  var modal = $('giftModal'), openBtn = $('giftOpen'), closeBtn = $('giftClose');
  function setModal(open) {
    if (!modal) return;
    modal.classList.toggle('open', open);
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.style.overflow = open ? 'hidden' : '';
  }
  if (openBtn) openBtn.addEventListener('click', function () { setModal(true); });
  if (closeBtn) closeBtn.addEventListener('click', function () { setModal(false); });
  if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) setModal(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setModal(false); });
})();