(function () {
    const sliders = document.querySelectorAll(".rrp-slider-wrap");
    let lightbox = null;
    let lightboxImage = null;
    let activeTrigger = null;

    const ensureLightbox = () => {
        if (lightbox) {
            return lightbox;
        }

        lightbox = document.createElement("div");
        lightbox.className = "rrp-lightbox";
        lightbox.setAttribute("hidden", "hidden");
        lightbox.innerHTML = [
            '<div class="rrp-lightbox-backdrop" data-rrp-close="1"></div>',
            '<div class="rrp-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Expanded review image">',
            '<button type="button" class="rrp-lightbox-close" aria-label="Close image viewer" data-rrp-close="1">&times;</button>',
            '<img class="rrp-lightbox-image" alt="" />',
            "</div>",
        ].join("");

        lightboxImage = lightbox.querySelector(".rrp-lightbox-image");

        lightbox.addEventListener("click", (event) => {
            if (event.target instanceof HTMLElement && event.target.dataset.rrpClose === "1") {
                closeLightbox();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && lightbox && !lightbox.hasAttribute("hidden")) {
                closeLightbox();
            }
        });

        document.body.appendChild(lightbox);

        return lightbox;
    };

    const openLightbox = (trigger) => {
        const instance = ensureLightbox();
        const imageUrl = trigger.getAttribute("href");
        const sourceImage = trigger.querySelector("img");

        if (!imageUrl || !lightboxImage) {
            return;
        }

        activeTrigger = trigger;
        lightboxImage.src = imageUrl;
        lightboxImage.alt = sourceImage ? sourceImage.getAttribute("alt") || "" : "";
        instance.removeAttribute("hidden");
        document.body.classList.add("rrp-lightbox-open");
    };

    const closeLightbox = () => {
        if (!lightbox || !lightboxImage) {
            return;
        }

        lightbox.setAttribute("hidden", "hidden");
        lightboxImage.removeAttribute("src");
        lightboxImage.alt = "";
        document.body.classList.remove("rrp-lightbox-open");

        if (activeTrigger) {
            activeTrigger.focus();
            activeTrigger = null;
        }
    };

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

    document.querySelectorAll(".rrp-lightbox-trigger").forEach((trigger) => {
        trigger.addEventListener("click", (event) => {
            event.preventDefault();
            openLightbox(trigger);
        });
    });
})();
