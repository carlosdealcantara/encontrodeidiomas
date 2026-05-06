<?php
require_once 'config.php';
require_once 'includes/db_online.php';
require_once 'includes/components.php';

$title          = 'Online';
$current_page   = 'online.php';
$og_description = 'Encontro de Idiomas Online - Comunidade gratuita para praticar idiomas via videoconferência.';
$canonical      = 'https://encontrodeidiomas.com.br/online.php';

// Arquivos Externos (CSS e JS)
$extra_head = '<link rel="stylesheet" href="assets/css/online.css">';

include 'includes/header.php';
?>

<main>
    <section class="hero">
        <div class="hero-content container">
            <h1>Prática <span>Online</span> com pessoas<br>reais de todo o mundo</h1>
            <p>
                Conecte-se com pessoas de todo o mundo sem sair de casa. 
                Nossos encontros online são o coração da nossa comunidade global, 
                proporcionando um ambiente gratuito e acolhedor para você destravar sua fala, fazer amigos e praticar diversos idiomas em tempo real com nativos e entusiastas.
            </p>
            <div class="hero-cta-row">
                <a href="#calendar" class="hero-button"><i class="fas fa-calendar-alt"></i> Ver Programação</a>
                <a href="equipe.php#seja-host" class="hero-button-outline"><i class="fas fa-user-plus"></i> Seja um Anfitrião</a>
            </div>
            
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="num">50+</div>
                    <div class="lbl">Encontros Mensais</div>
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
                                 alt="Bandeira">
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
                                    <img src="https://flagcdn.com/32x24/<?= htmlspecialchars($lang['flag_code']) ?>.png" class="flag-icon" alt="<?= htmlspecialchars($lang['name']) ?>">
                                <?php elseif (!empty($lang['flag_emoji'])): ?>
                                    <span style="font-size:1.2rem;"><?= $lang['flag_emoji'] ?></span>
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
        </div>
    </section>
</main>

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
