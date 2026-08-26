<?php
require_once 'config.php';

$title          = t('home.title');
$og_title       = t('home.og_title');
$current_page   = 'index.php';
$og_description = t('home.meta_description');
$canonical      = SITE_URL . langUrl('index.php');
$swiper_enabled = true;

// --- Lógica de Próximos Encontros ---
$all_meetings = getMeetings();
$currentDayOfWeek = (int)date('N');
$currentHour = (int)date('G');

$upcoming_meetings = [];
foreach ($all_meetings as $m) {
    $evDay = (int)$m['day_of_week'];
    $evHour = (int)$m['time_hour'];
    
    // Calcula quantos dias faltam (se já passou nesta semana, considera para a próxima)
    $daysDiff = $evDay - $currentDayOfWeek;
    if ($daysDiff < 0 || ($daysDiff === 0 && $evHour < $currentHour)) {
        $daysDiff += 7;
    }
    $sortScore = ($daysDiff * 24) + $evHour;
    $m['sort_score'] = $sortScore;
    $upcoming_meetings[] = $m;
}

usort($upcoming_meetings, function($a, $b) {
    return $a['sort_score'] <=> $b['sort_score'];
});

$next_meetings = array_slice($upcoming_meetings, 0, 3);
// ------------------------------------

$extra_head = '
<link rel="stylesheet" href="/assets/css/home.css?v=' . ASSET_VERSION . '">
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

$webpage_desc = (CURRENT_LANG === 'en')
    ? "Encontro de Idiomas is the largest free language practice community in Brazil. Weekly conversation meetups, online and in-person, for all levels."
    : "O Encontro de Idiomas é a maior comunidade gratuita de prática de idiomas do Brasil. Encontros de conversação semanais, online e presencial, para todos os níveis.";

$webpage_keys = (CURRENT_LANG === 'en')
    ? "language practice, free language exchange, practice english, practice spanish online, free conversation, language club, Ei, viaEi"
    : "encontro de idiomas, encontros de idiomas, clube de idiomas, clube de conversação, praticar inglês grátis, praticar espanhol online, conversação gratuita, Ei, viaEi";

$webpage_lang = (CURRENT_LANG === 'en') ? "en" : "pt-BR";

$extra_head .= '
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "@id": "' . SITE_URL . '/#webpage",
  "url": "' . SITE_URL . '/",
  "name": "Encontro de Idiomas",
  "description": "' . $webpage_desc . '",
  "keywords": "' . $webpage_keys . '",
  "inLanguage": "' . $webpage_lang . '",
  "isPartOf": {
    "@id": "' . SITE_URL . '/#website"
  },
  "about": {
    "@id": "' . SITE_URL . '/#organization"
  },
  "breadcrumb": {
    "@id": "' . SITE_URL . '/#breadcrumb"
  }
}
</script>';


