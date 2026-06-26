<?php
require_once 'config.php';
require_once 'includes/db_online.php';
require_once 'includes/components.php';

$title          = t('online.title_default');
$current_page   = 'online.php';
$og_description = t('online.meta_description_default');

// Dinamismo para SEO de Idiomas Específicos
$allLangNames = array_map(function($l) { return $l['name']; }, $languages);
$meta_keywords = "encontro de idiomas, praticar inglês, conversação online, " . implode(', ', $allLangNames) . ", clube do livro, consultoria de carreira, oportunidades internacionais, intercâmbio, nomadismo digital";

if (!empty($_GET['idioma'])) {
    $currentLangCode = t('meta.lang_code');
    foreach ($languages as $lang) {
        if ($lang['id'] == $_GET['idioma']) {
            $displayName = ($currentLangCode === 'en' && !empty($lang['name_en'])) ? $lang['name_en'] : $lang['name'];
            $title = t('online.title_lang', ['lang' => $displayName]) . ' | ' . SITE_NAME;
            $og_description = t('online.meta_description_lang', ['lang' => $displayName]);
            $canonical = SITE_URL . langSlugUrl($lang);
            break;
        }
    }
}

$canonical      = $canonical ?? SITE_URL . langUrl('online.php');

// Structured Data: Online Events
$events_json = [];
$currentLangCode = t('meta.lang_code');
foreach ($meetings as $m) {
    $m_lang_name = ($currentLangCode === 'en' && !empty($m['language_name_en'])) ? $m['language_name_en'] : $m['language_name'];
    $events_json[] = [
        "@context" => "https://schema.org",
        "@type" => "Event",
        "name" => t('online.event_schema_name', ['lang' => $m_lang_name]),
        "description" => t('online.event_schema_desc', ['lang' => $m_lang_name]),
        "eventAttendanceMode" => "https://schema.org/OnlineEventAttendanceMode",
        "eventStatus" => "https://schema.org/EventScheduled",
        "location" => [
            "@type" => "VirtualLocation",
            "url" => $m['meet_link'] ?? SITE_URL
        ],
        "image" => SITE_URL . "/assets/images/og_image.png",
        "organizer" => [
            "@type" => "EducationalOrganization",
            "name" => SITE_NAME,
            "url" => SITE_URL,
            "description" => t('meta.org_description'),
            "sameAs" => [
                "https://www.instagram.com/encontrodeidiomas",
                "https://www.tiktok.com/@encontrodeidiomas",
                "https://discord.com/invite/STHkrEhMpP"
            ]
        ],
        "offers" => [
            "@type" => "Offer",
            "price" => "0",
            "priceCurrency" => "BRL",
            "availability" => "https://schema.org/InStock"
        ],
        "startDate" => date('Y-m-d', strtotime("next " . ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$m['day_of_week']-1])) . "T" . sprintf("%02d:00:00-03:00", $m['time_hour']),
        "endDate" => date('Y-m-d', strtotime("next " . ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$m['day_of_week']-1])) . "T" . sprintf("%02d:00:00-03:00", $m['time_hour'] + 1)
    ];
}

