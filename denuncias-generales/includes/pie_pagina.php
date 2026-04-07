    <!-- Bootstrap JS -->
    <script src="<?= CDN_BS_JS ?>"></script>

    <!-- GSAP ScrollTrigger -->
    <script src="<?= CDN_GSAP_ST ?>"></script>
    <script>
        gsap.registerPlugin(ScrollTrigger);

        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.navbar');
            if (navbar) navbar.classList.add('nav-animate');

            gsap.from('body > div:not(.modal):not(.modal-backdrop)', {
                opacity: 0, y: 10, duration: 0.9, ease: 'power2.out', delay: 0.15
            });
        });

        gsap.utils.toArray('.fade-in').forEach((el, i) => {
            gsap.to(el, {
                opacity: 1, y: 0, duration: 0.9, ease: 'power2.out',
                delay: i * 0.04,
                scrollTrigger: { trigger: el, start: 'top 90%' }
            });
        });
        gsap.utils.toArray('.slide-in-left').forEach(el => {
            gsap.to(el, { opacity: 1, x: 0, duration: 0.9, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 90%' }});
        });
        gsap.utils.toArray('.slide-in-right').forEach(el => {
            gsap.to(el, { opacity: 1, x: 0, duration: 0.9, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 90%' }});
        });
        gsap.utils.toArray('.scale-in').forEach(el => {
            gsap.to(el, { opacity: 1, scale: 1, duration: 0.9, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 90%' }});
        });
    </script>
</body>
</html>