$page_scripts = <<<JS
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Carrossel Principal (Hero)
        new Swiper('.photo-swiper', {
            loop: true,
            slidesPerView: 1.1,
            centeredSlides: false,
            spaceBetween: 20,
            speed: 800,
            autoplay: { delay: 4000, disableOnInteraction: false },
            pagination: { el: '.photo-swiper .swiper-pagination', clickable: true },
            navigation: { nextEl: '.photo-swiper .swiper-button-next', prevEl: '.photo-swiper .swiper-button-prev' },
            breakpoints: {
                768: { slidesPerView: 1.2 }
            }
        });

        // Mini Carrossel - Encontros Online (Sem autoplay, usuário arrasta)
        new Swiper('.meetups-swiper', {
            slidesPerView: 1,
            spaceBetween: 12,
            loop: false,
            pagination: { el: '.meetups-swiper .swiper-pagination', clickable: true }
        });

        // Mini Carrossel - Cidades Presenciais (Com autoplay suave)
        new Swiper('.presencial-swiper', {
            slidesPerView: 1,
            spaceBetween: 12,
            loop: true,
            speed: 700,
            autoplay: { delay: 3500, disableOnInteraction: false },
            pagination: { el: '.presencial-swiper .swiper-pagination', clickable: true }
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
                                        <img src="/assets/images/encontrodeidiomas-20250407-0001.jpg" alt="<?= t('meta.alt_presencial') ?>" fetchpriority="high">
                                        <div class="photo-label"><?= t('home.hero_labels.presencial') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/encontrodeidiomas-20250407-0002.jpg" alt="<?= t('meta.alt_online') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.online') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/replay.png" alt="<?= t('meta.alt_replay') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.replay') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/encontrodeidiomas-20250408-0013.jpg" alt="<?= t('meta.alt_activities') ?>">
                                        <div class="photo-label"><?= t('home.hero_labels.activities') ?></div>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="photo-card">
                                        <img src="/assets/images/mentoria.jpg" alt="<?= t('meta.alt_mentorship') ?>">
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
                    
                    <!-- WIDGET ONLINE -->
                    <div class="mod-card mod-card-online">
                        <div class="mod-card-header">
                            <div class="mod-icon"><i class="fas fa-laptop"></i></div>
                            <h3><?= t('home.modalities.online_title') ?></h3>
                        </div>
                        
                        <?php if (!empty($next_meetings)): ?>
                            <div class="swiper meetups-swiper">
                                <div class="swiper-wrapper">
                                    <?php foreach ($next_meetings as $ev): 
                                        $evDay = (int)$ev['day_of_week'];
                                        $evHour = (int)$ev['time_hour'];
                                        $isToday = ($currentDayOfWeek === $evDay);
                                        $isNow = $isToday && ($currentHour === $evHour);
                                        $flagCode = $ev['flag_code'] ?? '';
                                        $flagEmoji = $ev['flag_emoji'] ?? '';
                                        $current_lang = t('meta.lang_code');
                                        $langDisplayName = ($current_lang === 'en' && !empty($ev['language_name_en'])) ? $ev['language_name_en'] : ($ev['language_name'] ?? '');
                                    ?>
                                        <div class="swiper-slide">
                                            <div class="home-meetup-slide">
                                                <div class="hms-top">
                                                    <?php if ($flagCode): ?>
                                                        <img src="https://flagcdn.com/32x24/<?= htmlspecialchars($flagCode) ?>.png" class="hms-flag" alt="Bandeira">
                                                    <?php elseif ($flagEmoji): ?>
                                                        <span class="hms-emoji"><?= $flagEmoji ?></span>
                                                    <?php endif; ?>
                                                    <span class="hms-lang"><?= htmlspecialchars($langDisplayName) ?></span>
                                                    <?php if ($isNow): ?>
                                                        <span class="hms-live">AO VIVO</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="hms-time">
                                                    <i class="far fa-clock"></i> <?= getDayName($evDay) ?> &middot; <?= formatHour($evHour) ?>
                                                </div>
                                                <div class="hms-host">
                                                    <?php if (!empty($ev['host_name'])): ?>
                                                        <?php $hostPhotoUrl = getHostPhotoUrl($ev['host_photo'] ?? null); ?>
                                                        <img src="<?= $hostPhotoUrl ?>" alt="Host" onerror="this.src='/assets/images/logo.png'">
                                                        <span>Host: <strong><?= htmlspecialchars($ev['host_name']) ?></strong></span>
                                                    <?php else: ?>
                                                        <div class="hms-no-host"></div>
                                                        <span><strong>Conversação Livre</strong></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="swiper-pagination"></div>
                            </div>
                        <?php else: ?>
                            <p class="mod-card-text"><?= t('home.modalities.online_text') ?></p>
                        <?php endif; ?>

                        <div class="mod-card-footer">
                            <a href="<?= langUrl('online.php') ?>" class="mod-card-link"><?= t('home.modalities.online_link') ?> <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <!-- WIDGET PRESENCIAL -->
                    <div class="mod-card mod-card-presencial">
                        <div class="mod-card-header">
                            <div class="mod-icon"><i class="fas fa-map-marked-alt"></i></div>
                            <h3><?= t('home.modalities.presencial_title') ?></h3>
                        </div>

                        <div class="swiper presencial-swiper">
                            <div class="swiper-wrapper">
                                <!-- Brasília -->
                                <div class="swiper-slide">
                                    <div class="home-city-slide" style="background-image: url('/assets/images/encontrodeidiomas-20250407-0001.jpg');">
                                        <div class="hcs-overlay">
                                            <span class="hcs-city">Brasília</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- São Paulo -->
                                <div class="swiper-slide">
                                    <div class="home-city-slide" style="background-image: url('/assets/images/IMG_20250408_174649_714.jpg');">
                                        <div class="hcs-overlay">
                                            <span class="hcs-city">São Paulo</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Belo Horizonte -->
                                <div class="swiper-slide">
                                    <div class="home-city-slide" style="background-image: url('/assets/images/encontrodeidiomas-20250408-0013.jpg'); background-position: center 30%;">
                                        <div class="hcs-overlay">
                                            <span class="hcs-city">Belo Horizonte</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>

                        <div class="mod-card-footer">
                            <a href="<?= langUrl('presencial.php') ?>" class="mod-card-link"><?= t('home.modalities.presencial_link') ?> <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

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
