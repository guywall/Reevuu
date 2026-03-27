(function () {
    const sliders = document.querySelectorAll(".rrp-slider-wrap");

    sliders.forEach((slider) => {
        const track = slider.querySelector(".rrp-slider-track");
        const prev = slider.querySelector(".rrp-slider-prev");
        const next = slider.querySelector(".rrp-slider-next");

        if (!track || !prev || !next) {
            return;
        }

        const step = () => Math.max(260, track.clientWidth * 0.8);

        prev.addEventListener("click", () => {
            track.scrollBy({ left: -step(), behavior: "smooth" });
        });

        next.addEventListener("click", () => {
            track.scrollBy({ left: step(), behavior: "smooth" });
        });
    });
})();
