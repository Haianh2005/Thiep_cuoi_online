document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Sao lấp lánh ---------- */
    var box = document.getElementById('sparkles');
    if (box) {
        var shapes = ['✦', '✧', '✦'];
        for (var i = 0; i < 22; i++) {
            var s = document.createElement('span');
            s.className = 'sparkle';
            s.textContent = shapes[i % shapes.length];
            s.style.left = Math.random() * 100 + '%';
            s.style.top = Math.random() * 100 + '%';
            s.style.fontSize = (8 + Math.random() * 14) + 'px';
            s.style.animationDuration = (3 + Math.random() * 4) + 's';
            s.style.animationDelay = (Math.random() * 5) + 's';
            box.appendChild(s);
        }
    }

    /* ---------- Nút Mở thiệp ---------- */
    var openBtn = document.getElementById('openBtn');
    var opening = document.getElementById('opening');
    if (openBtn && opening) {
        openBtn.addEventListener('click', function () {
            opening.classList.add('is-hidden');
            document.body.classList.remove('is-locked');
            window.scrollTo(0, 0);
            var music = document.getElementById('weddingMusic');
            if (music) music.play().catch(function () {});
        });
    }

    /* ---------- Album dạng Coverflow ---------- */
    (function () {
        var cf = document.getElementById('coverflow');
        if (!cf) return;

        var slides = Array.prototype.slice.call(cf.querySelectorAll('.cf-slide'));
        var dotsBox = document.getElementById('cfDots');
        var prevBtn = document.getElementById('cfPrev');
        var nextBtn = document.getElementById('cfNext');
        var total = slides.length;
        var current = 0;
        var dots = [];

        if (dotsBox) {
            slides.forEach(function (_, i) {
                var b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('aria-label', 'Ảnh ' + (i + 1));
                b.addEventListener('click', function () { go(i); });
                dotsBox.appendChild(b);
                dots.push(b);
            });
        }

        function render() {
            slides.forEach(function (el, i) {
                var off = i - current;
                var abs = Math.abs(off);
                var dir = off === 0 ? 0 : (off > 0 ? 1 : -1);

                var x = -50 + off * 58;                 // % chiều rộng ảnh
                var z = -abs * 120;                     // lùi dần ra sau
                var rot = -dir * 48;                    // nghiêng vào trong
                var scale = abs === 0 ? 1 : 0.92;

                el.style.transform =
                    'translateX(' + x + '%) translateZ(' + z + 'px) rotateY(' + rot + 'deg) scale(' + scale + ')';
                el.style.zIndex = String(100 - abs);
                el.style.opacity = abs === 0 ? '1' : (abs === 1 ? '.75' : (abs === 2 ? '.45' : '0'));
                el.style.filter = abs === 0 ? 'none' : 'brightness(.8)';
                el.style.pointerEvents = abs > 2 ? 'none' : 'auto';
            });
            dots.forEach(function (d, i) { d.classList.toggle('is-active', i === current); });
        }

        function go(i) {
            current = (i + total) % total;
            render();
        }

        if (prevBtn) prevBtn.addEventListener('click', function () { go(current - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { go(current + 1); });

        // Bấm vào ảnh bên cạnh để đưa nó ra giữa
        slides.forEach(function (el, i) {
            el.addEventListener('click', function () { if (i !== current && !moved) go(i); });
        });

        // Bàn phím
        cf.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') go(current - 1);
            if (e.key === 'ArrowRight') go(current + 1);
        });

        // Vuốt (điện thoại) và kéo chuột (máy tính)
        var startX = 0, startY = 0, dragging = false, moved = false;

        cf.addEventListener('pointerdown', function (e) {
            if (e.target.closest('.cf-arrow')) return;
            dragging = true; moved = false;
            startX = e.clientX; startY = e.clientY;
            cf.classList.add('is-dragging');
        });
        cf.addEventListener('pointermove', function (e) {
            if (!dragging) return;
            if (Math.abs(e.clientX - startX) > 8) moved = true;
        });
        function endDrag(e) {
            if (!dragging) return;
            dragging = false;
            cf.classList.remove('is-dragging');
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                go(dx < 0 ? current + 1 : current - 1);
            }
            setTimeout(function () { moved = false; }, 0);
        }
        cf.addEventListener('pointerup', endDrag);
        cf.addEventListener('pointercancel', endDrag);
        cf.addEventListener('pointerleave', function (e) { if (dragging) endDrag(e); });

        render();
    })();

    /* ---------- Ngày âm lịch (tự tính từ ngày dương) ---------- */
    var CAN = ['Giáp', 'Ất', 'Bính', 'Đinh', 'Mậu', 'Kỷ', 'Canh', 'Tân', 'Nhâm', 'Quý'];
    var CHI = ['Tý', 'Sửu', 'Dần', 'Mão', 'Thìn', 'Tỵ', 'Ngọ', 'Mùi', 'Thân', 'Dậu', 'Tuất', 'Hợi'];

    document.querySelectorAll('.js-lunar').forEach(function (el) {
        var iso = el.getAttribute('data-date');
        if (!iso) return;
        try {
            var d = new Date(iso + 'T12:00:00');
            var parts = new Intl.DateTimeFormat('en-u-ca-chinese', {
                day: 'numeric', month: 'numeric', year: 'numeric'
            }).formatToParts(d);

            var day = '', month = '', year = d.getFullYear();
            parts.forEach(function (p) {
                if (p.type === 'day') day = p.value;
                if (p.type === 'month') month = p.value;
                if (p.type === 'relatedYear') year = parseInt(p.value, 10);
            });
            if (!day || !month) return;

            var yearName = CAN[(year + 6) % 10] + ' ' + CHI[(year + 8) % 12];
            el.textContent = '(TỨC NGÀY ' + day + ' THÁNG ' + month.replace(/\D/g, '') +
                ' NĂM ' + yearName.toUpperCase() + ' ÂM LỊCH)';
        } catch (err) { /* trình duyệt không hỗ trợ: bỏ qua */ }
    });

    /* ---------- Debug ảnh lỗi (chỉ khi link có debug=1) ---------- */
    if (location.search.indexOf('debug=1') > -1) {
        document.querySelectorAll('.qr-img img, .couple-photo img, .story-img img, .cf-slide img').forEach(function (img) {
            function bad() {
                var t = document.createElement('small');
                t.style.cssText = 'color:#c00;word-break:break-all;display:block;padding:6px;font-size:10px';
                t.textContent = 'Không tải được: ' + img.getAttribute('src');
                img.replaceWith(t);
            }
            if (img.complete && img.naturalWidth === 0) bad();
            else img.addEventListener('error', bad);
        });
    }

    /* ---------- Popup quà mừng ---------- */
    var giftBtn = document.getElementById('giftBtn');
    var giftModal = document.getElementById('giftModal');
    var giftClose = document.getElementById('giftClose');

    function closeGift() { if (giftModal) giftModal.hidden = true; }

    if (giftBtn && giftModal) {
        giftBtn.addEventListener('click', function () { giftModal.hidden = false; });
        if (giftClose) giftClose.addEventListener('click', closeGift);
        giftModal.addEventListener('click', function (e) { if (e.target === giftModal) closeGift(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeGift(); });
    }

    /* ---------- Sổ lưu bút ---------- */
    var sendBtn = document.getElementById('sendWish');
    var nameEl = document.getElementById('wishName');
    var msgEl = document.getElementById('wishMessage');
    var listEl = document.getElementById('wishList');
    var noteEl = document.getElementById('wishNote');

    function note(text) {
        if (!noteEl) return;
        noteEl.textContent = text;
        noteEl.hidden = !text;
    }

    function addWishToList(name, message) {
        var item = document.createElement('article');
        item.className = 'wish-item';
        var head = document.createElement('header');
        var strong = document.createElement('strong');
        strong.textContent = name;
        var time = document.createElement('time');
        time.textContent = 'Vừa xong';
        head.appendChild(strong);
        head.appendChild(time);
        var p = document.createElement('p');
        p.textContent = message;
        item.appendChild(head);
        item.appendChild(p);
        listEl.insertBefore(item, listEl.firstChild);
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            var name = nameEl.value.trim();
            var message = msgEl.value.trim();
            if (!name || !message) { note('Vui lòng nhập tên và lời chúc.'); return; }

            sendBtn.disabled = true;
            note('Đang gửi...');

            var api  = sendBtn.dataset.api;
            var wid  = sendBtn.dataset.wedding;
            var slug = sendBtn.dataset.slug;
            var qs   = '?wedding_id=' + encodeURIComponent(wid) + '&slug=' + encodeURIComponent(slug);
            var payload = {
                wedding_id: parseInt(wid, 10), slug: slug,
                name: name, guest_name: name,
                message: message, wish: message, content: message
            };

            function send(asJson) {
                var opts = { method: 'POST', headers: { 'Accept': 'application/json' } };
                if (asJson) {
                    opts.headers['Content-Type'] = 'application/json';
                    opts.body = JSON.stringify(payload);
                } else {
                    var p = new URLSearchParams();
                    Object.keys(payload).forEach(function (k) { p.append(k, payload[k]); });
                    opts.body = p;
                }
                return fetch(api + qs, opts).then(function (r) {
                    return r.text().then(function (txt) {
                        var json = null;
                        try { json = JSON.parse(txt); } catch (e) {}
                        var failed = !r.ok || (json && (json.success === false || json.status === 'error' || json.error));
                        var msg = (json && (json.message || json.error)) ||
                            txt.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
                        return { failed: failed, status: r.status, msg: msg, txt: txt };
                    });
                });
            }

            // Thử gửi JSON trước; nếu API báo thiếu dữ liệu thì thử lại bằng form
            send(true)
                .then(function (res) {
                    if (res.failed && /thiếu|missing|required|không hợp lệ/i.test(res.msg)) return send(false);
                    return res;
                })
                .then(function (res) {
                    if (!res.failed) {
                        addWishToList(name, message);
                        nameEl.value = '';
                        msgEl.value = '';
                        note('Cảm ơn bạn đã gửi lời chúc!');
                        return;
                    }
                    note('Không gửi được (mã ' + res.status + '). Phản hồi: ' + (res.msg || '(trống)'));
                    if (window.console) console.log('wishes.php trả về:', res.status, res.txt);
                })
                .catch(function (err) { note('Lỗi kết nối: ' + err.message); })
                .finally(function () { sendBtn.disabled = false; });
        });
    }
});