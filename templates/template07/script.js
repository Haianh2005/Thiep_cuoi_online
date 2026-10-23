/* =========================================================
   TEMPLATE 07 – TƠ DUYÊN - ĐỎ | chỉ xử lý hành vi, dữ liệu lấy từ data-attributes
========================================================= */
(function () {
    "use strict";
    var root = document.querySelector(".template07-page");
    if (!root) return;

    var target = root.dataset.target || "";
    var shortGroom = root.dataset.shortGroom || "";
    var shortBride = root.dataset.shortBride || "";

    /* ---------- hiện mờ dần -> rõ khi cuộn ---------- */
    var reveals = root.querySelectorAll(".t7-reveal");
    if ("IntersectionObserver" in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add("is-visible"); io.unobserve(en.target); }
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });
        reveals.forEach(function (el) { io.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add("is-visible"); });
    }

    /* ---------- nhạc ---------- */
    var audio = document.getElementById("t7Audio");
    var musicBtn = document.getElementById("t7Music");
    function setPlaying(on) { if (musicBtn) musicBtn.classList.toggle("is-playing", on); }
    if (musicBtn && audio) {
        musicBtn.addEventListener("click", function () {
            if (audio.paused) { audio.play().then(function () { setPlaying(true); }).catch(function () {}); }
            else { audio.pause(); setPlaying(false); }
        });
    }

    /* ---------- mở thiệp (URL giữ nguyên /w/?slug=...) ---------- */
    var opening = document.getElementById("t7Opening");
    var openBtn = document.getElementById("t7OpenBtn");
    function openInvitation() {
        if (!opening || opening.classList.contains("is-hidden")) return;
        opening.classList.add("is-hidden");
        document.body.classList.remove("t7-locked");
        window.scrollTo(0, 0);
        if (audio) audio.play().then(function () { setPlaying(true); }).catch(function () {});
        if (musicBtn) musicBtn.classList.add("is-visible");
        setTimeout(function () { opening.style.display = "none"; }, 1200);
    }
    if (openBtn) openBtn.addEventListener("click", openInvitation);

    /* ---------- countdown ---------- */
    var cd = document.getElementById("t7Countdown");
    if (cd && target) {
        var targetTime = new Date(target).getTime();
        var n = {
            d: cd.querySelector('[data-cd="d"]'), h: cd.querySelector('[data-cd="h"]'),
            m: cd.querySelector('[data-cd="m"]'), s: cd.querySelector('[data-cd="s"]')
        };
        var cdText = document.getElementById("t7CdText");
        var pad = function (v) { return String(v).padStart(2, "0"); };
        var tick = function () {
            if (isNaN(targetTime)) return false;
            var diff = targetTime - Date.now();
            if (diff <= 0) {
                n.d.textContent = n.h.textContent = n.m.textContent = n.s.textContent = "00";
                if (cdText) cdText.textContent = "Chúc mừng hạnh phúc " + shortGroom + " và " + shortBride + "!";
                return false;
            }
            var s = Math.floor(diff / 1000), days = Math.floor(s / 86400);
            n.d.textContent = days;
            n.h.textContent = pad(Math.floor((s % 86400) / 3600));
            n.m.textContent = pad(Math.floor((s % 3600) / 60));
            n.s.textContent = pad(s % 60);
            var dt = root.querySelector('[data-cd="days-text"]');
            if (dt) dt.textContent = days;
        };
        tick();
        var timer = setInterval(function () { if (tick() === false) clearInterval(timer); }, 1000);
    }

    /* ---------- gallery (thumbnail + vuốt) ---------- */
    var galImg = document.getElementById("t7GalImg");
    var galMain = document.getElementById("t7GalMain");
    var thumbsBox = document.getElementById("t7GalThumbs");
    var thumbs = thumbsBox ? Array.prototype.slice.call(thumbsBox.querySelectorAll(".t7-thumb")) : [];
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
        thumbsBox.scrollTo({ left: th.offsetLeft - (thumbsBox.clientWidth - th.clientWidth) / 2, behavior: "smooth" });
    }
    thumbs.forEach(function (t, i) { t.addEventListener("click", function () { showPhoto(i); }); });
    if (galMain && thumbs.length > 1) {
        var sx = 0, sy = 0;
        galMain.addEventListener("touchstart", function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
        galMain.addEventListener("touchend", function (e) {
            var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) showPhoto(current + (dx < 0 ? 1 : -1));
        }, { passive: true });
    }

    /* ---------- modal hộp quà ---------- */
    var modal = document.getElementById("t7GiftModal");
    var openGift = document.getElementById("t7OpenGift");
    function setModal(open) {
        if (!modal) return;
        modal.classList.toggle("is-open", open);
        modal.setAttribute("aria-hidden", open ? "false" : "true");
        document.body.style.overflow = open ? "hidden" : "";
    }
    if (openGift) openGift.addEventListener("click", function () { setModal(true); });
    if (modal) modal.addEventListener("click", function (e) { if (e.target.closest("[data-close]")) setModal(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setModal(false); });
    
    /* ========== SỔ LƯU BÚT - TEMPLATE07 (gửi thẳng về trang này, không qua api/wishes.php) ========== */
    (function () {
        "use strict";

        var form = document.getElementById("t7WishForm");
        var nameInput = document.getElementById("t7WishName");
        var messageInput = document.getElementById("t7WishMessage");
        var sendButton = document.getElementById("t7SendWish");
        var statusBox = document.getElementById("t7WishStatus");
        var wishList = document.getElementById("t7WishList");

        if (!form || !nameInput || !messageInput || !sendButton || !statusBox || !wishList) return;

        function createCard(wish) {
            var card = document.createElement("article");
            card.className = "t7-wish-card is-new";
            var name = document.createElement("strong");
            name.textContent = wish.name || "Một người bạn";
            var message = document.createElement("p");
            message.textContent = wish.message || "";
            card.appendChild(name);
            card.appendChild(message);
            if (wish.time) {
                var time = document.createElement("time");
                time.textContent = wish.time;
                card.appendChild(time);
            }
            return card;
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            var guestName = nameInput.value.trim();
            var message = messageInput.value.trim();
            if (!guestName || !message) { statusBox.textContent = "Vui lòng nhập tên và lời chúc nhé."; return; }

            sendButton.disabled = true;
            statusBox.textContent = "Đang gửi lời chúc...";

            var fd = new FormData();
            fd.append("action", "wish");
            fd.append("name", guestName);
            fd.append("message", message);

            fetch(window.location.href, { method: "POST", body: fd, headers: { "Accept": "application/json" } })
                .then(function (r) { return r.text(); })
                .then(function (t) {
                    var j;
                    try { j = JSON.parse(t); }
                    catch (e) {
                        var a = t.indexOf("{"), b = t.lastIndexOf("}");
                        try { j = JSON.parse(t.slice(a, b + 1)); }
                        catch (e2) {
                            console.error("Template07 guestbook - phản hồi không phải JSON:", t);
                            var snippet = t.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim().slice(0, 160);
                            throw new Error("Máy chủ trả về dữ liệu không hợp lệ: " + (snippet || "(rỗng)"));
                        }
                    }
                    if (!j.ok) throw new Error(j.error || "Không gửi được lời chúc.");

                    var placeholder = wishList.querySelector(".t7-wish-empty");
                    if (placeholder) placeholder.remove();
                    wishList.insertBefore(createCard(j), wishList.firstChild);
                    wishList.scrollTop = 0;
                    form.reset();
                    statusBox.textContent = "Gửi lời chúc thành công. Cảm ơn bạn rất nhiều!";
                })
                .catch(function (e) { statusBox.textContent = e.message || "Chưa gửi được. Bạn thử lại nhé."; })
                .then(function () { sendButton.disabled = false; });
        });
    })();

})();