(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------- Nhạc ---------- */
  var audio = $('#weddingMusic');
  var musicBtn = $('#musicBtn');

  function syncMusicBtn() {
    if (!musicBtn || !audio) return;
    var playing = !audio.paused;
    musicBtn.classList.toggle('is-off', !playing);
    musicBtn.classList.toggle('is-playing', playing);
    musicBtn.setAttribute('aria-pressed', playing ? 'true' : 'false');
  }
  function playMusic() {
    if (!audio) return;
    var p = audio.play();
    if (p && p.catch) p.catch(function () {});
    syncMusicBtn();
  }
  if (musicBtn && audio) {
    musicBtn.classList.add('is-off');
    musicBtn.addEventListener('click', function () {
      if (audio.paused) playMusic(); else { audio.pause(); syncMusicBtn(); }
    });
    audio.addEventListener('play', syncMusicBtn);
    audio.addEventListener('pause', syncMusicBtn);
  }

  /* ---------- Mở thiệp ---------- */
  var opening = $('#opening');
  function openCard() {
    if (!opening || opening.classList.contains('is-open')) return;
    opening.classList.add('is-open');
    document.body.classList.remove('is-locked');
    window.scrollTo(0, 0);
    playMusic();               // bấm mở = có tương tác nên Chrome cho phép phát nhạc
    buildTimeline();
    setTimeout(function () { opening.style.display = 'none'; }, 1000);
  }
  if (opening) {
    opening.addEventListener('click', openCard);
    opening.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCard(); }
    });
  } else {
    document.body.classList.remove('is-locked');
  }

  /* ---------- Slideshow đổi ảnh mỗi giây ---------- */
  $$('[data-ss]').forEach(function (box) {
    var imgs = $$('.ss-img', box);
    if (imgs.length < 2) return;
    var i = 0;
    setInterval(function () {
      imgs[i].classList.remove('is-on');
      i = (i + 1) % imgs.length;
      imgs[i].classList.add('is-on');
    }, 1000);
  });

  /* ---------- Timeline: vẽ đường cong đi qua các dấu chấm ---------- */
  function buildTimeline() {
    var tl = $('#tl');
    var svg = $('#tlPath');
    if (!tl || !svg) return;
    var dots = $$('.tl-dot', tl);
    if (!dots.length) return;
    var box = tl.getBoundingClientRect();
    var pts = dots.map(function (d) {
      var r = d.getBoundingClientRect();
      return { x: r.left - box.left + r.width / 2, y: r.top - box.top + r.height / 2 };
    });
    var w = box.width, h = box.height;
    svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
    var d = 'M' + pts[0].x + ' 0 L' + pts[0].x + ' ' + (pts[0].y - 20) + ' L' + pts[0].x + ' ' + pts[0].y;
    for (var i = 1; i < pts.length; i++) {
      var a = pts[i - 1], b = pts[i], m = (b.y - a.y) / 2;
      d += ' C' + a.x + ' ' + (a.y + m) + ',' + b.x + ' ' + (b.y - m) + ',' + b.x + ' ' + b.y;
    }
    var last = pts[pts.length - 1];
    d += ' L' + last.x + ' ' + h;
    svg.firstElementChild.setAttribute('d', d);
  }
  window.addEventListener('resize', buildTimeline);
  window.addEventListener('load', buildTimeline);

  /* ---------- Album có thumbnail ---------- */
  (function () {
    var track = $('#albumTrack');
    if (!track) return;
    var slides = $$('.album-slide', track);
    var thumbsBox = $('#albumThumbs');
    var thumbs = $$('button', thumbsBox);
    var cur = 0;

    function go(n) {
      cur = (n + slides.length) % slides.length;
      track.style.transform = 'translateX(' + (-slides[cur].offsetLeft) + 'px)';
      slides.forEach(function (s, i) { s.classList.toggle('is-on', i === cur); });
      thumbs.forEach(function (t, i) { t.classList.toggle('is-on', i === cur); });
      var t = thumbs[cur];
      if (t && thumbsBox) thumbsBox.scrollTo({ left: t.offsetLeft - thumbsBox.clientWidth / 2 + t.clientWidth / 2, behavior: 'smooth' });
    }
    $('#albumPrev').addEventListener('click', function () { go(cur - 1); });
    $('#albumNext').addEventListener('click', function () { go(cur + 1); });
    thumbs.forEach(function (t) {
      t.addEventListener('click', function () { go(parseInt(t.getAttribute('data-i'), 10)); });
    });

    // vuốt trái/phải (mobile) + kéo chuột (desktop)
    var sx = null;
    function start(x) { sx = x; }
    function end(x) {
      if (sx === null) return;
      var dx = x - sx; sx = null;
      if (Math.abs(dx) > 40) go(cur + (dx < 0 ? 1 : -1));
    }
    track.addEventListener('touchstart', function (e) { start(e.touches[0].clientX); }, { passive: true });
    track.addEventListener('touchend', function (e) { end(e.changedTouches[0].clientX); });
    track.addEventListener('mousedown', function (e) { e.preventDefault(); start(e.clientX); });
    window.addEventListener('mouseup', function (e) { end(e.clientX); });
    window.addEventListener('resize', function () { go(cur); });
  })();

  /* ---------- Sổ lưu bút ---------- */
  (function () {
    var btn = $('#sendWish');
    if (!btn) return;
    var nameEl = $('#wishName'), msgEl = $('#wishMessage'), err = $('#wishError'), list = $('#wishList');

    function showErr(t) { err.textContent = t; err.hidden = !t; }

    btn.addEventListener('click', function () {
      var name = nameEl.value.trim(), msg = msgEl.value.trim();
      if (!name || !msg) { showErr('Vui lòng nhập tên và lời chúc.'); return; }
      showErr('');
      btn.disabled = true;

      var fd = new FormData();
      fd.append('action', 'wish');
      fd.append('name', name);
      fd.append('message', msg);

      fetch(window.location.href, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.text(); })
        .then(function (t) {
          var j;
          try { j = JSON.parse(t); } catch (e) { throw new Error('Máy chủ trả về dữ liệu không hợp lệ.'); }
          if (!j.ok) throw new Error(j.error || 'Không gửi được lời chúc.');
          var a = document.createElement('article');
          a.className = 'wish is-new';
          var s = document.createElement('strong'); s.textContent = j.name;
          var p = document.createElement('p'); p.style.whiteSpace = 'pre-line'; p.textContent = j.message;
          var tm = document.createElement('time'); tm.textContent = j.time;
          a.appendChild(s); a.appendChild(p); a.appendChild(tm);
          list.insertBefore(a, list.firstChild);
          nameEl.value = ''; msgEl.value = '';
        })
        .catch(function (e) { showErr(e.message); })
        .then(function () { btn.disabled = false; });
    });
  })();

  /* ---------- Hộp quà ---------- */
  (function () {
    var modal = $('#giftModal'), open = $('#giftOpen'), close = $('#giftClose');
    if (!modal || !open) return;
    function show() { modal.hidden = false; close.focus(); }
    function hide() { modal.hidden = true; open.focus(); }
    open.addEventListener('click', show);
    close.addEventListener('click', hide);
    modal.addEventListener('click', function (e) { if (e.target === modal) hide(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) hide(); });
  })();

  /* ---------- Trái tim rơi nhẹ ---------- */
  (function () {
    var box = $('#hearts');
    if (!box || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    for (var i = 0; i < 9; i++) {
      var h = document.createElement('span');
      h.className = 'h';
      var size = 12 + Math.random() * 14;
      h.style.cssText = 'left:' + (Math.random() * 100) + '%;width:' + size + 'px;height:' + size + 'px;' +
        'animation-duration:' + (16 + Math.random() * 16) + 's;animation-delay:-' + (Math.random() * 24) + 's;' +
        '--dx:' + ((Math.random() * 80) - 40) + 'px';
      h.innerHTML = window.TP.heart;
      box.appendChild(h);
    }
  })();
})();