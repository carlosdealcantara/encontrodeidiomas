<?php
require_once 'config.php';

$title          = 'Encontro de Idiomas';
$current_page   = 'index.php';
$og_description = 'Participe gratuitamente de encontros para praticar inglês, espanhol, francês, alemão, japonês e outros idiomas online e presenciais.';
$canonical      = 'https://encontrodeidiomas.com.br/';
$swiper_enabled = true;

$page_styles = <<<CSS
    /* Swiper Slider */
    .swiper {
        margin-top: 40px;
        width: 100%;
        height: 600px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(0,0,0,.1);
    }

    .swiper-slide {
        text-align: center;
        background: #fff;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
    }

    .swiper-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .slide-caption {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0,0,0,.8), transparent);
        color: var(--white);
        padding: 30px;
        text-align: left;
    }

    .slide-caption h3 { font-size: 1.5rem; margin-bottom: .5rem; }

    /* Features */
    .features {
        padding: 60px 0;
        background-color: var(--bg-light);
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
        text-align: center;
    }

    .feature-item {
        padding: 30px;
        background: var(--white);
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,.1);
        transition: transform 0.3s ease;
        border-top: 4px solid var(--accent-red);
    }

    .feature-item:nth-child(2) { border-top-color: var(--accent-blue); }
    .feature-item:nth-child(3) { border-top-color: var(--accent-yellow); }

    .feature-item:hover { transform: translateY(-5px); }

    .feature-icon {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }

    /* CTA Section */
    .cta-section {
        background: var(--primary-color);
        padding: 60px 0;
        text-align: center;
        color: var(--white);
        position: relative;
        overflow: hidden;
    }

    .cta-section::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(to right, var(--accent-red), var(--accent-blue), var(--accent-yellow));
    }

    .cta-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 30px;
    }

    .cta-button {
        display: inline-flex;
        align-items: center;
        padding: 12px 30px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .cta-button i { margin-right: 10px; }

    .primary-button   { background: var(--accent-red); color: var(--white); }
    .secondary-button { background: transparent; color: var(--white); border: 2px solid var(--white); }

    .cta-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,.2);
    }

    @media (max-width: 768px) {
        .swiper { height: 450px; }
        .features-grid { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 480px) {
        .swiper { height: 350px; }
        .features-grid { grid-template-columns: 1fr; }
        .cta-buttons { flex-direction: column; }
        .cta-button { width: 100%; justify-content: center; }
        .swiper-button-prev, .swiper-button-next { display: none; }
    }
CSS;

$page_scripts = <<<JS
<script>
    new Swiper('.swiper', {
        loop: true,
        effect: 'fade',
        autoplay: { delay: 5000, disableOnInteraction: false },
        pagination: { el: '.swiper-pagination', clickable: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    });
</script>
JS;

include 'includes/header.php';
?>

    <main>
        <div class="container">
            <!-- Swiper Slider -->
            <div class="swiper">
                <div class="swiper-wrapper">
                    <div class="swiper-slide">
                        <img src="assets/images/encontrodeidiomas-20250407-0001.jpg" alt="Encontro presencial com participantes">
                        <div class="slide-caption">
                            <h3>Encontros Presenciais</h3>
                            <p>Pratique idiomas em um ambiente acolhedor</p>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/encontrodeidiomas-20250407-0002.jpg" alt="Encontro online de idiomas">
                        <div class="slide-caption">
                            <h3>Encontros Online</h3>
                            <p>Participe de onde estiver, pelo seu computador ou celular</p>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/encontrodeidiomas-20250408-0002.jpg" alt="Piquenique de idiomas">
                        <div class="slide-caption">
                            <h3>Eventos Sociais</h3>
                            <p>Integre-se à comunidade em eventos divertidos</p>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/encontrodeidiomas-20250408-0013.jpg" alt="Atividades em grupo">
                        <div class="slide-caption">
                            <h3>Atividades Interativas</h3>
                            <p>Aprenda jogando e interagindo com outros participantes</p>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/IMG_20250408_174649_714.jpg" alt="Encontro ao ar livre">
                        <div class="slide-caption">
                            <h3>Momentos Marcantes</h3>
                            <p>Crie memórias enquanto aprende novos idiomas</p>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/IMG_20250408_175458_304.jpg" alt="Evento ao ar livre">
                        <div class="slide-caption">
                            <h3>Eventos ao Ar Livre</h3>
                            <p>Momentos especiais de aprendizado e diversão</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        </div>

        <section class="features">
            <div class="container">
                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-users"></i></div>
                        <h3>Encontros Gratuitos</h3>
                        <p>Participe de encontros online e presenciais sem nenhum custo</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-globe"></i></div>
                        <h3>Múltiplos Idiomas</h3>
                        <p>Pratique inglês, espanhol, francês e muito mais</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-calendar-alt"></i></div>
                        <h3>Horários Flexíveis</h3>
                        <p>Encontros em diversos horários para sua conveniência</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <div class="container">
                <h2>Comece a praticar hoje mesmo!</h2>
                <div class="cta-buttons">
                    <a href="links.php" class="cta-button primary-button">
                        <i class="fas fa-link"></i> Acessar Links
                    </a>
                    <a href="https://www.instagram.com/encontrodeidiomas" class="cta-button secondary-button" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-instagram"></i> Siga no Instagram
                    </a>
                </div>
            </div>
        </section>
    </main>

<?php include 'includes/footer.php'; ?>
