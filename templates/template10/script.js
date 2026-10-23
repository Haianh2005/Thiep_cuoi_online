/* =========================================================
   TEMPLATE 10 – MINT | chỉ xử lý hành vi, dữ liệu lấy từ data-attributes
========================================================= */
(function () {
    "use strict";
    var root = document.querySelector(".template10-page");
    if (!root) return;

    /* ---------- hiện dần khi cuộn ---------- */
    var reveals = root.querySelectorAll(".t10-reveal");
    if ("IntersectionObserver" in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add("is-visible"); io.unobserve(en.target); }
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -5% 0px" });
        reveals.forEach(function (el) { io.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add("is-visible"); });
    }

    /* ---------- nhạc ---------- */
    var audio = document.getElementById("t10Audio");
    var musicBtn = document.getElementById("t10Music");
    function setPlaying(on) { if (musicBtn) musicBtn.classList.toggle("is-playing", on); }
    if (musicBtn && audio) {
        musicBtn.addEventListener("click", function () {
            if (audio.paused) { audio.play().then(function () { setPlaying(true); }).catch(function () {}); }
            else { audio.pause(); setPlaying(false); }
        });
    }

    /* ---------- mở thiệp (URL giữ nguyên /w/?slug=...) ---------- */
    var opening = document.getElementById("t10Opening");
    function openInvitation() {
        if (!opening || opening.classList.contains("is-closing")) return;
        opening.classList.add("is-closing");
        document.body.classList.remove("t10-locked");
        window.scrollTo(0, 0);
        if (audio) audio.play().then(function () { setPlaying(true); }).catch(function () {});
        if (musicBtn) musicBtn.classList.add("is-visible");
        setTimeout(function () { opening.style.display = "none"; }, 1100);
    }
    if (opening) {
        opening.addEventListener("click", openInvitation);
        opening.addEventListener("keydown", function (e) {
            if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openInvitation(); }
        });
    }

    /* ---------- slideshow album (2 khung, mỗi khung 1 thứ tự riêng) ---------- */
    root.querySelectorAll(".t10-slides").forEach(function (box, bi) {
        var list = [];
        try { list = JSON.parse(box.dataset.slides || "[]"); } catch (e) { list = []; }
        if (list.length < 2) return;
        var imgs = box.querySelectorAll("img");
        var cur = 0, front = 0;
        function next() {
            var nextIdx = (cur + 1) % list.length;
            var back = 1 - front;
            var loader = new Image();
            loader.onload = function () {
                imgs[back].src = list[nextIdx];
                imgs[back].classList.add("is-on");
                imgs[front].classList.remove("is-on");
                front = back;
                cur = nextIdx;
            };
            loader.onerror = function () { cur = nextIdx; };
            loader.src = list[nextIdx];
        }
        setTimeout(function () {
            setInterval(function () { if (!document.hidden) next(); }, 3500);
        }, bi * 1500);
    });

    /* ---------- đếm ngược (cả khối dạng dòng và dạng ô) ---------- */
    var cds = root.querySelectorAll(".t10-cd");
    var leftDays = root.querySelectorAll("[data-cd-days]");
    if (cds.length) {
        var tick = function () {
            var alive = false;
            cds.forEach(function (cd) {
                var t = new Date(cd.dataset.target || "").getTime();
                if (isNaN(t)) return;
                var diff = Math.max(0, t - Date.now());
                if (diff > 0) alive = true;
                var sec = Math.floor(diff / 1000);
                var v = { d: Math.floor(sec / 86400), h: Math.floor((sec % 86400) / 3600), m: Math.floor((sec % 3600) / 60), s: sec % 60 };
                Object.keys(v).forEach(function (k) {
                    var el = cd.querySelector('[data-cd="' + k + '"]');
                    if (el) el.textContent = v[k];
                });
                leftDays.forEach(function (el) { el.textContent = v.d; });
            });
            return alive;
        };
        tick();
        var cdTimer = setInterval(function () { if (!tick()) clearInterval(cdTimer); }, 1000);
    }

    /* ---------- sổ lưu bút: gửi qua api/wishes.php (thử JSON rồi form), lời chúc mới hiện ngay bên dưới ---------- */
    var wish = document.getElementById("t10Wish");
    if (wish) {
        var form = document.getElementById("t10WishForm");
        var nameEl = document.getElementById("wishName");
        var msgEl = document.getElementById("wishMessage");
        var sendBtn = document.getElementById("sendWish");
        var list = document.getElementById("wishList");
        var statusEl = document.getElementById("t10WishStatus");
        var say = function (msg, isErr) {
            statusEl.textContent = msg || "";
            statusEl.classList.toggle("is-error", !!isErr);
        };
        var pad = function (n) { return (n < 10 ? "0" : "") + n; };
        var addCard = function (name, text) {
            var empty = list.querySelector(".t10-wish-empty");
            if (empty) empty.remove();
            var d = new Date();
            var card = document.createElement("article");
            card.className = "t10-wish-item is-new";
            var top = document.createElement("div");
            top.className = "t10-wish-top";
            var b = document.createElement("b"); b.textContent = name;
            var tm = document.createElement("time");
            tm.textContent = pad(d.getHours()) + ":" + pad(d.getMinutes()) + " " + pad(d.getDate()) + "/" + pad(d.getMonth() + 1) + "/" + d.getFullYear();
            top.appendChild(b); top.appendChild(tm);
            var p = document.createElement("p");
            p.style.whiteSpace = "pre-line";
            p.textContent = text;
            card.appendChild(top); card.appendChild(p);
            list.insertBefore(card, list.firstChild);
            list.scrollTop = 0;
        };
        var wid = wish.dataset.weddingId || "", slug = wish.dataset.slug || "";
        var apiUrl = wish.dataset.api + (wish.dataset.api.indexOf("?") > -1 ? "&" : "?") +
            "wedding_id=" + encodeURIComponent(wid) + "&id=" + encodeURIComponent(wid) + "&slug=" + encodeURIComponent(slug);
        var buildPayload = function (name, text) {
            var o = {};
            ["wedding_id", "id", "wedding", "weddingId", "w_id"].forEach(function (k) { o[k] = wid; });
            ["slug", "wedding_slug"].forEach(function (k) { o[k] = slug; });
            ["name", "guest_name", "sender_name", "author", "full_name"].forEach(function (k) { o[k] = name; });
            ["message", "wish", "wish_message", "content", "comment"].forEach(function (k) { o[k] = text; });
            return o;
        };
        var post = function (mode, payload) {
            var opts = { method: "POST", credentials: "same-origin" };
            if (mode === "json") {
                opts.headers = { "Content-Type": "application/json", "Accept": "application/json" };
                opts.body = JSON.stringify(payload);
            } else {
                var fd = new FormData();
                Object.keys(payload).forEach(function (k) { fd.append(k, payload[k]); });
                opts.body = fd;
            }
            return fetch(apiUrl, opts).then(function (r) {
                return r.text().then(function (t) {
                    var d = null;
                    try { d = JSON.parse(t); } catch (err) { d = null; }
                    var failed = !r.ok || (d && (d.success === false || d.ok === false || d.status === "error" || !!d.error));
                    return { failed: failed, data: d, text: t };
                });
            });
        };
        var errText = function (res) {
            return (res.data && (res.data.message || res.data.error)) || "";
        };
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var name = nameEl.value.trim();
            var text = msgEl.value.trim();
            if (!name || !text) { say("Vui lòng nhập tên và lời chúc.", true); return; }
            var payload = buildPayload(name, text);
            sendBtn.disabled = true;
            say("Đang gửi...", false);
            post("json", payload)
                .then(function (r1) {
                    if (!r1.failed) return r1;
                    return post("form", payload).then(function (r2) {
                        if (r2.failed) { console.warn("wishes.php (json):", r1.text, "| (form):", r2.text); r2.msg = errText(r2) || errText(r1); }
                        return r2;
                    });
                })
                .then(function (res) {
                    if (res.failed) { say(res.msg ? "Lỗi: " + res.msg : "Chưa gửi được lời chúc, bạn thử lại nhé.", true); return; }
                    addCard(name, text);
                    nameEl.value = ""; msgEl.value = "";
                    say("Đã gửi lời chúc. Cảm ơn bạn!", false);
                })
                .catch(function () { say("Không kết nối được, bạn thử lại nhé.", true); })
                .then(function () { sendBtn.disabled = false; });
        });
    }

    /* ---------- modal hộp quà ---------- */
    var modal = document.getElementById("t10GiftModal");
    var openGift = document.getElementById("t10OpenGift");
    function setModal(open) {
        if (!modal) return;
        modal.classList.toggle("is-open", open);
        modal.setAttribute("aria-hidden", open ? "false" : "true");
        document.body.style.overflow = open ? "hidden" : "";
    }
    if (openGift) openGift.addEventListener("click", function () { setModal(true); });
    if (modal) modal.addEventListener("click", function (e) { if (e.target.closest("[data-close]")) setModal(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setModal(false); });
})();