(function () {
  const W = window.WEDDING || {};
  const $ = (s) => document.querySelector(s);

  /* ---------- NHẠC ---------- */
  const audio = $('#weddingMusic'), mBtn = $('#musicBtn');
  if (audio && mBtn) {
    const set = (on) => { mBtn.classList.toggle('on', on); mBtn.setAttribute('aria-pressed', on); };
    mBtn.addEventListener('click', () => {
      if (audio.paused) audio.play().then(() => set(true)).catch(() => {}); else { audio.pause(); set(false); }
    });
    // Chrome chặn autoplay → thử phát ở lần chạm đầu tiên
    const first = () => { audio.play().then(() => set(true)).catch(() => {}); };
    ['touchend', 'click'].forEach(ev => document.addEventListener(ev, first, { once: true }));
  }

  /* ---------- ALBUM ---------- */
  const slides = [...document.querySelectorAll('#stage .slide')], thumbs = [...document.querySelectorAll('#thumbs img')];
  if (slides.length) {
    const n = slides.length; let cur = 0;
    const rel = (i) => { let d = (i - cur + n) % n; return d > n / 2 ? d - n : d; };
    function render() {
      slides.forEach((s, i) => {
        const d = rel(i); s.dataset.pos = d === 0 ? 'c' : d === -1 ? 'l' : d === 1 ? 'r' : 'h';
      });
      thumbs.forEach((t, i) => t.classList.toggle('on', i === cur));
      const t = thumbs[cur]; if (t) $('#thumbs').scrollTo({ left: t.offsetLeft - $('#thumbs').clientWidth / 2 + t.clientWidth / 2, behavior: 'smooth' });
    }
    const go = (i) => { cur = (i + n) % n; render(); };
    $('#albPrev').onclick = () => go(cur - 1); $('#albNext').onclick = () => go(cur + 1);
    thumbs.forEach((t, i) => t.onclick = () => go(i));
    slides.forEach((s, i) => s.onclick = () => { const d = rel(i); if (d) go(cur + d); });
    // vuốt / kéo
    const st = $('#stage'); let x0 = null;
    const down = (x) => x0 = x, up = (x) => { if (x0 === null) return; const dx = x - x0; if (Math.abs(dx) > 40) go(cur + (dx < 0 ? 1 : -1)); x0 = null; };
    st.addEventListener('touchstart', e => down(e.touches[0].clientX), { passive: true });
    st.addEventListener('touchend', e => up(e.changedTouches[0].clientX));
    st.addEventListener('mousedown', e => down(e.clientX)); window.addEventListener('mouseup', e => up(e.clientX));
    render();
  }

  /* ---------- MODAL QUÀ ---------- */
  const modal = $('#giftModal');
  if (modal) {
    const open = () => { modal.hidden = false; document.body.classList.add('lock'); };
    const close = () => { modal.hidden = true; document.body.classList.remove('lock'); };
    $('#openGift').onclick = open; $('#closeGift').onclick = close;
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  }

  /* ---------- SỔ LƯU BÚT (gửi thẳng về trang hiện tại, template tự lưu vào DB) ---------- */
  const list = $('#wishList'), status = $('#wishStatus'), btn = $('#sendWish');
  if (list && btn) {
    btn.addEventListener('click', async () => {
      const name = $('#wishName').value.trim(), msg = $('#wishMessage').value.trim(), att = $('#wishAttend').value;
      status.className = 'status';
      if (!name || !msg) { status.textContent = 'Vui lòng nhập tên và lời chúc.'; status.classList.add('err'); return; }
      btn.disabled = true; status.textContent = 'Đang gửi…';
      const fd = new FormData();
      fd.append('action', 'wish');
      fd.append('name', name);
      fd.append('message', att ? '[' + att + '] ' + msg : msg);
      try {
        const r = await fetch(location.href, { method: 'POST', body: fd });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'Gửi thất bại');
        const empty = list.querySelector('.empty'); if (empty) empty.remove();
        const el = document.createElement('article'); el.className = 'wish-item';
        const b = document.createElement('b'); b.textContent = j.name;
        const p = document.createElement('p'); p.textContent = j.message;
        const t = document.createElement('small'); t.textContent = j.time;
        el.append(b, p, t); list.prepend(el);
        $('#wishMessage').value = ''; $('#wishAttend').value = '';
        status.textContent = 'Cảm ơn bạn! Lời chúc đã được gửi.'; status.classList.add('ok');
      } catch (e) { status.textContent = e.message || 'Không gửi được, vui lòng thử lại.'; status.classList.add('err'); }
      btn.disabled = false;
    });
  }
})();