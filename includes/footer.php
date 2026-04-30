<?php
/**
 * Footer Global — Encontro de Idiomas
 *
 * Variáveis opcionais:
 *   $swiper_enabled bool  — Se true, carrega Swiper JS
 *   $page_scripts   string — Scripts JS específicos da página
 */
?>
    </main><!-- /main -->

    <footer class="footer">
        <div class="container">
            <div class="footer-content">

                <!-- Logo + Tagline -->
                <div class="footer-logo-section">
                    <img src="assets/images/logo.png" alt="Logo Encontro de Idiomas" class="footer-logo">
                    <div>
                        <div class="footer-title">Encontro de Idiomas</div>
                        <div class="footer-tagline">Pratique idiomas gratuitamente</div>
                    </div>
                </div>

                <!-- Navegação + Redes Sociais -->
                <nav class="footer-nav" aria-label="Rodapé">
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
                        <h3>Redes Sociais</h3>
                        <div class="social-links">
                            <a href="https://www.instagram.com/encontrodeidiomas" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                                <span>Instagram</span>
                            </a>
                            <a href="https://www.tiktok.com/@encontrodeidiomas" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="TikTok">
                                <i class="fab fa-tiktok"></i>
                                <span>TikTok</span>
                            </a>
                            <a href="https://www.youtube.com/@encontrodeidiomas" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="YouTube">
                                <i class="fab fa-youtube"></i>
                                <span>YouTube</span>
                            </a>
                            <a href="https://discord.com/invite/STHkrEhMpP" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="Discord">
                                <i class="fab fa-discord"></i>
                                <span>Discord</span>
                            </a>
                        </div>
                    </div>
                </nav>

            </div><!-- /footer-content -->

            <div class="copyright">
                &copy; <?= date('Y') ?> Encontro de Idiomas. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <style>
        /* ============================================================
           FOOTER STYLES
           ============================================================ */
        .footer {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 60px 0 20px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .footer-logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
        }

        .footer-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .footer-tagline {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .footer-nav {
            display: flex;
            gap: 60px;
            flex-wrap: wrap;
        }

        .footer-section h3 {
            font-size: 1.1rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 12px;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--accent-red);
            border-radius: 2px;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 10px;
        }

        .footer-section ul li a {
            color: var(--white);
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .footer-section ul li a:hover {
            opacity: 1;
        }

        .social-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .social-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--white);
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .social-link:hover {
            opacity: 1;
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,.1);
            font-size: 0.9rem;
            opacity: 0.7;
        }

        @media (max-width: 768px) {
            .footer-content {
                flex-direction: column;
                gap: 30px;
            }

            .footer-nav {
                flex-direction: column;
                gap: 30px;
            }
        }
    </style>

    <?php if (!empty($swiper_enabled)): ?>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <?php endif; ?>

    <?php if (!empty($page_scripts)) echo $page_scripts; ?>

</body>
</html>
