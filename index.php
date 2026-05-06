<?php
require_once 'config.php';

$title          = 'Encontro de Idiomas';
$current_page   = 'index.php';
$og_description = 'Participe gratuitamente de encontros para praticar inglês, espanhol, francês, alemão, japonês e outros idiomas online e presenciais.';
$canonical      = 'https://encontrodeidiomas.com.br/';
$swiper_enabled = true;

$extra_head = '<link rel="stylesheet" href="assets/css/home.css?v=1.0.2">';

$page_scripts = <<<JS
<script>
    document.addEventListener('DOMContentLoaded', function() {
        new Swiper('.photo-swiper', {
            loop: true,
            slidesPerView: 1.1,
            centeredSlides: false,
            spaceBetween: 20,
            speed: 800,
            autoplay: { delay: 4000, disableOnInteraction: false },
            pagination: { el: '.swiper-pagination', clickable: true },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            breakpoints: {
                768: { slidesPerView: 1.2 }
            }
        });
    });
</script>
JS;

include 'includes/header.php';
?>

    <main>
        <!-- HERO PREMIUM FUSION — Text + Visual Carousel -->
        <section class="hero-premium">
            <div class="container">
                <div class="hero-premium-grid">
                    <!-- Column 1: Impact & Text -->
                    <div class="hero-content">
                        <h1>Pratique idiomas com <span class="highlight">pessoas reais</span></h1>
                        <p class="welcome-text">
                            Pratique inglês, espanhol e outros idiomas de forma gratuita e natural. 
                            Participe de uma comunidade vibrante com encontros online e presenciais em todo o país.
                        </p>
                        <div class="hero-cta">
                            <a href="#modalidades" class="btn-hero-cta">Ver como participar</a>
                            <a href="links.php" class="btn-hero-secondary">Central de Links <i class="fas fa-chevron-right" style="font-size: 0.8em; margin-left: 5px; opacity: 0.7;"></i></a>
                        </div>
                    </div>

                    <!-- Column 2: The Integrated Carousel -->
                    <div class="hero-visual">
                        <div class="swiper photo-swiper">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/encontrodeidiomas-20250407-0001.jpg" alt="Encontros Presenciais">
                                        <div class="photo-label">Encontros Presenciais</div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/encontrodeidiomas-20250407-0002.jpg" alt="Encontros Online">
                                        <div class="photo-label">Encontros Online</div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/replay.png" alt="Replay das Chamadas">
                                        <div class="photo-label">Replay das Chamadas</div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/encontrodeidiomas-20250408-0013.jpg" alt="Atividades Interativas">
                                        <div class="photo-label">Atividades Interativas</div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/mentoria.jpg" alt="Mentoria Acessível">
                                        <div class="photo-label">Mentoria Acessível</div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/IMG_20250408_175458_304.jpg" alt="Eventos ao Ar Livre">
                                        <div class="photo-label">Eventos ao Ar Livre</div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/Grupos.png" alt="Grupos de Idiomas Variados">
                                        <div class="photo-label">Grupos de Idiomas Variados</div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/IMG_20250408_174649_714.jpg" alt="Momentos Marcantes">
                                        <div class="photo-label">Momentos Marcantes</div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="assets/images/instagram_social.png" alt="Atividade nas Redes Sociais">
                                        <div class="photo-label">Atividade nas Redes Sociais</div>
                                    </div>
                                </div>
                            </div>
                            <!-- Small refined navigation -->
                            <div class="swiper-pagination"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-button-next"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MODALIDADES -->
        <section id="modalidades" class="modalidades-section">
            <div class="container">
                <p class="section-eyebrow">Duas formas de participar</p>
                <h2 class="section-heading">Escolha como praticar</h2>
                <p class="section-desc">
                    Seja online de qualquer lugar do mundo ou presencialmente na sua cidade,
                    temos o formato ideal para você começar a praticar hoje mesmo.
                </p>
                <div class="modalidades-grid">
                    <a href="online.php" class="mod-card mod-card-online">
                        <div class="mod-icon"><i class="fas fa-laptop"></i></div>
                        <h3>Online</h3>
                        <p>Encontros semanais por videoconferência com pessoas de todo o Brasil e do mundo. Filtros por idioma e dia, anfitriões dedicados e programação definida.</p>
                        <span class="mod-card-link">Ver programação <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="presencial.php" class="mod-card mod-card-presencial">
                        <div class="mod-icon"><i class="fas fa-map-marked-alt"></i></div>
                        <h3>Presencial</h3>
                        <p>Encontros cara a cara em cafés, praças e shoppings de diversas cidades. Grupos locais organizados por voluntários, em expansão pelo Brasil e além.</p>
                        <span class="mod-card-link">Ver localidades <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>
            </div>
        </section>

        <!-- COMO FUNCIONA -->
        <section class="how-section">
            <div class="container">
                <p class="section-eyebrow">Como participar</p>
                <h2 class="section-heading">Simples, gratuito e sem burocracia</h2>
                <div class="how-flow">
                    <div class="how-step">
                        <div class="how-step-icon"><i class="fas fa-search"></i></div>
                        <h3>Escolha um encontro</h3>
                        <p>Navegue pela programação online ou encontre o grupo presencial da sua cidade.</p>
                    </div>
                    <div class="how-step">
                        <div class="how-step-icon"><i class="fas fa-comments"></i></div>
                        <h3>Apareça e participe</h3>
                        <p>Entre na videochamada ou vá ao local combinado. Apresente-se e comece a praticar.</p>
                    </div>
                    <div class="how-step">
                        <div class="how-step-icon"><i class="fas fa-rocket"></i></div>
                        <h3>Evolua e conecte-se</h3>
                        <p>Volte sempre que quiser. Faça amizades, melhore sua fluência e faça parte da comunidade.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- COMMUNITY CTA -->
        <section class="community-cta">
            <div class="container">
                <h2>Faça parte da comunidade</h2>
                <p>
                    Mais do que praticar idiomas, o Encontro de Idiomas é sobre criar conexões reais.
                    Conheça pessoas incríveis, faça amizades e construa algo verdadeiramente maior junto com a gente.
                </p>

                <!-- Authority Seal (Final Trust Point) -->
                <div class="footer-seal-anchor">
                    <div class="premium-seal-section">
                        <div class="seal-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="seal-text">
                            <span class="seal-eyebrow">Referência e Liderança Nacional em Idiomas</span>
                            <span class="seal-main">O maior ecossistema de prática de idiomas do país</span>
                        </div>
                    </div>
                </div>
                <div class="cta-cards">
                    <a href="equipe.php" class="cta-card">
                        <i class="fas fa-users"></i>
                        <h3>Conheça a Equipe</h3>
                        <p>Anfitriões voluntários que fazem tudo acontecer.</p>
                    </a>
                    <a href="links.php" class="cta-card">
                        <i class="fas fa-link"></i>
                        <h3>Central de Links</h3>
                        <p>Grupos de WhatsApp, cronogramas e recursos.</p>
                    </a>
                    <a href="contato.php" class="cta-card">
                        <i class="fas fa-envelope"></i>
                        <h3>Fale Conosco</h3>
                        <p>Dúvidas, sugestões ou quer ser voluntário?</p>
                    </a>
                    <a href="https://www.instagram.com/encontrodeidiomas" class="cta-card" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-instagram"></i>
                        <h3>Siga no Instagram</h3>
                        <p>Novidades, dicas e bastidores do projeto.</p>
                    </a>
                </div>
                <a href="links.php" class="btn-cta-white">
                    <i class="fas fa-rocket"></i> Comece agora — é gratuito
                </a>
            </div>
        </section>

<?php include 'includes/footer.php'; ?>