// Arquivos Externos (CSS e JS)
$extra_head = '<link rel="stylesheet" href="/assets/css/online.css?v=' . ASSET_VERSION . '">';
if (!empty($events_json)) {
    $extra_head .= '<script type="application/ld+json">' . json_encode($events_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
}

include 'includes/header.php';
?>

<main>
    <section class="hero">
        <div class="hero-content container">
            <h1><?= t('online.hero_heading') ?></h1>
            <p>
                <?= t('online.hero_text') ?>
            </p>
            <div class="hero-cta-row">
                <a href="#calendar" class="hero-button"><i class="fas fa-calendar-alt"></i> <?= t('online.hero_cta_calendar') ?></a>
                <a href="<?= SITE_URL . (CURRENT_LANG === 'pt' ? '/seja-host' : '/en/be-a-host') ?>" class="hero-button-outline"><i class="fas fa-user-plus"></i> <?= t('online.hero_cta_host') ?></a>
            </div>
            
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="num">50+</div>
                    <div class="lbl"><?= t('online.stats.meetings') ?></div>
                </div>
                <div class="hero-stat">
                    <div class="num">10+</div>
                    <div class="lbl"><?= t('online.stats.languages') ?></div>
                </div>
                <div class="hero-stat">
                    <div class="num">100%</div>
                    <div class="lbl"><?= t('online.stats.free') ?></div>
                </div>
                <div class="hero-stat">
                    <div class="num">∞</div>
                    <div class="lbl"><?= t('online.stats.connections') ?></div>
                </div>
            </div>
        </div>
        <a href="#calendar" class="scroll-down"><i class="fas fa-chevron-down"></i></a>
    </section>

    <section id="calendar" class="calendar-section">
        <div class="container">
            <h2 class="section-title"><?= t('online.calendar_heading') ?></h2>
            <!-- O timezone info label foi removido conforme solicitação para tornar transparente -->

            <div class="calendar-nav">
                <div class="calendar-nav-title"><?= t('online.filter_label') ?></div>
                <div class="view-toggle">
                    <button class="view-button <?= $initialView === 'day' ? 'active' : '' ?>" id="day-filter-btn" data-view="day"><?= t('online.filter_day') ?></button>
                    <button class="view-button <?= $initialView === 'language' ? 'active' : '' ?>" id="language-filter-btn" data-view="language"><?= t('online.filter_language') ?></button>
                </div>
            </div>

            <!-- VIEW: IDIOMA -->
            <div id="language-view" class="view-content <?= $initialView === 'language' ? 'active' : '' ?>">
                <div class="mobile-dropdown">
                    <button class="dropdown-button" id="lang-dropdown-btn">
                        <?php 
                            $currentLangCode = t('meta.lang_code');
                            $initialDisplayName = ($currentLangCode === 'en' && !empty($initialLangNameEn)) ? $initialLangNameEn : $initialLangName;
                        ?>
                        <div class="dropdown-flag-container">
                            <img id="selected-language-flag" 
                                 src="<?= $initialFlagCode ? "https://flagcdn.com/32x24/{$initialFlagCode}.png" : '' ?>" 
                                 class="flag-icon" 
                                 style="<?= $initialFlagCode ? '' : 'display:none;' ?>" 
                                  alt="Bandeira">
                            <span id="selected-language-emoji" style="<?= (!$initialFlagCode && $initialFlagEmoji) ? '' : 'display:none;' ?>"><?= $initialFlagEmoji ?></span>
                            <span id="selected-language"><?= htmlspecialchars($initialDisplayName) ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content" id="lang-dropdown-content">
                        <div class="search-filter">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" id="language-search" placeholder="<?= t('online.search_placeholder') ?>">
                        </div>
                        <?php foreach ($languages as $lang): 
                            if (empty($byLanguage[$lang['id']])) continue;
                            $langBtnName = ($currentLangCode === 'en' && !empty($lang['name_en'])) ? $lang['name_en'] : $lang['name'];
                        ?>
                        <button class="language-button <?= $lang['id'] == $initialLang ? 'active-lang' : '' ?>"
                                data-language-id="<?= $lang['id'] ?>"
                                data-language="<?= htmlspecialchars($langBtnName) ?>"
                                data-flag-code="<?= htmlspecialchars($lang['flag_code'] ?? '') ?>"
                                data-flag-emoji="<?= htmlspecialchars($lang['flag_emoji'] ?? '') ?>"
                                data-slug="<?= htmlspecialchars(langSlugUrl($lang)) ?>"
                                data-slug-pt="<?= htmlspecialchars(!empty($lang['slug_pt']) ? '/' . $lang['slug_pt'] : '/online?view=language&idioma='.$lang['id']) ?>"
                                data-slug-en="<?= htmlspecialchars(!empty($lang['slug_en']) ? '/en/' . $lang['slug_en'] : '/en/online?view=language&idioma='.$lang['id']) ?>">
                            <div class="language-info">
                                <?php if (!empty($lang['flag_code'])): ?>
                                    <img src="https://flagcdn.com/32x24/<?= htmlspecialchars($lang['flag_code']) ?>.png" class="flag-icon" alt="Bandeira">
                                <?php elseif (!empty($lang['flag_emoji'])): ?>
                                    <span style="font-size:1.2rem;" role="img" aria-label="Emoji"><?= $lang['flag_emoji'] ?></span>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($langBtnName) ?></span>
                            </div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="language-events">
                    <?php foreach ($languages as $lang): 
                        $langEvents = $byLanguage[$lang['id']] ?? [];
                        if (empty($langEvents)) continue;
                    ?>
                    <div id="lang-events-<?= $lang['id'] ?>" class="language-events-container" style="display:<?= $lang['id'] == $initialLang ? 'block' : 'none'; ?>;">
                        <div class="timeline">
                            <?php foreach ($langEvents as $ev) renderEventCard($ev, $currentDayOfWeek, $currentHour); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- VIEW: DIA DA SEMANA -->
            <div id="day-view" class="view-content <?= $initialView === 'day' ? 'active' : '' ?>">
                <div class="calendar-days">
                    <?php
                    for ($num = 1; $num <= 7; $num++): ?>
                    <button class="day-button <?= $num == $initialDay ? 'active' : '' ?>" data-day="<?= $num ?>"><?= getDayName($num) ?></button>
                    <?php endfor; ?>
                </div>

                <?php foreach ($dayNames as $dayNum => $dayName): ?>
                <div id="day-<?= $dayNum ?>" class="day-events <?= $dayNum == $initialDay ? 'active' : '' ?>">
                    <div class="timeline">
                        <?php 
                        $dayEvents = $byDay[$dayNum] ?? [];
                        $focusAssigned = false;
                        
                        if (!empty($dayEvents)) {
                            foreach ($dayEvents as $ev) {
                                $isTarget = false;
                                if (!$focusAssigned) {
                                    $evHour = (int)$ev['time_hour'];
                                    $isToday = ($currentDayOfWeek === (int)$ev['day_of_week']);
                                    $isNow = $isToday && ($currentHour === $evHour);
                                    $isFuture = ($isToday && $evHour > $currentHour);
                                    if ($isNow || $isFuture) { $isTarget = true; $focusAssigned = true; }
                                }
                                renderEventCard($ev, $currentDayOfWeek, $currentHour, $isTarget); 
                            }
                        }
                        ?>
                        <div class="empty-day-card" style="<?= !empty($dayEvents) ? 'display:none;' : '' ?>">
                            <div class="empty-day-icon">🚀</div>
                            <h3><?= t('online.empty_day_title') ?></h3>
                            <p><?= t('online.empty_day_text') ?></p>
                            <a href="<?= SITE_URL . (CURRENT_LANG === 'pt' ? '/seja-host' : '/en/be-a-host') ?>" class="empty-day-button"><?= t('online.empty_day_cta') ?></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
    </section>
</main>

<!-- SEO Language Navigation — Subtle Professional Index -->
<section class="seo-language-nav" style="padding: 40px 0; background: #fafafa; border-top: 1px solid #eee;">
    <div class="container" style="opacity: 0.7; transition: opacity 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
        <p style="margin-bottom: 15px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #888;"><?= t('online.seo_index_title') ?></p>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($languages as $lang): ?>
            <a href="<?= SITE_URL . langSlugUrl($lang) ?>" style="color: #666; text-decoration: none; font-size: 0.75rem; border: 1px solid #d0d0d0; padding: 4px 12px; border-radius: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <?= htmlspecialchars(($currentLangCode === 'en' && !empty($lang['name_en'])) ? $lang['name_en'] : $lang['name']) ?> <?= t('nav.online') ?>
            </a>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<?php
// JS Modular + Configurações Injetadas
ob_start(); ?>
<script>
    window.onlineConfig = {
        initialView: '<?= $initialView ?>',
        initialDay: '<?= $initialDay ?>',
        initialLang: '<?= $initialLang ?>',
        daySlugMap: <?= json_encode(getDaySlugMap(), JSON_UNESCAPED_UNICODE) ?>,
        siteLang: '<?= CURRENT_LANG ?>'
    };
</script>
<script src="/assets/js/online.js?v=<?= ASSET_VERSION ?>"></script>
<?php
$page_scripts = ob_get_clean();

include 'includes/footer.php';
?>
