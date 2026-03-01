    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- GSAP ScrollTrigger -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Animaciones
        gsap.utils.toArray('.fade-in').forEach(el => {
            gsap.to(el, { opacity: 1, y: 0, duration: 0.8, scrollTrigger: { trigger: el, start: 'top 85%' }});
        });
        gsap.utils.toArray('.slide-in-left').forEach(el => {
            gsap.to(el, { opacity: 1, x: 0, duration: 0.8, scrollTrigger: { trigger: el, start: 'top 85%' }});
        });
        gsap.utils.toArray('.slide-in-right').forEach(el => {
            gsap.to(el, { opacity: 1, x: 0, duration: 0.8, scrollTrigger: { trigger: el, start: 'top 85%' }});
        });
    </script>
</body>
</html>
