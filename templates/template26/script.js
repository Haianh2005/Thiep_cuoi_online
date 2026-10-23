(function () {
  var $ = function (s) { return document.querySelector(s); };

  /* Hiệu ứng xuất hiện khi cuộn */
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: .12 }) : null;
  document.querySelectorAll('.reveal').forEach(function (el) { io ? io.observe(el) : el.classList.add('in'); });

  /* Đếm ngược */
  var cnt = $('.count'), target = cnt && cnt.dataset.date ? new Date(cnt.dataset.date).getTime() : 0;
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function tick() {
    if (!target) return;
    var d = Math.max(0, target - Date.now()), s = Math.floor(d / 1000);
    $('#cdD').textContent = pad(Math.floor(s / 86400));
    $('#cdH').textContent = pad(Math.floor(s % 86400 / 3600));
    $('#cdM').textContent = pad(Math.floor(s % 3600 / 60));
    $('#cdS').textContent = pad(s % 60);
  }
  tick(); setInterval(tick, 1000);

  /* Album: thumbnail, vuốt */
  var al = $('#album');
  if (al) {
    var imgs = []; try { imgs = JSON.parse(al.dataset.imgs); } catch (e) {}
    var main = $('#alMain'), thumbs = al.querySelectorAll('.gx-thumbs img'), cur = 0;
    function go(i) {
      if (!imgs.length) return;
      cur = (i + imgs.length) % imgs.length;
      main.style.opacity = 0;
      setTimeout(function () { main.src = imgs[cur]; main.style.opacity = 1; }, 150);
      thumbs.forEach(function (t, k) { t.classList.toggle('on', k === cur); });
      if (thumbs[cur]) { var th = thumbs[cur].parentNode; th.scrollTo({ left: thumbs[cur].offsetLeft - (th.clientWidth - thumbs[cur].offsetWidth) / 2, behavior: 'smooth' }); }
    }
    thumbs.forEach(function (t) { t.onclick = function () { go(+t.dataset.i); }; });
    var x0 = null, box = al.querySelector('.gx-main');
    box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', function (e) {
      if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) go(cur + (dx < 0 ? 1 : -1)); x0 = null;
    });
    var mx = null;
    box.addEventListener('mousedown', function (e) { mx = e.clientX; e.preventDefault(); });
    window.addEventListener('mouseup', function (e) {
      if (mx === null) return; var dx = e.clientX - mx;
      if (Math.abs(dx) > 40) go(cur + (dx < 0 ? 1 : -1)); mx = null;
    });
    if (imgs.length > 1) setInterval(function () { go(cur + 1); }, 5000);
  }

  /* Sổ lưu bút: hiện ngay bên dưới khi gửi */
  var btn = $('#sendWish');
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  if (btn) btn.onclick = function () {
    var name = $('#wishName').value.trim(), msg = $('#wishMessage').value.trim();
    if (!name || !msg) { alert('Vui lòng nhập tên và lời chúc.'); return; }
    btn.disabled = true;
    var fd = new FormData();
    fd.append('wedding_id', btn.dataset.wid); fd.append('name', name); fd.append('message', msg);
    fetch(btn.dataset.api, { method: 'POST', body: fd })
      .then(function (r) { return r.text(); })
      .then(function () {
        var n = new Date(), t = pad(n.getHours()) + ':' + pad(n.getMinutes()) + ':' + pad(n.getSeconds()) + ' ' +
          pad(n.getDate()) + '/' + pad(n.getMonth() + 1) + '/' + n.getFullYear();
        var el = document.createElement('div'); el.className = 'wish';
        el.innerHTML = '<div class="wish-head"><b>' + esc(name) + '</b><span class="num">' + t + '</span></div><p>' + esc(msg).replace(/\n/g, '<br>') + '</p>';
        var list = $('#wishList'); list.insertBefore(el, list.firstChild); list.scrollTop = 0;
        $('#wishName').value = ''; $('#wishMessage').value = '';
      })
      .catch(function () { alert('Gửi chưa được, vui lòng thử lại.'); })
      .finally(function () { btn.disabled = false; });
  };

  /* Hộp quà */
  var gm = $('#giftModal');
  $('#openGift').onclick = function () { gm.hidden = false; };
  $('#closeGift').onclick = function () { gm.hidden = true; };
  gm.addEventListener('click', function (e) { if (e.target === gm) gm.hidden = true; });

  /* Nhạc (Chrome chặn autoplay → tự bật khi chạm lần đầu) */
  var au = $('#weddingMusic'), mb = $('#musicBtn');
  if (au && mb) {
    function sync() { mb.classList.toggle('on', !au.paused); }
    mb.onclick = function () { au.paused ? au.play().then(sync).catch(function () {}) : (au.pause(), sync()); };
    var first = function () { au.play().then(sync).catch(function () {}); };
    au.play().then(sync).catch(function () {
      ['click', 'touchstart', 'scroll'].forEach(function (ev) { window.addEventListener(ev, first, { once: true, passive: true }); });
    });
  }
})();