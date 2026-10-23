(function () {
  var $ = function (id) { return document.getElementById(id); };
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Mở thiệp + nhạc */
  var audio = $('weddingMusic'), mbtn = $('musicBtn');
  function setMusic(on) {
    if (!audio) return;
    if (on) { audio.play().then(function () { mbtn.classList.add('on'); }).catch(function () {}); }
    else { audio.pause(); mbtn.classList.remove('on'); }
  }
  document.body.style.overflow = 'hidden';
  $('openBtn').addEventListener('click', function () {
    $('cover').classList.add('gone');
    document.body.style.overflow = '';
    window.scrollTo(0, 0);
    setMusic(true);
  });
  if (mbtn) mbtn.addEventListener('click', function () { setMusic(audio.paused); });

  /* Kim tuyến vàng rơi (canvas) */
  var cv = $('sparkles');
  if (cv) {
    var cx = cv.getContext('2d'), W, H, P = [];
    var cols = ['#f9e29b', '#f2c94c', '#ffd86b', '#fff1b8', '#e0a93a'];
    var size = function () { var b = cv.getBoundingClientRect(); W = cv.width = Math.round(b.width) || Math.min(window.innerWidth, 480); H = cv.height = Math.round(b.height) || window.innerHeight; };
    size(); window.addEventListener('resize', size);
    var mk = function (top) {
      return { x: Math.random() * W, y: top ? Math.random() * H : -12, s: 2 + Math.random() * 5,
        vy: .5 + Math.random() * 1.3, sw: Math.random() * 6.28, sv: .01 + Math.random() * .03,
        r: Math.random() * 6.28, rv: (Math.random() - .5) * .12, t: Math.random() * 6.28, tv: .04 + Math.random() * .08,
        c: cols[(Math.random() * cols.length) | 0], k: Math.random() < .35 ? 0 : 1 };
    };
    for (var i = 0; i < 80; i++) P.push(mk(true));
    (function loop() {
      cx.clearRect(0, 0, W, H);
      P.forEach(function (p, n) {
        p.y += p.vy; p.sw += p.sv; p.x += Math.sin(p.sw) * .6; p.r += p.rv; p.t += p.tv;
        if (p.y > H + 12) P[n] = mk(false);
        cx.globalAlpha = .45 + .55 * Math.abs(Math.sin(p.t));
        cx.fillStyle = p.c; cx.shadowColor = p.c; cx.shadowBlur = 6;
        cx.save(); cx.translate(p.x, p.y); cx.rotate(p.r);
        if (p.k) cx.fillRect(-p.s / 2, -p.s / 3, p.s, p.s * .66);
        else { cx.beginPath(); cx.arc(0, 0, p.s / 2, 0, 6.28); cx.fill(); }
        cx.restore();
      });
      requestAnimationFrame(loop);
    })();
  }

  /* Đếm ngược */
  var cd = $('count');
  if (cd) {
    var target = new Date(cd.dataset.target).getTime();
    var tick = function () {
      var diff = Math.max(0, target - Date.now());
      $('cdD').textContent = Math.floor(diff / 864e5);
      $('cdH').textContent = Math.floor(diff % 864e5 / 36e5);
      $('cdM').textContent = Math.floor(diff % 36e5 / 6e4);
      $('cdS').textContent = Math.floor(diff % 6e4 / 1e3);
    };
    tick(); setInterval(tick, 1000);
  }

  /* Album + lightbox (vuốt trái/phải trên điện thoại) */
  var photos = (window.T24 && window.T24.photos) || [], cur = 0, lb = $('lb');
  function show(i) { cur = (i + photos.length) % photos.length; $('lbImg').src = photos[cur]; }
  if (lb) {
    document.querySelectorAll('.ph').forEach(function (b) {
      b.addEventListener('click', function () { show(+b.dataset.i); lb.hidden = false; });
    });
    $('lbX').onclick = function () { lb.hidden = true; };
    $('lbP').onclick = function () { show(cur - 1); };
    $('lbN').onclick = function () { show(cur + 1); };
    var x0 = null;
    lb.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1));
      x0 = null;
    });
    document.addEventListener('keydown', function (e) {
      if (lb.hidden) return;
      if (e.key === 'Escape') lb.hidden = true;
      if (e.key === 'ArrowLeft') show(cur - 1);
      if (e.key === 'ArrowRight') show(cur + 1);
    });
  }

  /* Sổ lưu bút: gửi POST về chính trang thiệp (PHP trong index.php xử lý) */
  var list = $('wishList'), send = $('sendWish'), st = $('wishStatus');
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML.replace(/\n/g, '<br>'); }
  function say(t, err) { st.textContent = t || ''; st.classList.toggle('err', !!err); }
  send.addEventListener('click', function () {
    var n = $('wishName').value.trim(), m = $('wishMessage').value.trim();
    if (!n || !m) { say('Vui lòng nhập tên và lời chúc nhé.', true); return; }
    send.disabled = true; say('Đang gửi lời chúc...');
    var fd = new FormData();
    fd.append('action', 'wish'); fd.append('name', n); fd.append('message', m);
    fetch(window.location.href, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        var j;
        try { j = JSON.parse(t); }
        catch (e) {
          try { j = JSON.parse(t.slice(t.indexOf('{'), t.lastIndexOf('}') + 1)); }
          catch (e2) { console.error('Phản hồi không phải JSON:', t); throw new Error('Máy chủ trả về dữ liệu không hợp lệ.'); }
        }
        if (!j.ok) throw new Error(j.error || 'Không gửi được lời chúc.');
        var el = document.createElement('article');
        el.className = 'wish';
        el.innerHTML = '<header><strong>' + esc(j.name) + '</strong><time>' + esc(j.time || '') + '</time></header><p>' + esc(j.message) + '</p>';
        list.insertBefore(el, list.firstChild);
        list.scrollTop = 0;
        $('wishName').value = ''; $('wishMessage').value = '';
        say('Gửi lời chúc thành công. Cảm ơn bạn rất nhiều!');
      })
      .catch(function (e) { say(e.message || 'Chưa gửi được, bạn thử lại nhé.', true); })
      .then(function () { send.disabled = false; });
  });

  /* Modal quà */
  var gm = $('giftModal');
  $('giftOpen').onclick = function () { gm.hidden = false; };
  $('giftClose').onclick = function () { gm.hidden = true; };
  gm.addEventListener('click', function (e) { if (e.target === gm) gm.hidden = true; });
})();