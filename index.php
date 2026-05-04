<?php
require_once 'config.php';

$title          = 'Encontro de Idiomas';
$current_page   = 'index.php';
$og_description = 'Participe gratuitamente de encontros para praticar inglês, espanhol, francês, alemão, japonês e outros idiomas online e presenciais.';
$canonical      = 'https://encontrodeidiomas.com.br/';
$swiper_enabled = true;

// Dynamic stats from DB
try {
    $conn = connectDB();
    $langCount     = (int) $conn->query("SELECT COUNT(*) FROM languages WHERE active = 1")->fetchColumn();
    $meetingCount  = (int) $conn->query("SELECT COUNT(*) FROM meetings WHERE active = 1")->fetchColumn();
    $hostCount     = (int) $conn->query("SELECT COUNT(*) FROM hosts WHERE status = 'ativo'")->fetchColumn();
    $presCount     = (int) $conn->query("SELECT COUNT(*) FROM in_person_events WHERE active = 1")->fetchColumn();
    $presCountries = (int) $conn->query("SELECT COUNT(DISTINCT COALESCE(country,'Brasil')) FROM in_person_events WHERE active = 1")->fetchColumn();
} catch (Exception $e) {
    $langCount = $meetingCount = $hostCount = $presCount = $presCountries = 0;
}

$extra_head = '<link rel="stylesheet" href="assets/css/home.css">';

$page_scripts = <<<JS
<script>
    new Swiper('.photo-swiper', {
        loop: true,
        slidesPerView: 'auto',
        centeredSlides: true,
        spaceBetween: 20,
        speed: 600,
        autoplay: { delay: 4000, disableOnInteraction: false },
        pagination: { el: '.swiper-pagination', clickable: true },
    });
</script>
JS;

include 'includes/header.php';
?>

    <main>
        <!-- HERO — Light warm gradient with mosaic -->
        <section class="hero-home">
            <div class="container">
                <div class="hero-inner">
                    <div class="hero-text">
                        <div class="hero-tag">
                            <i class="fas fa-heart" style="font-size:0.7rem;"></i> 100% gratuito e voluntário
                        </div>
                        <h1>
                            Pratique idiomas com<br><span class="highlight">pessoas reais</span>
                        </h1>
                        <p class="hero-subtitle">
                            Comunidade gratuita com encontros online via videoconferência e presenciais em diversas cidades.
                            Sem matrícula, sem compromisso — apenas conversação genuína.
                        </p>
                        <div class="hero-cta-row">
                            <a href="online.php" class="btn-hero btn-hero-online">
                                <i class="fas fa-video"></i> Encontros Online
                            </a>
                            <a href="presencial.php" class="btn-hero btn-hero-presencial">
                                <i class="fas fa-map-marker-alt"></i> Encontros Presenciais
                            </a>
                        </div>
                        <div class="hero-stats-pills">
                            <div class="stat-pill">
                                <i class="fas fa-globe"></i>
                                <strong><?= $langCount ?>+</strong> idiomas
                            </div>
                            <div class="stat-pill">
                                <i class="fas fa-calendar-check"></i>
                                <strong><?= $meetingCount ?>+</strong> encontros/semana
                            </div>
                            <div class="stat-pill">
                                <i class="fas fa-map-pin"></i>
                                <strong><?= $presCount ?>+</strong> cidades
                            </div>
                            <div class="stat-pill">
                                <i class="fas fa-flag"></i>
                                <strong><?= $presCountries ?>+</strong> países
                            </div>
                        </div>
                    </div>
                    <div class="hero-mosaic">
                        <img src="assets/images/encontrodeidiomas-20250407-0001.jpg" alt="Encontro presencial">
                        <img src="assets/images/encontrodeidiomas-20250408-0013.jpg" alt="Atividades em grupo">
                        <img src="assets/images/IMG_20250408_175458_304.jpg" alt="Evento ao ar livre">
                    </div>
                </div>
            </div>
        </section>

        <!-- PHOTO STRIP -->
        <section class="photo-strip">
            <p class="section-eyebrow">Nossa Comunidade</p>
            <h2>Momentos que marcam</h2>
            <div class="swiper photo-swiper">
                <div class="swiper-wrapper">
                    <div class="swiper-slide">
                        <img src="assets/images/encontrodeidiomas-20250407-0001.jpg" alt="Encontro presencial com participantes">
                        <div class="photo-caption">Encontros Presenciais</div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/encontrodeidiomas-20250407-0002.jpg" alt="Encontro online de idiomas">
                        <div class="photo-caption">Encontros Online</div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/encontrodeidiomas-20250408-0002.jpg" alt="Piquenique de idiomas">
                        <div class="photo-caption">Eventos Sociais</div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/encontrodeidiomas-20250408-0013.jpg" alt="Atividades em grupo">
                        <div class="photo-caption">Atividades Interativas</div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/IMG_20250408_174649_714.jpg" alt="Encontro ao ar livre">
                        <div class="photo-caption">Momentos Marcantes</div>
                    </div>
                    <div class="swiper-slide">
                        <img src="assets/images/IMG_20250408_175458_304.jpg" alt="Evento ao ar livre">
                        <div class="photo-caption">Eventos ao Ar Livre</div>
                    </div>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- MODALIDADES -->
        <section class="modalidades-section">
            <div class="container">
                <p class="section-eyebrow">Duas formas de participar</p>
                <h2>Escolha como praticar</h2>
                <p class="modalidades-desc">
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

        <!-- COMO FUNCIONA — Horizontal flow (unique from presencial) -->
        <section class="how-section">
            <div class="container">
                <p class="section-eyebrow">Como participar</p>
                <h2>Simples, gratuito e sem burocracia</h2>
                <div class="how-flow">
                    <div class="how-step">
                        <div class="how-step-icon"><i class="fas fa-search"></i></div>
                        <h3>Escolha um encontro</h3>
                        <p>Navegue pela programação online ou encontre o grupo presencial da sua cidade.</p>
                    </div>
                    <div class="how-step">
                        <div class="how-step-icon"><i class="fas fa-hand-wave"></i></div>
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
                    Conheça pessoas incríveis e construa algo maior junto com a gente.
                </p>
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
    </main>

<?php include 'includes/footer.php'; ?>
