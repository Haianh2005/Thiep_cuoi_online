/* =========================================================
   TEMPLATE 06 – TƠ DUYÊN XÁM  |  chỉ xử lý hành vi, dữ liệu lấy từ data-attributes
========================================================= */
(function () {
    "use strict";

    var root = document.querySelector(".template06-page");
    if (!root) return;

    var weddingId = parseInt(root.dataset.weddingId, 10) || 0;
    var wishApi = root.dataset.wishApi || "/WeddingProject/api/wishes.php";
    var target = root.dataset.target || "";
    var shortGroom = root.dataset.shortGroom || "";
    var shortBride = root.dataset.shortBride || "";

    /* ---------- HIỆN MỜ DẦN -> RÕ KHI CUỘN ---------- */
    var reveals = root.querySelectorAll(".t3-reveal");
    var observer = null;
    if ("IntersectionObserver" in window) {
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) {
                    en.target.classList.add("is-visible");
                    observer.unobserve(en.target);
                }
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });
        reveals.forEach(function (el) { observer.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add("is-visible"); });
    }

    /* ---------- NHẠC ---------- */
    var audio = document.getElementById("t3Audio");
    var musicBtn = document.getElementById("t3Music");
    function setPlaying(on) {
        if (!musicBtn) return;
        musicBtn.classList.toggle("is-playing", on);
    }
    if (musicBtn && audio) {
        musicBtn.addEventListener("click", function () {
            if (audio.paused) {
                audio.play().then(function () { setPlaying(true); }).catch(function () {});
            } else {
                audio.pause();
                setPlaying(false);
            }
        });
    }

    /* ---------- MỞ THIỆP (URL giữ nguyên /w/?slug=...) ---------- */
    var opening = document.getElementById("t3Opening");
    function openInvitation() {
        if (!opening || opening.classList.contains("is-closing")) return;
        opening.classList.add("is-closing");
        document.body.classList.remove("t3-locked");
        window.scrollTo(0, 0);
        if (audio) {
            audio.play().then(function () { setPlaying(true); }).catch(function () { /* trình duyệt chặn: người dùng bấm nút ♪ */ });
        }
        if (musicBtn) musicBtn.classList.add("is-visible");
        setTimeout(function () { if (opening) opening.style.display = "none"; }, 950);
    }
    if (opening) {
        opening.addEventListener("click", openInvitation);
        opening.addEventListener("keydown", function (e) {
            if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openInvitation(); }
        });
    }

    /* ---------- COUNTDOWN ---------- */
    var cd = document.getElementById("t3Countdown");
    if (cd && target) {
        var targetTime = new Date(target).getTime();
        var nodes = {
            d: cd.querySelector('[data-cd="d"]'),
            h: cd.querySelector('[data-cd="h"]'),
            m: cd.querySelector('[data-cd="m"]'),
            s: cd.querySelector('[data-cd="s"]')
        };
        var cdText = document.getElementById("t3CdText");
        var pad = function (n) { return String(n).padStart(2, "0"); };
        var tick = function () {
            var diff = targetTime - Date.now();
            if (isNaN(targetTime)) return;
            if (diff <= 0) {
                nodes.d.textContent = nodes.h.textContent = nodes.m.textContent = nodes.s.textContent = "00";
                if (cdText) cdText.textContent = "Chúc mừng hạnh phúc " + shortGroom + " và " + shortBride + "!";
                return false;
            }
            var s = Math.floor(diff / 1000);
            var days = Math.floor(s / 86400);
            nodes.d.textContent = days;
            nodes.h.textContent = pad(Math.floor((s % 86400) / 3600));
            nodes.m.textContent = pad(Math.floor((s % 3600) / 60));
            nodes.s.textContent = pad(s % 60);
            var dt = document.querySelector('[data-cd="days-text"]');
            if (dt) dt.textContent = days;
        };
        tick();
        var timer = setInterval(function () { if (tick() === false) clearInterval(timer); }, 1000);
    }

    /* ---------- GALLERY (thumbnail + vuốt) ---------- */
    var galImg = document.getElementById("t3GalImg");
    var galMain = document.getElementById("t3GalMain");
    var thumbsBox = document.getElementById("t3GalThumbs");
    var thumbs = thumbsBox ? Array.prototype.slice.call(thumbsBox.querySelectorAll(".t3-thumb")) : [];
    var current = 0;

    function showPhoto(i) {
        if (!galImg || !thumbs.length) return;
        i = (i + thumbs.length) % thumbs.length;
        if (i === current) return;
        current = i;
        galImg.classList.add("is-fading");
        var src = thumbs[i].dataset.src;
        setTimeout(function () {
            galImg.src = src;
            galImg.alt = "Ảnh cưới " + (i + 1);
            galImg.onload = function () { galImg.classList.remove("is-fading"); };
            setTimeout(function () { galImg.classList.remove("is-fading"); }, 400);
        }, 220);
        thumbs.forEach(function (t, k) { t.classList.toggle("is-active", k === i); });
        var th = thumbs[i];
        if (thumbsBox) {
            var left = th.offsetLeft - (thumbsBox.clientWidth - th.clientWidth) / 2;
            thumbsBox.scrollTo({ left: left, behavior: "smooth" });
        }
    }
    thumbs.forEach(function (t, i) {
        t.addEventListener("click", function () { showPhoto(i); });
    });
    if (galMain && thumbs.length > 1) {
        var sx = 0, sy = 0;
        galMain.addEventListener("touchstart", function (e) {
            sx = e.touches[0].clientX; sy = e.touches[0].clientY;
        }, { passive: true });
        galMain.addEventListener("touchend", function (e) {
            var dx = e.changedTouches[0].clientX - sx;
            var dy = e.changedTouches[0].clientY - sy;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) showPhoto(current + (dx < 0 ? 1 : -1));
        }, { passive: true });
    }

    /* ---------- MODAL HỘP MỪNG CƯỚI ---------- */
    var modal = document.getElementById("t3GiftModal");
    var openGift = document.getElementById("t3OpenGift");
    function setModal(open) {
        if (!modal) return;
        modal.classList.toggle("is-open", open);
        modal.setAttribute("aria-hidden", open ? "false" : "true");
        document.body.style.overflow = open ? "hidden" : "";
    }
    if (openGift) openGift.addEventListener("click", function () { setModal(true); });
    if (modal) {
        modal.addEventListener("click", function (e) {
            if (e.target.closest("[data-close]")) setModal(false);
        });
    }
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") setModal(false);
    });

    /* ---------- SỔ LƯU BÚT ---------- */
    var wishName = document.getElementById("t3WishName");
    var wishMsg = document.getElementById("t3WishMessage");
    var wishBtn = document.getElementById("t3SendWish");
    var wishList = document.getElementById("t3WishList");

    function formatTime(str) {
        if (!str) return "";
        var d = new Date(String(str).replace(" ", "T"));
        return isNaN(d.getTime()) ? str : d.toLocaleString("vi-VN");
    }
    function buildCard(w) {
        var card = document.createElement("div");
        card.className = "t3-wish-card";
        var head = document.createElement("div");
        head.className = "t3-wish-head";
        var n = document.createElement("span");
        n.className = "t3-wish-name";
        n.textContent = w.guest_name || "";
        var t = document.createElement("span");
        t.className = "t3-wish-time";
        t.textContent = formatTime(w.created_at);
        var m = document.createElement("p");
        m.className = "t3-wish-msg";
        m.textContent = w.message || "";
        head.appendChild(n);
        head.appendChild(t);
        card.appendChild(head);
        card.appendChild(m);
        return card;
    }
    function renderWishes(list) {
        if (!wishList) return;
        wishList.innerHTML = "";
        if (!Array.isArray(list) || !list.length) {
            var e = document.createElement("div");
            e.className = "t3-wish-empty";
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
        var d = new Date(), p = function (n) { return String(n).padStart(2, "0"); };
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
                if (!data || !data.success) {
                    alert((data && data.message) || "Không thể gửi lời chúc.");
                    return;
                }
                wishName.value = "";
                wishMsg.value = "";
                /* hiện ngay xuống danh sách, rồi đồng bộ lại từ MySQL */
                var empty = wishList.querySelector(".t3-wish-empty");
                if (empty) empty.remove();
                wishList.insertBefore(buildCard({ guest_name: name, message: message, created_at: nowSql() }), wishList.firstChild);
                wishList.scrollTop = 0;
                return loadWishes();
            })
            .catch(function (err) {
                console.error(err);
                alert("Có lỗi kết nối, vui lòng thử lại.");
            })
            .then(function () {
                wishBtn.disabled = false;
                wishBtn.textContent = oldText;
            });
        });
    }
    loadWishes();
})();