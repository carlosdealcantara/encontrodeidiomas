<?php
require_once 'config.php';
require_once 'includes/db_online.php';
require_once 'includes/components.php';

$title          = 'Praticar Idiomas Online Grátis - Programação Semanal';
$current_page   = 'online.php';
$og_description = 'Encontro de Idiomas Online - Comunidade gratuita para praticar inglês, espanhol e mais via videoconferência.';

// Dinamismo para SEO de Idiomas Específicos
if (!empty($_GET['idioma'])) {
    foreach ($languages as $lang) {
        if ($lang['id'] == $_GET['idioma']) {
            $title = 'Prática de ' . $lang['name'] . ' Online Grátis | ' . SITE_NAME;
            $og_description = 'Participe dos nossos encontros gratuitos de conversação em ' . $lang['name'] . ' via videoconferência. Pratique com pessoas reais.';
            break;
        }
    }
}

$canonical      = 'https://encontrodeidiomas.com.br/online.php';

// Structured Data: Online Events
$events_json = [];
foreach ($meetings as $m) {
    $events_json[] = [
        "@context" => "https://schema.org",
        "@type" => "Event",
        "name" => "Prática de " . $m['language_name'] . " - Encontro Online",
        "description" => "Encontro gratuito de conversação em " . $m['language_name'] . " via videoconferência.",
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
            "description" => "A melhor alternativa gratuita para quem busca um clube poliglota e prática de conversação online e presencial.",
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
        "startDate" => date('Y-m-d', strtotime("next " . getDayName($m['day_of_week']))) . "T" . sprintf("%02d:00:00-03:00", $m['time_hour']),
        "endDate" => date('Y-m-d', strtotime("next " . getDayName($m['day_of_week']))) . "T" . sprintf("%02d:00:00-03:00", $m['time_hour'] + 1)
    ];
}

