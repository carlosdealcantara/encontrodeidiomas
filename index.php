<?php
require_once 'config.php';

$title          = t('home.title');
$current_page   = 'index.php';
$og_description = t('home.meta_description');
$canonical      = SITE_URL . langUrl('index.php');
$swiper_enabled = true;

$extra_head = '
<link rel="stylesheet" href="/assets/css/home.css?v=<?= ASSET_VERSION ?>">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "' . t('home.faq.q1') . '",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "' . t('home.faq.a1') . '"
      }
    },
    {
      "@type": "Question",
      "name": "' . t('home.faq.q2') . '",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "' . t('home.faq.a2') . '"
      }
    },
    {
      "@type": "Question",
      "name": "' . t('home.faq.q3') . '",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "' . t('home.faq.a3') . '"
      }
    }
  ]
}
</script>';

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
                        <h1><?= t('home.hero_heading') ?></h1>
                        <p class="welcome-text">
                            <?= t('home.welcome_text') ?>
                        </p>
                        <div class="hero-cta">
                            <a href="#modalidades" class="btn-hero-cta"><?= t('home.cta_participate') ?></a>
                            <a href="<?= langUrl('links.php') ?>" class="btn-hero-secondary"><?= t('home.cta_links') ?> <i class="fas fa-chevron-right" style="font-size: 0.8em; margin-left: 5px; opacity: 0.7;"></i></a>
                        </div>
                    </div>

                    <!-- Column 2: The Integrated Carousel -->
                    <div class="hero-visual">
                        <div class="swiper photo-swiper">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/encontrodeidiomas-20250407-0001.jpg" alt="Encontro de Idiomas - <?= t('home.hero_labels.presencial') ?>" fetchpriority="high">
                                        <div class="photo-label"><?= t('home.hero_labels.presencial') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/encontrodeidiomas-20250407-0002.jpg" alt="Encontro de Idiomas - <?= t('home.hero_labels.online') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.online') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/replay.png" alt="<?= t('home.hero_labels.replay') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.replay') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/encontrodeidiomas-20250408-0013.jpg" alt="<?= t('home.hero_labels.activities') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.activities') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/mentoria.jpg" alt="<?= t('home.hero_labels.mentorship') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.mentorship') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/IMG_20250408_175458_304.jpg" alt="<?= t('home.hero_labels.outdoor') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.outdoor') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/Grupos.png" alt="<?= t('home.hero_labels.varied') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.varied') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/IMG_20250408_174649_714.jpg" alt="<?= t('home.hero_labels.moments') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.moments') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/instagram_social.png" alt="<?= t('home.hero_labels.social') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.social') ?></div>
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
                <p class="section-eyebrow"><?= t('home.modalities.eyebrow') ?></p>
                <h2 class="section-heading"><?= t('home.modalities.heading') ?></h2>
                <p class="section-desc">
                    <?= t('home.modalities.desc') ?>
                </p>
                <div class="modalidades-grid">
                    <a href="<?= langUrl('online.php') ?>" class="mod-card mod-card-online">
                        <div class="mod-icon"><i class="fas fa-laptop"></i></div>
                        <h3><?= t('home.modalities.online_title') ?></h3>
                        <p><?= t('home.modalities.online_text') ?></p>
                        <span class="mod-card-link"><?= t('home.modalities.online_link') ?> <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="<?= langUrl('presencial.php') ?>" class="mod-card mod-card-presencial">
                        <div class="mod-icon"><i class="fas fa-map-marked-alt"></i></div>
                        <h3><?= t('home.modalities.presencial_title') ?></h3>
                        <p><?= t('home.modalities.presencial_text') ?></p>
                        <span class="mod-card-link"><?= t('home.modalities.presencial_link') ?> <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>
            </div>
        </section>

        <!-- COMO FUNCIONA -->
        <section class="how-section">
            <div class="container">
                <p class="section-eyebrow"><?= t('home.how_it_works.eyebrow') ?></p>
                <h2 class="section-heading"><?= t('home.how_it_works.heading') ?></h2>
                <div class="how-flow">
                    <div class="how-step">
                        <div class="how-step-icon"><i class="fas fa-search"></i></div>
                        <h3><?= t('home.how_it_works.step1_title') ?></h3>
                        <p><?= t('home.how_it_works.step1_text') ?></p>
                    </div>
                    <div class="how-step">
                        <div class="how-step-icon"><i class="fas fa-comments"></i></div>
                        <h3><?= t('home.how_it_works.step2_title') ?></h3>
                        <p><?= t('home.how_it_works.step2_text') ?></p>
                    </div>
                    <div class="how-step">
                        <div class="how-step-icon"><i class="fas fa-rocket"></i></div>
                        <h3><?= t('home.how_it_works.step3_title') ?></h3>
                        <p><?= t('home.how_it_works.step3_text') ?></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ SECTION -->
        <section class="faq-section" style="padding: 90px 0; background: #fff;">
            <div class="container">
                <p class="section-eyebrow"><?= t('home.faq.eyebrow') ?></p>
                <h2 class="section-heading"><?= t('home.faq.heading') ?></h2>
                <div style="max-width: 800px; margin: 40px auto 0;">
                    <div style="margin-bottom: 30px;">
                        <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--primary-color);"><?= t('home.faq.q1') ?></h3>
                        <p style="color: #666;"><?= t('home.faq.a1') ?></p>
                    </div>
                    <div style="margin-bottom: 30px;">
                        <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--primary-color);"><?= t('home.faq.q2') ?></h3>
                        <p style="color: #666;"><?= t('home.faq.a2') ?></p>
                    </div>
                    <div style="margin-bottom: 30px;">
                        <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--primary-color);"><?= t('home.faq.q3') ?></h3>
                        <p style="color: #666;"><?= t('home.faq.a3') ?></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- COMMUNITY CTA -->
        <section class="community-cta">
            <div class="container">
                <h2><?= t('home.community.heading') ?></h2>
                <p>
                    <?= t('home.community.text') ?>
                </p>

                <!-- Authority Seal (Final Trust Point) -->
                <div class="footer-seal-anchor">
                    <div class="premium-seal-section">
                        <div class="seal-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="seal-text">
                            <span class="seal-eyebrow"><?= t('home.authority_seal.eyebrow') ?></span>
                            <span class="seal-main"><?= t('authority.text') ?></span>
                        </div>
                    </div>
                </div>
                <div class="cta-cards">
                    <a href="<?= langUrl('equipe.php') ?>" class="cta-card">
                        <i class="fas fa-users"></i>
                        <h3><?= t('home.community.team_title') ?></h3>
                        <p><?= t('home.community.team_text') ?></p>
                    </a>
                    <a href="<?= langUrl('links.php') ?>" class="cta-card">
                        <i class="fas fa-link"></i>
                        <h3><?= t('home.community.links_title') ?></h3>
                        <p><?= t('home.community.links_text') ?></p>
                    </a>
                    <a href="<?= langUrl('contato.php') ?>" class="cta-card">
                        <i class="fas fa-envelope"></i>
                        <h3><?= t('home.community.contact_title') ?></h3>
                        <p><?= t('home.community.contact_text') ?></p>
                    </a>
                    <a href="https://www.instagram.com/encontrodeidiomas" class="cta-card" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-instagram"></i>
                        <h3><?= t('home.community.social_title') ?></h3>
                        <p><?= t('home.community.social_text') ?></p>
                    </a>
                </div>
                <a href="<?= langUrl('links.php') ?>" class="btn-cta-white">
                    <i class="fas fa-rocket"></i> <?= t('home.community.cta_start') ?>
                </a>
            </div>
        </section>

<?php include 'includes/footer.php'; ?>
