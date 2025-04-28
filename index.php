<?php
$title = "Encontro de Idiomas";
$swiper_enabled = true;

// Additional styles for this page
$page_styles = <<<EOT
/* Swiper Styles */
.swiper {
    width: 100%;
    height: 600px;
    margin: 40px 0;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.swiper-slide {
    text-align: center;
    background: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
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
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
    color: var(--white);
    padding: 30px;
    text-align: left;
}

.slide-caption h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

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
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    border-top: 4px solid var(--accent-red);
}

.feature-item:nth-child(2) {
    border-top-color: var(--accent-blue);
}

.feature-item:nth-child(3) {
    border-top-color: var(--accent-yellow);
}

.feature-item:hover {
    transform: translateY(-5px);
}

.feature-icon {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

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
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(to right, var(--accent-red), var(--accent-blue), var(--accent-yellow));
}

.cta-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 40px;
    flex-wrap: wrap;
}

.cta-button {
    display: inline-block;
    padding: 15px 30px;
    background-color: var(--accent-red);
    color: var(--white);
    text-decoration: none;
    border-radius: 30px;
    font-weight: 600;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    cursor: pointer;
}

.cta-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

.cta-button.secondary {
    background-color: transparent;
    border: 2px solid var(--white);
}

.cta-button.secondary:hover {
    background-color: var(--white);
    color: var(--primary-color);
}

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
    font-size: 1.2rem;
    margin-bottom: 20px;
    position: relative;
}

.footer-section h3::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 0;
    width: 40px;
    height: 3px;
    background: var(--accent-red);
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
    gap: 15px;
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
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.9rem;
    opacity: 0.7;
}

@media (max-width: 768px) {
    .swiper {
        height: 400px;
    }
    
    .features-grid {
        grid-template-columns: 1fr;
    }
    
    .cta-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .footer-content {
        flex-direction: column;
        gap: 30px;
    }
    
    .footer-nav {
        gap: 30px;
    }
}
EOT;

// Additional scripts for this page
$page_scripts = <<<EOT
<!-- Initialize Swiper -->
<script>
    var swiper = new Swiper('.swiper', {
        loop: true,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    });
</script>
EOT;

include 'includes/header.php';
?>

<div class="container">
    <!-- Slider main container -->
    <div class="swiper">
        <!-- Additional required wrapper -->
        <div class="swiper-wrapper">
            <!-- Slides -->
            <div class="swiper-slide">
                <img src="assets/images/encontrodeidiomas-20250408-0002.jpg" alt="Encontro de Idiomas">
                <div class="slide-caption">
                    <h3>Pratique idiomas gratuitamente</h3>
                    <p>Conheça nossa comunidade de intercâmbio linguístico</p>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="assets/images/encontrodeidiomas-20250407-0001.jpg" alt="Eventos online">
                <div class="slide-caption">
                    <h3>Encontros online</h3>
                    <p>Participe de videoconferências para praticar mais de 10 idiomas diferentes</p>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="assets/images/encontrodeidiomas-20250407-0002.jpg" alt="Eventos presenciais">
                <div class="slide-caption">
                    <h3>Eventos em diversas cidades</h3>
                    <p>Encontros presenciais em cafés, parques e outros locais</p>
                </div>
            </div>
        </div>
        
        <!-- Pagination and navigation buttons -->
        <div class="swiper-pagination"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
</div>

<section class="features">
    <div class="container">
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-video"></i>
                </div>
                <h3>Encontros Online</h3>
                <p>Participe de videoconferências gratuitas para praticar diversos idiomas, independente do seu nível. Ambiente acolhedor e inclusivo.</p>
            </div>
            
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-coffee"></i>
                </div>
                <h3>Eventos Presenciais</h3>
                <p>Encontros em cafés, parques e outros locais para conversação e intercâmbio cultural. Conheça pessoas que compartilham seu interesse.</p>
            </div>
            
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <h3>Comunidade Internacional</h3>
                <p>Faça parte de uma comunidade internacional de mais de 5.000 pessoas apaixonadas por idiomas e culturas.</p>
            </div>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container">
        <h2>Comece a praticar idiomas hoje mesmo!</h2>
        <p>Participe gratuitamente dos encontros online ou presenciais</p>
        <div class="cta-buttons">
            <a href="online.php" class="cta-button">Ver Encontros Online</a>
            <a href="contato.php" class="cta-button secondary">Entre em Contato</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?> 