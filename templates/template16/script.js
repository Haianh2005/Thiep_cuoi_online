/* =========================================================
   TEMPLATE 08 – WEDDING ĐỎ TRẮNG | chỉ xử lý hành vi, dữ liệu lấy từ data-attributes
========================================================= */
(function () {
    "use strict";
    var root = document.querySelector(".template08-page");
    if (!root) return;

    var target = root.dataset.target || "";
    var slides = [];
    try { slides = JSON.parse(root.dataset.slides || "[]"); } catch (e) { slides = []; }

    /* ---------- hiện mờ dần -> rõ khi cuộn ---------- */
    var reveals = root.querySelectorAll(".t8-reveal");
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
    var audio = document.getElementById("t8Audio");
    var musicBtn = document.getElementById("t8Music");
    function setPlaying(on) { if (musicBtn) musicBtn.classList.toggle("is-playing", on); }
    if (musicBtn && audio) {
        musicBtn.addEventListener("click", function () {
            if (audio.paused) { audio.play().then(function () { setPlaying(true); }).catch(function () {}); }
            else { audio.pause(); setPlaying(false); }
        });
    }

    /* ---------- mở thiệp (URL giữ nguyên /w/?slug=...) ---------- */
    var opening = document.getElementById("t8Opening");
    function openInvitation() {
        if (!opening || opening.classList.contains("is-closing")) return;
        opening.classList.add("is-closing");
        document.body.classList.remove("t8-locked");
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

    /* ---------- slideshow album: mờ dần rồi chuyển ảnh khác ---------- */
    var box = document.getElementById("t8Slides");
    if (box && slides.length > 1) {
        var imgs = box.querySelectorAll("img");
        var cur = 0, front = 0;
        var interval = 3500;
        var next = function () {
            var nextIdx = (cur + 1) % slides.length;
            var back = 1 - front;
            var loader = new Image();
            loader.onload = function () {
                imgs[back].src = slides[nextIdx];
                imgs[back].classList.add("is-on");
                imgs[front].classList.remove("is-on");
                front = back;
                cur = nextIdx;
            };
            loader.onerror = function () { cur = nextIdx; };
            loader.src = slides[nextIdx];
        };
        setInterval(function () { if (!document.hidden) next(); }, interval);
    }

    /* ---------- countdown ---------- */
    var cd = document.getElementById("t8Countdown");
    if (cd && target) {
        var targetTime = new Date(target).getTime();
        var n = {
            d: cd.querySelector('[data-cd="d"]'), h: cd.querySelector('[data-cd="h"]'),
            m: cd.querySelector('[data-cd="m"]'), s: cd.querySelector('[data-cd="s"]')
        };
        var tick = function () {
            if (isNaN(targetTime)) return false;
            var diff = targetTime - Date.now();
            if (diff <= 0) {
                n.d.textContent = n.h.textContent = n.m.textContent = n.s.textContent = "0";
                return false;
            }
            var s = Math.floor(diff / 1000);
            n.d.textContent = Math.floor(s / 86400);
            n.h.textContent = Math.floor((s % 86400) / 3600);
            n.m.textContent = Math.floor((s % 3600) / 60);
            n.s.textContent = s % 60;
        };
        tick();
        var timer = setInterval(function () { if (tick() === false) clearInterval(timer); }, 1000);
    }

    /* ---------- album so le: bấm ảnh / ô +N để xem từng ảnh ---------- */
    var album = [];
    try { album = JSON.parse(root.dataset.album || "[]"); } catch (e) { album = []; }
    var lb = document.getElementById("t8Lightbox");
    var lbImg = document.getElementById("t8LbImg");
    var lbCount = document.getElementById("t8LbCount");
    var lbStage = document.getElementById("t8LbStage");
    var mosaic = document.getElementById("t8Mosaic");
    var lbIdx = 0;

    function lbRender(i, animate) {
        if (!album.length) return;
        lbIdx = (i + album.length) % album.length;
        var src = album[lbIdx];
        var apply = function () {
            lbImg.src = src;
            lbImg.alt = "Ảnh cưới " + (lbIdx + 1);
            lbImg.onload = function () { lbImg.classList.remove("is-fading"); };
            setTimeout(function () { lbImg.classList.remove("is-fading"); }, 500);
        };
        if (animate) { lbImg.classList.add("is-fading"); setTimeout(apply, 180); } else { apply(); }
        if (lbCount) lbCount.textContent = (lbIdx + 1) + " / " + album.length;
        var pre = new Image();
        pre.src = album[(lbIdx + 1) % album.length];
    }
    function lbOpen(i) {
        if (!lb || !album.length) return;
        lb.classList.add("is-open");
        lb.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        lbRender(i, false);
    }
    function lbClose() {
        if (!lb) return;
        lb.classList.remove("is-open");
        lb.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }
    if (mosaic) {
        mosaic.addEventListener("click", function (e) {
            var t = e.target.closest(".t8-tile");
            if (t) lbOpen(parseInt(t.dataset.i, 10) || 0);
        });
    }
    if (lb) {
        lb.addEventListener("click", function (e) {
            if (e.target.closest("[data-lb-close]")) { lbClose(); return; }
            if (e.target.closest(".t8-lb-prev")) { lbRender(lbIdx - 1, true); return; }
            if (e.target.closest(".t8-lb-next")) { lbRender(lbIdx + 1, true); return; }
            if (e.target === lb || e.target === lbStage) lbClose();
        });
        var lsx = 0, lsy = 0;
        lbStage.addEventListener("touchstart", function (e) { lsx = e.touches[0].clientX; lsy = e.touches[0].clientY; }, { passive: true });
        lbStage.addEventListener("touchend", function (e) {
            var dx = e.changedTouches[0].clientX - lsx, dy = e.changedTouches[0].clientY - lsy;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) lbRender(lbIdx + (dx < 0 ? 1 : -1), true);
        }, { passive: true });
        document.addEventListener("keydown", function (e) {
            if (!lb.classList.contains("is-open")) return;
            if (e.key === "Escape") lbClose();
            if (e.key === "ArrowLeft") lbRender(lbIdx - 1, true);
            if (e.key === "ArrowRight") lbRender(lbIdx + 1, true);
        });
    }

    /* ---------- modal hộp quà ---------- */
    var modal = document.getElementById("t8GiftModal");
    var openGift = document.getElementById("t8OpenGift");
    function setModal(open) {
        if (!modal) return;
        modal.classList.toggle("is-open", open);
        modal.setAttribute("aria-hidden", open ? "false" : "true");
        document.body.style.overflow = open ? "hidden" : "";
    }
    if (openGift) openGift.addEventListener("click", function () { setModal(true); });
    if (modal) modal.addEventListener("click", function (e) { if (e.target.closest("[data-close]")) setModal(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setModal(false); });

    /* ---------- SỔ LƯU BÚT (API /wishes.php) ---------- */
    var weddingId = parseInt(root.dataset.weddingId, 10) || 0;
    var wishApi = root.dataset.wishApi || "/WeddingProject/api/wishes.php";

    var wishName = document.getElementById("t8WishName");
    var wishMsg = document.getElementById("t8WishMessage");
    var wishBtn = document.getElementById("t8SendWish");
    var wishList = document.getElementById("t8WishList");
    var wishStatus = document.getElementById("t8WishStatus");

    function fmtTime(v) {
        var d = v ? new Date(String(v).replace(" ", "T")) : new Date();
        if (isNaN(d.getTime())) return "";
        var p = function (x) { return (x < 10 ? "0" : "") + x; };
        return p(d.getHours()) + ":" + p(d.getMinutes()) + ":" + p(d.getSeconds()) + " " +
               d.getDate() + "/" + (d.getMonth() + 1) + "/" + d.getFullYear();
    }

    function buildCard(w) {
        var card = document.createElement("div");
        card.className = "t8-wish-item";

        var top = document.createElement("div");
        top.className = "t8-wish-top";

        var nm = document.createElement("div");
        nm.className = "t8-wish-name";
        nm.textContent = w.guest_name || "";

        var tm = document.createElement("div");
        tm.className = "t8-wish-time";
        tm.textContent = fmtTime(w.created_at);

        var ms = document.createElement("div");
        ms.className = "t8-wish-msg";
        ms.textContent = w.message || "";

        top.appendChild(nm);
        top.appendChild(tm);
        card.appendChild(top);
        card.appendChild(ms);
        return card;
    }

    function renderWishes(list) {
        if (!wishList) return;
        wishList.innerHTML = "";
        if (Array.isArray(list)) {
            list.forEach(function (w) {
                wishList.appendChild(buildCard(w));
            });
        }
    }

    function loadWishes() {
        if (!wishList || !weddingId) return;
        fetch(wishApi + "?wedding_id=" + weddingId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) renderWishes(data.wishes);
            })
            .catch(function (err) { console.error("Lỗi tải lời chúc:", err); });
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

                if (wishStatus) {
                    wishStatus.style.display = "block";
                    setTimeout(function () { wishStatus.style.display = "none"; }, 4000);
                }

                wishList.insertBefore(buildCard({ guest_name: name, message: message }), wishList.firstChild);
                wishList.scrollTop = 0;
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