// Arquivos Externos (CSS e JS)
$extra_head = '<link rel="stylesheet" href="assets/css/online.css">';
if (!empty($events_json)) {
    $extra_head .= '<script type="application/ld+json">' . json_encode($events_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
}

include 'includes/header.php';
?>

<main>
    <section class="hero">
        <div class="hero-content container">
            <h1>Fale <span>Online</span> com pessoas<br>reais de todo o planeta</h1>
            <p>
                Conecte-se com pessoas do mundo todo sem sair de casa. Junte-se à nossa comunidade global: um ambiente online gratuito e acolhedor para destravar a fala, fazer novas amizades e praticar idiomas com nativos.
            </p>
            <div class="hero-cta-row">
                <a href="#calendar" class="hero-button"><i class="fas fa-calendar-alt"></i> Ver Programação</a>
                <a href="equipe.php#seja-host" class="hero-button-outline"><i class="fas fa-user-plus"></i> Seja um Anfitrião</a>
            </div>
            
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="num">50+</div>
                    <div class="lbl">Encontros</div>
                </div>
                <div class="hero-stat">
                    <div class="num">10+</div>
                    <div class="lbl">Idiomas</div>
                </div>
                <div class="hero-stat">
                    <div class="num">100%</div>
                    <div class="lbl">Gratuito</div>
                </div>
                <div class="hero-stat">
                    <div class="num">∞</div>
                    <div class="lbl">Conexões</div>
                </div>
            </div>
        </div>
        <a href="#calendar" class="scroll-down"><i class="fas fa-chevron-down"></i></a>
    </section>

    <section id="calendar" class="calendar-section">
        <div class="container">
            <h2 class="section-title">Programação Semanal</h2>
            <p style="text-align:center;margin-bottom:2rem;">Fuso horário: GMT-3 (Horário de Brasília)<br>As chamadas são gravadas e disponibilizadas no Odysee.</p>

            <div class="calendar-nav">
                <div class="calendar-nav-title">Filtrar por:</div>
                <div class="view-toggle">
                    <button class="view-button <?= $initialView === 'day' ? 'active' : '' ?>" id="day-filter-btn" data-view="day">Dia da Semana</button>
                    <button class="view-button <?= $initialView === 'language' ? 'active' : '' ?>" id="language-filter-btn" data-view="language">Idioma</button>
                </div>
            </div>

            <!-- VIEW: IDIOMA -->
            <div id="language-view" class="view-content <?= $initialView === 'language' ? 'active' : '' ?>">
                <div class="mobile-dropdown">
                    <button class="dropdown-button" id="lang-dropdown-btn">
                        <div class="dropdown-flag-container">
                            <img id="selected-language-flag" 
                                 src="<?= $initialFlagCode ? "https://flagcdn.com/32x24/{$initialFlagCode}.png" : '' ?>" 
                                 class="flag-icon" 
                                 style="<?= $initialFlagCode ? '' : 'display:none;' ?>" 
                                  alt="Bandeira do idioma <?= htmlspecialchars($initialLangName) ?>">
                            <span id="selected-language-emoji" style="<?= $initialFlagEmoji ? '' : 'display:none;' ?>"><?= $initialFlagEmoji ?></span>
                            <span id="selected-language"><?= htmlspecialchars($initialLangName) ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content" id="lang-dropdown-content">
                        <div class="search-filter">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" id="language-search" placeholder="Buscar idioma...">
                        </div>
                        <?php foreach ($languages as $lang): 
                            if (empty($byLanguage[$lang['id']])) continue;
                        ?>
                        <button class="language-button <?= $lang['id'] == $initialLang ? 'active-lang' : '' ?>"
                                data-language-id="<?= $lang['id'] ?>"
                                data-language="<?= htmlspecialchars($lang['name']) ?>"
                                data-flag-code="<?= htmlspecialchars($lang['flag_code'] ?? '') ?>"
                                data-flag-emoji="<?= htmlspecialchars($lang['flag_emoji'] ?? '') ?>">
                            <div class="language-info">
                                <?php if (!empty($lang['flag_code'])): ?>
                                    <img src="https://flagcdn.com/32x24/<?= htmlspecialchars($lang['flag_code']) ?>.png" class="flag-icon" alt="Bandeira - <?= htmlspecialchars($lang['name']) ?>">
                                <?php elseif (!empty($lang['flag_emoji'])): ?>
                                    <span style="font-size:1.2rem;" role="img" aria-label="Emoji <?= htmlspecialchars($lang['name']) ?>"><?= $lang['flag_emoji'] ?></span>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($lang['name']) ?></span>
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
                    $dayNames = [1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado',7=>'Domingo'];
                    foreach ($dayNames as $num => $name): ?>
                    <button class="day-button <?= $num == $initialDay ? 'active' : '' ?>" data-day="<?= $num ?>"><?= $name ?></button>
                    <?php endforeach; ?>
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
                        } else { ?>
                            <div class="empty-day-card">
                                <div class="empty-day-icon">🚀</div>
                                <h3>Que tal ser o próximo Anfitrião?</h3>
                                <p>Se o idioma ou horário que você procura não está aqui, você pode ser a pessoa que faz ele acontecer!</p>
                                <a href="equipe.php#seja-host" class="empty-day-button">Quero ser um Host</a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
    </section>
</main>

<!-- SEO Language Navigation — Subtle Professional Index -->
<section class="seo-language-nav" style="padding: 40px 0; background: #fafafa; border-top: 1px solid #eee;">
    <div class="container" style="opacity: 0.7; transition: opacity 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
        <p style="margin-bottom: 15px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #888;">Índice de Prática por Idioma</p>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($languages as $lang): 
                if (empty($byLanguage[$lang['id']])) continue;
            ?>
            <a href="online.php?view=language&idioma=<?= $lang['id'] ?>" style="color: #666; text-decoration: none; font-size: 0.75rem; border: 1px solid #d0d0d0; padding: 4px 12px; border-radius: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <?= htmlspecialchars($lang['name']) ?> Online
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
        initialLang: '<?= $initialLang ?>'
    };
</script>
<script src="assets/js/online.js"></script>
<?php
$page_scripts = ob_get_clean();

include 'includes/footer.php';
?>
