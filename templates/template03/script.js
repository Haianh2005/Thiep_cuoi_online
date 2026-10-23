document.addEventListener("DOMContentLoaded", function() {
    // 1. Countdown Logic
    const countdownBox = document.querySelector('.countdown-box');
    if (countdownBox) {
        const weddingDateStr = countdownBox.getAttribute('data-date');
        const countDownDate = new Date(weddingDateStr).getTime();

        setInterval(function() {
            const now = new Date().getTime();
            const distance = countDownDate - now;

            if (distance < 0) return;

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("days").innerText = String(days).padStart(2, '0');
            document.getElementById("hours").innerText = String(hours).padStart(2, '0');
            document.getElementById("minutes").innerText = String(minutes).padStart(2, '0');
            document.getElementById("seconds").innerText = String(seconds).padStart(2, '0');
        }, 1000);
    }

    // 2. Music Player Toggle
    const audio = document.getElementById('wedding-audio');
    const musicBtn = document.getElementById('music-control');
    if (audio && musicBtn) {
        let isPlaying = false;
        musicBtn.addEventListener('click', function() {
            if (isPlaying) {
                audio.pause();
                musicBtn.classList.remove('playing');
            } else {
                audio.play().catch(e => console.log(e));
                musicBtn.classList.add('playing');
            }
            isPlaying = !isPlaying;
        });
    }

    // 3. Modal Gift Box Popup
    const giftBtn = document.getElementById('openGiftModal');
    const giftModal = document.getElementById('giftModal');
    const closeModal = document.querySelector('.close-modal');

    if (giftBtn && giftModal) {
        giftBtn.addEventListener('click', () => giftModal.classList.add('active'));
        closeModal.addEventListener('click', () => giftModal.classList.remove('active'));
        giftModal.addEventListener('click', (e) => {
            if (e.target === giftModal) giftModal.classList.remove('active');
        });
    }
});