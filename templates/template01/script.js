document.addEventListener("DOMContentLoaded", function () {


    /* =====================================================
       OPEN INVITATION
    ===================================================== */

    const intro = document.getElementById("intro");
    const mainWedding = document.getElementById("mainWedding");
    const openButton = document.getElementById("openInvitation");

    const music = document.getElementById("weddingMusic");
    const musicButton = document.getElementById("musicButton");


    document.body.classList.add("locked");


    openButton.addEventListener("click", function () {

        /* hiệu ứng nút */

        openButton.style.transform = "scale(.92)";

        setTimeout(function () {

            intro.classList.add("hide");

            mainWedding.classList.add("show");

            document.body.classList.remove("locked");

            startMusic();

            createFireworks();

        }, 250);

    });


    /* =====================================================
       MUSIC
    ===================================================== */

    function startMusic() {

        if (!music) return;

        music.volume = 0.35;

        music.play()
            .then(function () {

                musicButton.classList.add("playing");

            })
            .catch(function () {

                console.log("Trình duyệt chặn autoplay.");

            });

    }


    musicButton.addEventListener("click", function () {

        if (music.paused) {

            music.play();

            musicButton.classList.add("playing");

        } else {

            music.pause();

            musicButton.classList.remove("playing");

        }

    });


    /* =====================================================
       ALBUM
    ===================================================== */

    const albumItems =
        Array.from(document.querySelectorAll(".album-item"));

    const dots =
        Array.from(document.querySelectorAll(".album-dots button"));

    const nextButton =
        document.querySelector(".album-next");

    const prevButton =
        document.querySelector(".album-prev");

    let currentSlide = 0;


    function updateAlbum() {

        albumItems.forEach(function (item, index) {

            item.classList.remove(
                "active",
                "prev",
                "next",
                "prev2",
                "next2"
            );


            let diff =
                index - currentSlide;


            if (diff === 0) {

                item.classList.add("active");

            } else if (diff === -1) {

                item.classList.add("prev");

            } else if (diff === 1) {

                item.classList.add("next");

            } else if (diff === -2) {

                item.classList.add("prev2");

            } else if (diff === 2) {

                item.classList.add("next2");

            }

        });


        dots.forEach(function (dot, index) {

            dot.classList.toggle(
                "active",
                index === currentSlide
            );

        });

    }


    function goToSlide(index) {

        if (index < 0) {

            index = albumItems.length - 1;

        }

        if (index >= albumItems.length) {

            index = 0;

        }

        currentSlide = index;

        updateAlbum();

    }


    if (nextButton) {

        nextButton.addEventListener(
            "click",
            function () {

                goToSlide(currentSlide + 1);

            }
        );

    }


    if (prevButton) {

        prevButton.addEventListener(
            "click",
            function () {

                goToSlide(currentSlide - 1);

            }
        );

    }


    dots.forEach(function (dot, index) {

        dot.addEventListener(
            "click",
            function () {

                goToSlide(index);

            }
        );

    });


    albumItems.forEach(function (item, index) {

        item.addEventListener(
            "click",
            function () {

                goToSlide(index);

            }
        );

    });


    /* =====================================================
       SWIPE MOBILE
    ===================================================== */

    let touchStartX = 0;
    let touchEndX = 0;


    const albumTrack =
        document.querySelector(".album-track");


    if (albumTrack) {

        albumTrack.addEventListener(
            "touchstart",
            function (event) {

                touchStartX =
                    event.changedTouches[0].screenX;

            },
            {
                passive: true
            }
        );


        albumTrack.addEventListener(
            "touchend",
            function (event) {

                touchEndX =
                    event.changedTouches[0].screenX;

                handleSwipe();

            },
            {
                passive: true
            }
        );

    }


    function handleSwipe() {

        const distance =
            touchEndX - touchStartX;


        if (Math.abs(distance) < 40) {

            return;

        }


        if (distance < 0) {

            goToSlide(currentSlide + 1);

        } else {

            goToSlide(currentSlide - 1);

        }

    }


    updateAlbum();


    /* =====================================================
       KEYBOARD
    ===================================================== */

    document.addEventListener(
        "keydown",
        function (event) {

            if (event.key === "ArrowRight") {

                goToSlide(currentSlide + 1);

            }

            if (event.key === "ArrowLeft") {

                goToSlide(currentSlide - 1);

            }

        }
    );


    /* =====================================================
       COUNTDOWN
    ===================================================== */



    /* =====================================================
       GIFT MODAL
    ===================================================== */

    const giftModal =
        document.getElementById("giftModal");

    const openGift =
        document.getElementById("openGift");

    const closeGift =
        document.getElementById("closeGift");

    const giftOverlay =
        document.querySelector(".gift-overlay");


    function showGift() {

        giftModal.classList.add("show");

        document.body.classList.add("locked");

    }


    function hideGift() {

        giftModal.classList.remove("show");

        document.body.classList.remove("locked");

    }


    openGift.addEventListener(
        "click",
        showGift
    );


    closeGift.addEventListener(
        "click",
        hideGift
    );


    giftOverlay.addEventListener(
        "click",
        hideGift
    );


    /* =====================================================
       RSVP
    ===================================================== */



/* =====================================================
   SỔ LƯU BÚT - MYSQL
===================================================== */

const wishNameInput =
    document.getElementById("wishName");

const wishMessageInput =
    document.getElementById("wishMessage");

const sendWishButton =
    document.getElementById("sendWish");

const wishList =
    document.getElementById("wishList");


/*
 * wedding_id được PHP truyền từ trang thiệp
 */
const weddingId = WEDDING_ID;


/*
 * API lời chúc
 */
const WISH_API =
    "/WeddingProject/api/wishes.php";


/* =====================================================
   ĐỌC LỜI CHÚC TỪ MYSQL
===================================================== */

async function loadWishes() {

    try {

        const response =
            await fetch(
                WISH_API +
                "?wedding_id=" +
                weddingId
            );

        const data =
            await response.json();


        if (!data.success) {

            console.error(
                "Không thể lấy lời chúc:",
                data.message
            );

            return;
        }


        renderWishes(data.wishes);


    } catch (error) {

        console.error(
            "Lỗi tải lời chúc:",
            error
        );

    }

}


/* =====================================================
   HIỂN THỊ LỜI CHÚC
===================================================== */

function renderWishes(wishes) {

    if (!wishList) return;


    wishList.innerHTML = "";


    if (
        !Array.isArray(wishes) ||
        wishes.length === 0
    ) {

        return;

    }


    wishes.forEach(function (wish) {

        const card =
            document.createElement("div");

        card.className =
            "wish-card";


        const header =
            document.createElement("div");

        header.className =
            "wish-card-header";


        const name =
            document.createElement("strong");

        name.className =
            "wish-card-name";

        name.textContent =
            wish.guest_name;


        const time =
            document.createElement("span");

        time.className =
            "wish-card-time";


        /*
         * Đổi thời gian MySQL
         * thành dạng Việt Nam
         */
        if (wish.created_at) {

            const date =
                new Date(
                    wish.created_at
                        .replace(" ", "T")
                );

            if (!isNaN(date.getTime())) {

                time.textContent =
                    date.toLocaleString(
                        "vi-VN"
                    );

            } else {

                time.textContent =
                    wish.created_at;

            }

        }


        const message =
            document.createElement("p");

        message.className =
            "wish-card-message";

        message.textContent =
            wish.message;


        header.appendChild(name);

        header.appendChild(time);

        card.appendChild(header);

        card.appendChild(message);

        wishList.appendChild(card);

    });

}


/* =====================================================
   GỬI LỜI CHÚC
===================================================== */

if (sendWishButton) {

    sendWishButton.addEventListener(
        "click",
        async function () {

            const name =
                wishNameInput.value.trim();

            const message =
                wishMessageInput.value.trim();


            /*
             * Kiểm tra tên
             */

            if (!name) {

                alert(
                    "Cậu hãy nhập tên trước nhé!"
                );

                wishNameInput.focus();

                return;

            }


            /*
             * Kiểm tra lời chúc
             */

            if (!message) {

                alert(
                    "Cậu hãy nhập lời chúc nhé!"
                );

                wishMessageInput.focus();

                return;

            }


            /*
             * Khóa nút trong lúc gửi
             */

            sendWishButton.disabled =
                true;

            sendWishButton.textContent =
                "ĐANG GỬI...";


            try {

                const response =
                    await fetch(
                        WISH_API,
                        {

                            method: "POST",

                            headers: {
                                "Content-Type":
                                    "application/json"
                            },

                            body: JSON.stringify({

                                wedding_id:
                                    weddingId,

                                guest_name:
                                    name,

                                message:
                                    message

                            })

                        }
                    );


                const data =
                    await response.json();


                /*
                 * Kiểm tra kết quả
                 */

                if (!data.success) {

                    alert(
                        data.message ||
                        "Không thể gửi lời chúc."
                    );

                    return;

                }


                /*
                 * Xóa nội dung đã nhập
                 */

                wishNameInput.value =
                    "";

                wishMessageInput.value =
                    "";


                /*
                 * Tải lại danh sách từ MySQL
                 */

                await loadWishes();


                /*
                 * Cuộn tới danh sách lời chúc
                 */

                if (wishList) {

                    wishList.scrollIntoView({
                        behavior:
                            "smooth",

                        block:
                            "start"
                    });

                }


            } catch (error) {

                console.error(
                    "Lỗi gửi lời chúc:",
                    error
                );

                alert(
                    "Không thể kết nối tới máy chủ. Cậu thử lại nhé!"
                );


            } finally {

                sendWishButton.disabled =
                    false;

                sendWishButton.textContent =
                    "GỬI LỜI CHÚC";

            }

        }
    );

}


/* =====================================================
   TẢI LỜI CHÚC KHI MỞ THIỆP
===================================================== */

loadWishes();


    /* =====================================================
       FIREWORKS
    ===================================================== */

    function createFireworks() {

        for (let i = 0; i < 18; i++) {

            const spark =
                document.createElement("span");


            spark.style.position =
                "fixed";

            spark.style.left =
                (20 + Math.random() * 60) + "%";

            spark.style.top =
                (20 + Math.random() * 45) + "%";

            spark.style.width =
                "4px";

            spark.style.height =
                "4px";

            spark.style.borderRadius =
                "50%";

            spark.style.background =
                "#f7dfac";

            spark.style.pointerEvents =
                "none";

            spark.style.zIndex =
                "5000";


            document.body.appendChild(spark);


            spark.animate(
                [
                    {
                        transform:
                            "translate(0,0) scale(1)",

                        opacity:
                            1
                    },

                    {
                        transform:
                            `translate(
                                ${(Math.random() - .5) * 250}px,
                                ${(Math.random() - .5) * 250}px
                            ) scale(0)`,

                        opacity:
                            0
                    }
                ],
                {
                    duration:
                        900 + Math.random() * 600,

                    easing:
                        "ease-out"
                }
            );


            setTimeout(
                function () {

                    spark.remove();

                },
                1700
            );

        }

    }

});