document.querySelectorAll('[data-hero-carousel]').forEach((carousel) => {
    const slides = Array.from(carousel.querySelectorAll('.hero-carousel-slide'));
    const previousButton = carousel.querySelector('.hero-carousel-previous');
    const nextButton = carousel.querySelector('.hero-carousel-next');
    const dots = Array.from(carousel.querySelectorAll('.hero-carousel-dot'));

    if (slides.length < 2 || !previousButton || !nextButton || dots.length !== slides.length) {
        return;
    }

    let activeIndex = 0;
    let intervalId;

    const showSlide = (nextIndex) => {
        activeIndex = (nextIndex + slides.length) % slides.length;
        slides.forEach((slide, index) => {
            const isActive = index === activeIndex;
            slide.classList.toggle('is-active', isActive);
            slide.setAttribute('aria-hidden', String(!isActive));
            if (isActive) {
                slide.removeAttribute('inert');
            } else {
                slide.setAttribute('inert', '');
            }
            dots[index].classList.toggle('is-active', isActive);
            dots[index].setAttribute('aria-current', String(isActive));
        });
    };

    const restartTimer = () => {
        window.clearInterval(intervalId);
        intervalId = window.setInterval(() => showSlide(activeIndex + 1), 4000);
    };

    previousButton.addEventListener('click', () => {
        showSlide(activeIndex - 1);
        restartTimer();
    });
    nextButton.addEventListener('click', () => {
        showSlide(activeIndex + 1);
        restartTimer();
    });
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            showSlide(index);
            restartTimer();
        });
    });

    restartTimer();
});
