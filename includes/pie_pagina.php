    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <!-- GSAP ScrollTrigger -->
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Animación de entrada de la página
        document.addEventListener('DOMContentLoaded', function() {
            // Animación del navbar
            const navbar = document.querySelector('.navbar');
            if (navbar) {
                navbar.classList.add('nav-animate');
            }

            // Animación de entrada suave para toda la página
            gsap.from('body > div:not(.modal):not(.modal-backdrop)', {
                opacity: 0,
                y: 20,
                duration: 0.7,
                ease: 'power2.out',
                delay: 0.2
            });
        });

        // Animaciones por scroll
        gsap.utils.toArray('.fade-in').forEach((el, i) => {
            gsap.to(el, { 
                opacity: 1, y: 0, duration: 0.7, 
                ease: 'power2.out',
                delay: i * 0.05,
                scrollTrigger: { trigger: el, start: 'top 88%' }
            });
        });
        gsap.utils.toArray('.slide-in-left').forEach(el => {
            gsap.to(el, { opacity: 1, x: 0, duration: 0.7, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 88%' }});
        });
        gsap.utils.toArray('.slide-in-right').forEach(el => {
            gsap.to(el, { opacity: 1, x: 0, duration: 0.7, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 88%' }});
        });
        gsap.utils.toArray('.scale-in').forEach(el => {
            gsap.to(el, { opacity: 1, scale: 1, duration: 0.6, ease: 'back.out(1.4)', scrollTrigger: { trigger: el, start: 'top 88%' }});
        });
    </script>
</body>
</html>
