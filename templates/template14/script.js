/* =========================================================
   TEMPLATE 09 – THỦY MẶC ĐỎ | chỉ xử lý hành vi, dữ liệu lấy từ data-attributes
========================================================= */
(function () {
    "use strict";
    var root = document.querySelector(".template09-page");
    if (!root) return;

    var weddingId = parseInt(root.dataset.weddingId, 10) || 0;
    var wishApi = root.dataset.wishApi || "/WeddingProject/api/wishes.php";
    var target = root.dataset.target || "";
    var shortGroom = root.dataset.shortGroom || "";
    var shortBride = root.dataset.shortBride || "";

    /* ---------- hiện mờ dần -> rõ khi cuộn ---------- */
    var reveals = root.querySelectorAll(".t9-reveal");
    if ("IntersectionObserver" in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add("is-visible"); io.unobserve(en.target); }
            });
        }, { threshold: 0.1, rootMargin: "0px 0px -5% 0px" });
        reveals.forEach(function (el) { io.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add("is-visible"); });
    }

    /* ---------- nhạc ---------- */
    var audio = document.getElementById("t9Audio");
    var musicBtn = document.getElementById("t9Music");
    function setPlaying(on) { if (musicBtn) musicBtn.classList.toggle("is-playing", on); }
    if (musicBtn && audio) {
        musicBtn.addEventListener("click", function () {
            if (audio.paused) { audio.play().then(function () { setPlaying(true); }).catch(function () {}); }
            else { audio.pause(); setPlaying(false); }
        });
    }

    /* ---------- mở thiệp (URL giữ nguyên /w/?slug=...) ---------- */
    var opening = document.getElementById("t9Opening");
    function openInvitation() {
        if (!opening || opening.classList.contains("is-closing")) return;
        opening.classList.add("is-closing");
        document.body.classList.remove("t9-locked");
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

    /* ---------- countdown ---------- */
    var cd = document.getElementById("t9Countdown");
    if (cd && target) {
        var targetTime = new Date(target).getTime();
        var n = {
            d: cd.querySelector('[data-cd="d"]'), h: cd.querySelector('[data-cd="h"]'),
            m: cd.querySelector('[data-cd="m"]'), s: cd.querySelector('[data-cd="s"]')
        };
        var tick = function () {
            if (isNaN(targetTime)) return false;
            var diff = targetTime - Date.now();
            if (diff <= 0) { n.d.textContent = n.h.textContent = n.m.textContent = n.s.textContent = "0"; return false; }
            var s = Math.floor(diff / 1000);
            n.d.textContent = Math.floor(s / 86400);
            n.h.textContent = Math.floor((s % 86400) / 3600);
            n.m.textContent = Math.floor((s % 3600) / 60);
            n.s.textContent = s % 60;
        };
        tick();
        var timer = setInterval(function () { if (tick() === false) clearInterval(timer); }, 1000);
    }

    /* ---------- album coverflow 3D ---------- */
    var cf = document.getElementById("t9Cf");
    if (cf) {
        var slides = Array.prototype.slice.call(cf.querySelectorAll(".t9-cf-slide"));
        var total = slides.length;
        var cur = 0;
        var range = document.getElementById("t9Range");
        var dotsBox = document.getElementById("t9Dots");
        var dots = dotsBox ? Array.prototype.slice.call(dotsBox.querySelectorAll("i")) : [];

        var posOf = function (i) {
            var d = (i - cur + total) % total;
            if (d > total / 2) d -= total;
            return d;
        };
        var render = function () {
            slides.forEach(function (s, i) {
                var p = posOf(i);
                var key = Math.abs(p) > 2 ? (p < 0 ? "-3" : "3") : String(p);
                s.setAttribute("data-pos", key);
                if (Math.abs(p) <= 2) {
                    var img = s.querySelector("img");
                    if (img && !img.getAttribute("src") && img.dataset.src) img.src = img.dataset.src;
                }
            });
            dots.forEach(function (d, i) { d.classList.toggle("is-on", i === cur); });
            if (range) range.value = cur;
        };
        var go = function (i) { cur = ((i % total) + total) % total; render(); };

        if (total > 1) {
            var prev = cf.querySelector(".t9-cf-prev");
            var next = cf.querySelector(".t9-cf-next");
            if (prev) prev.addEventListener("click", function () { go(cur - 1); });
            if (next) next.addEventListener("click", function () { go(cur + 1); });
            slides.forEach(function (s, i) {
                s.addEventListener("click", function () { if (posOf(i) !== 0) go(i); });
            });
            dots.forEach(function (d, i) { d.addEventListener("click", function () { go(i); }); });
            if (range) range.addEventListener("input", function () { go(parseInt(range.value, 10) || 0); });

            var sx = 0, sy = 0;
            cf.addEventListener("touchstart", function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
            cf.addEventListener("touchend", function (e) {
                var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
                if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) go(cur + (dx < 0 ? 1 : -1));
            }, { passive: true });
        }
        render();
    }

    /* ---------- thêm vào lịch (.ics) ---------- */
    var addCal = document.getElementById("t9AddCal");
    if (addCal && target) {
        addCal.addEventListener("click", function () {
            var start = new Date(target);
            if (isNaN(start.getTime())) return;
            var end = new Date(start.getTime() + 3 * 3600 * 1000);
            var fmt = function (d) {
                var p = function (v) { return String(v).padStart(2, "0"); };
                return d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + "T" + p(d.getHours()) + p(d.getMinutes()) + "00";
            };
            var esc = function (t) { return String(t || "").replace(/([,;\\])/g, "\\$1").replace(/\n/g, "\\n"); };
            var loc = [root.dataset.venue, root.dataset.address].filter(Boolean).join(", ");
            var ics = [
                "BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Wedding//VN", "BEGIN:VEVENT",
                "UID:wedding-" + weddingId + "-" + fmt(start) + "@wedding",
                "DTSTAMP:" + fmt(new Date()),
                "DTSTART:" + fmt(start), "DTEND:" + fmt(end),
                "SUMMARY:" + esc("Đám cưới " + shortGroom + " & " + shortBride),
                "LOCATION:" + esc(loc),
                "END:VEVENT", "END:VCALENDAR"
            ].join("\r\n");
            var blob = new Blob([ics], { type: "text/calendar;charset=utf-8" });
            var a = document.createElement("a");
            a.href = URL.createObjectURL(blob);
            a.download = "dam-cuoi.ics";
            document.body.appendChild(a);
            a.click();
            setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
        });
    }

    /* ---------- xác nhận tham dự: cuộn tới sổ lưu bút ---------- */
    var rsvp = document.getElementById("t9Rsvp");
    var gb = document.getElementById("t9Guestbook");
    if (rsvp && gb) {
        rsvp.addEventListener("click", function (e) {
            e.preventDefault();
            gb.scrollIntoView({ behavior: "smooth", block: "start" });
            setTimeout(function () { var n = document.getElementById("t9WishName"); if (n) n.focus({ preventScroll: true }); }, 700);
        });
    }

    /* ---------- modal hộp quà ---------- */
    var modal = document.getElementById("t9GiftModal");
    var openGift = document.getElementById("t9OpenGift");
    function setModal(open) {
        if (!modal) return;
        modal.classList.toggle("is-open", open);
        modal.setAttribute("aria-hidden", open ? "false" : "true");
        document.body.style.overflow = open ? "hidden" : "";
    }
    if (openGift) openGift.addEventListener("click", function () { setModal(true); });
    if (modal) modal.addEventListener("click", function (e) { if (e.target.closest("[data-close]")) setModal(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setModal(false); });

    /* ---------- sổ lưu bút (api/wishes.php) ---------- */
    var wishName = document.getElementById("t9WishName");
    var wishMsg = document.getElementById("t9WishMessage");
    var wishBtn = document.getElementById("t9SendWish");
    var wishList = document.getElementById("t9WishList");

    function formatTime(str) {
        if (!str) return "";
        var d = new Date(String(str).replace(" ", "T"));
        return isNaN(d.getTime()) ? str : d.toLocaleString("vi-VN");
    }
    function buildCard(w) {
        var card = document.createElement("div");
        card.className = "t9-wish-card";
        var head = document.createElement("div");
        head.className = "t9-wish-head";
        var nm = document.createElement("span");
        nm.className = "t9-wish-name";
        nm.textContent = w.guest_name || "";
        var tm = document.createElement("span");
        tm.className = "t9-wish-time";
        tm.textContent = formatTime(w.created_at);
        var ms = document.createElement("p");
        ms.className = "t9-wish-msg";
        ms.textContent = w.message || "";
        head.appendChild(nm);
        head.appendChild(tm);
        card.appendChild(head);
        card.appendChild(ms);
        return card;
    }
    function renderWishes(list) {
        if (!wishList) return;
        wishList.innerHTML = "";
        if (!Array.isArray(list) || !list.length) {
            var e = document.createElement("div");
            e.className = "t9-wish-empty";
            e.textContent = "Hãy là người đầu tiên gửi lời chúc!";
            wishList.appendChild(e);
            return;
        }
        list.forEach(function (w) { wishList.appendChild(buildCard(w)); });
    }
    function loadWishes() {
        if (!wishList || !weddingId) return Promise.resolve();
        return fetch(wishApi + "?wedding_id=" + weddingId)
            .then(function (r) { return r.json(); })
            .then(function (data) { if (data && data.success) renderWishes(data.wishes); })
            .catch(function (err) { console.error("Lỗi tải lời chúc:", err); });
    }
    function nowSql() {
        var d = new Date(), p = function (v) { return String(v).padStart(2, "0"); };
        return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate()) + " " + p(d.getHours()) + ":" + p(d.getMinutes()) + ":" + p(d.getSeconds());
    }
    if (wishBtn) {
        wishBtn.addEventListener("click", function () {
            var name = wishName.value.trim();
            var message = wishMsg.value.trim();
            if (!name) { alert("Vui lòng nhập tên của bạn."); wishName.focus(); return; }
            if (!message) { alert("Vui lòng nhập lời chúc."); wishMsg.focus(); return; }
            wishBtn.disabled = true;
            var oldText = wishBtn.textContent;
            wishBtn.textContent = "ĐANG GỬI...";
            fetch(wishApi, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ wedding_id: weddingId, guest_name: name, message: message })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) { alert((data && data.message) || "Không thể gửi lời chúc."); return; }
                wishName.value = "";
                wishMsg.value = "";
                var empty = wishList.querySelector(".t9-wish-empty");
                if (empty) empty.remove();
                wishList.insertBefore(buildCard({ guest_name: name, message: message, created_at: nowSql() }), wishList.firstChild);
                wishList.scrollTop = 0;
                return loadWishes();
            })
            .catch(function (err) { console.error(err); alert("Có lỗi kết nối, vui lòng thử lại."); })
            .then(function () { wishBtn.disabled = false; wishBtn.textContent = oldText; });
        });
    }
    loadWishes();
})();