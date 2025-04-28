    </main>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo-section">
                    <img src="assets/images/logo.png" alt="Encontro de Idiomas" class="footer-logo">
                    <div class="site-info">
                        <div class="footer-title">Encontro de Idiomas</div>
                        <div class="footer-tagline">Aprenda se divertindo!</div>
                    </div>
                </div>
                
                <div class="footer-nav">
                    <div class="footer-section">
                        <h3>Navegação</h3>
                        <ul>
                            <li><a href="index.php">Início</a></li>
                            <li><a href="online.php">Encontros Online</a></li>
                            <li><a href="equipe.php">Nossa Equipe</a></li>
                            <li><a href="links.php">Links Úteis</a></li>
                            <li><a href="contato.php">Contato</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-section">
                        <h3>Social</h3>
                        <div class="social-links">
                            <a href="https://www.instagram.com/encontrodeidiomas" target="_blank" class="social-link">
                                <i class="fab fa-instagram"></i>
                                <span>Instagram</span>
                            </a>
                            <a href="https://www.tiktok.com/@encontrodeidiomas" target="_blank" class="social-link">
                                <i class="fab fa-tiktok"></i>
                                <span>TikTok</span>
                            </a>
                            <a href="https://www.youtube.com/@encontrodeidiomas" target="_blank" class="social-link">
                                <i class="fab fa-youtube"></i>
                                <span>YouTube</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="copyright">
                &copy; <?= date('Y') ?> Encontro de Idiomas. Todos os direitos reservados.
            </div>
        </div>
    </footer>
    
    <script>
        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('show');
        });
    </script>
    
    <?php if(isset($swiper_enabled) && $swiper_enabled): ?>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <?php endif; ?>
    
    <?php if(isset($page_scripts)): ?>
    <?= $page_scripts ?>
    <?php endif; ?>
</body>
</html> 