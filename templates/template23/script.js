/* =========================================================
   TEMPLATE 23 – SEN THANH TỊNH | chỉ xử lý hành vi, dữ liệu do index.php (PHP + MySQL) cấp
========================================================= */
(function () {
    "use strict";
    var root = document.querySelector(".template23-page");
    if (!root) return;

    /* ---------- hiện mờ dần khi cuộn ---------- */
    var reveals = root.querySelectorAll(".t23-reveal");
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
    var audio = document.getElementById("t23Audio");
    var musicBtn = document.getElementById("t23Music");
    function syncMusic() {
        if (!musicBtn || !audio) return;
        var on = !audio.paused;
        musicBtn.classList.toggle("is-off", !on);
        musicBtn.setAttribute("aria-pressed", on ? "true" : "false");
    }
    function playMusic() {
        if (!audio) return;
        var p = audio.play();
        if (p && p.catch) p.catch(function () {});
        syncMusic();
    }
    if (musicBtn && audio) {
        musicBtn.addEventListener("click", function () {
            if (audio.paused) playMusic(); else { audio.pause(); syncMusic(); }
        });
        audio.addEventListener("play", syncMusic);
        audio.addEventListener("pause", syncMusic);
    }

    /* ---------- mở thiệp ---------- */
    var opening = document.getElementById("t23Opening");
    var dock = document.getElementById("t23Dock");
    function showDock() { if (dock) dock.classList.add("is-on"); }
    function openInvitation() {
        if (!opening || opening.classList.contains("is-hidden")) return;
        opening.classList.add("is-hidden");
        document.body.classList.remove("t23-locked");
        window.scrollTo(0, 0);
        playMusic();
        showDock();
        setTimeout(function () { opening.style.display = "none"; }, 1100);
    }
    if (opening) {
        opening.addEventListener("click", openInvitation);
        opening.addEventListener("keydown", function (e) {
            if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openInvitation(); }
        });
    } else {
        document.body.classList.remove("t23-locked");
        showDock();
    }

    /* ---------- thanh nổi: cuộn tới sổ lưu bút ---------- */
    var dockWish = document.getElementById("t23DockWish");
    if (dockWish) {
        dockWish.addEventListener("click", function (e) {
            var target = document.getElementById("t23Guestbook");
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    }

    /* ---------- hộp quà mừng ---------- */
    var modal = document.getElementById("t23GiftModal");
    function setModal(open) {
        if (!modal) return;
        modal.classList.toggle("is-open", open);
        modal.setAttribute("aria-hidden", open ? "false" : "true");
        document.body.style.overflow = open ? "hidden" : "";
    }
    ["t23OpenGift", "t23DockGift"].forEach(function (id) {
        var b = document.getElementById(id);
        if (b) b.addEventListener("click", function () { setModal(true); });
    });
    if (modal) modal.addEventListener("click", function (e) { if (e.target.closest("[data-close]")) setModal(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setModal(false); });

    /* ---------- sổ lưu bút: gửi thẳng về trang này, lời chúc mới hiện ngay phía dưới form ---------- */
    (function () {
        var form = document.getElementById("t23WishForm");
        var nameInput = document.getElementById("t23WishName");
        var messageInput = document.getElementById("t23WishMessage");
        var sendBtn = document.getElementById("t23SendWish");
        var status = document.getElementById("t23WishStatus");
        var list = document.getElementById("t23WishList");
        if (!form || !nameInput || !messageInput || !sendBtn || !status || !list) return;

        function say(text, isError) {
            status.textContent = text || "";
            status.classList.toggle("is-error", !!isError);
        }

        function card(w) {
            var a = document.createElement("article");
            a.className = "t23-wish is-new";
            var s = document.createElement("strong"); s.textContent = w.name || "Một người bạn";
            var p = document.createElement("p"); p.textContent = w.message || "";
            a.appendChild(s); a.appendChild(p);
            if (w.time) { var t = document.createElement("time"); t.textContent = w.time; a.appendChild(t); }
            return a;
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            var name = nameInput.value.trim(), msg = messageInput.value.trim();
            if (!name || !msg) { say("Vui lòng nhập tên và lời chúc nhé.", true); return; }

            sendBtn.disabled = true;
            say("Đang gửi lời chúc...");

            var fd = new FormData();
            fd.append("action", "wish");
            fd.append("name", name);
            fd.append("message", msg);

            fetch(window.location.href, { method: "POST", body: fd, headers: { "Accept": "application/json" } })
                .then(function (r) { return r.text(); })
                .then(function (t) {
                    var j;
                    try { j = JSON.parse(t); }
                    catch (e) {
                        var a = t.indexOf("{"), b = t.lastIndexOf("}");
                        try { j = JSON.parse(t.slice(a, b + 1)); }
                        catch (e2) {
                            console.error("Template23 guestbook - phản hồi không phải JSON:", t);
                            var snippet = t.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim().slice(0, 160);
                            throw new Error("Máy chủ trả về dữ liệu không hợp lệ: " + (snippet || "(rỗng)"));
                        }
                    }
                    if (!j.ok) throw new Error(j.error || "Không gửi được lời chúc.");
                    list.insertBefore(card(j), list.firstChild);
                    list.scrollTop = 0;
                    form.reset();
                    say("Gửi lời chúc thành công. Cảm ơn bạn rất nhiều!");
                })
                .catch(function (e) { say(e.message || "Chưa gửi được. Bạn thử lại nhé.", true); })
                .then(function () { sendBtn.disabled = false; });
        });
    })();
    /* ---------- Phóng to ảnh Album (Lightbox) ---------- */
    (function () {
        var albumImgs = root.querySelectorAll(".t23-album img");
        if (!albumImgs.length) return;

        var lightboxModal = document.getElementById("t23LightboxModal");
        var lightboxImg = document.getElementById("t23LightboxImg");

        // Tự động tạo khung modal phóng to nếu chưa có trong HTML
        if (!lightboxModal) {
            lightboxModal = document.createElement("div");
            lightboxModal.className = "t23-modal t23-lightbox-modal";
            lightboxModal.id = "t23LightboxModal";
            lightboxModal.setAttribute("aria-hidden", "true");
            lightboxModal.innerHTML =
                '<div class="t23-modal-backdrop" data-close></div>' +
                '<div class="t23-lightbox-content">' +
                    '<button type="button" class="t23-lightbox-close" data-close aria-label="Đóng">&times;</button>' +
                    '<img src="" id="t23LightboxImg" alt="Ảnh phóng to">' +
                '</div>';
            root.appendChild(lightboxModal);
            lightboxImg = lightboxModal.querySelector("#t23LightboxImg");
        }

        function closeLightbox() {
            lightboxModal.classList.remove("is-open");
            lightboxModal.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
        }

        albumImgs.forEach(function (img) {
            img.addEventListener("click", function () {
                if (lightboxImg) lightboxImg.src = this.src;
                lightboxModal.classList.add("is-open");
                lightboxModal.setAttribute("aria-hidden", "false");
                document.body.style.overflow = "hidden";
            });
        });

        lightboxModal.addEventListener("click", function (e) {
            if (e.target.closest("[data-close]")) {
                closeLightbox();
            }
        });

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && lightboxModal.classList.contains("is-open")) {
                closeLightbox();
            }
        });
    })();
})();