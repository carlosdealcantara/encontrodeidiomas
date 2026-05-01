<?php
require_once 'config.php';

$title          = 'Encontros Online';
$current_page   = 'online.php';
$og_description = 'Encontro de Idiomas Online - Comunidade gratuita para praticar idiomas via videoconferência.';
$canonical      = 'https://encontrodeidiomas.com.br/online.php';

// Busca eventos do BD, agrupa por dia
$events     = getEvents();
$languages  = getLanguages();
$byDay      = [];
$byLanguage = [];

foreach ($events as $e) {
    $byDay[$e['day_of_week']][]          = $e;
    $byLanguage[$e['language_id']][]     = $e;
}

$currentDayOfWeek = (int)date('N'); // 1=Seg ... 7=Dom

// Parâmetros iniciais da URL
$initialView = $_GET['view'] ?? 'day';
$initialDay  = $_GET['dia']  ?? $currentDayOfWeek;
if ($initialDay > 5) $initialDay = 1; // Fallback se for fds e não houver eventos mapeados no loop abaixo
$initialLang = $_GET['idioma'] ?? '';

ob_start();
?>
    /* ---- ONLINE PAGE STYLES ---- */
    .hero {
        min-height: 60vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        background: linear-gradient(135deg, #000428, #004e92);
        color: var(--white);
        position: relative;
        padding: 2rem 0;
        overflow: hidden;
        margin-top: 20px;
    }
    .hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url('assets/images/encontrodeidiomas-20250407-0002.jpg') center/cover;
        opacity: .2;
    }
    .hero-content {
        position: relative;
        z-index: 1;
        max-width: 800px;
        padding: 0 20px;
        animation: fadeUp 1s ease;
    }
    @keyframes fadeUp {
        from { opacity:0; transform:translateY(20px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .hero h1 {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        background: linear-gradient(to right, var(--accent-red), var(--accent-yellow));
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero p { font-size:1.2rem; margin-bottom:2rem; }
    .hero-button {
        display: inline-block;
        padding: 12px 32px;
        background: var(--accent-red);
        color: var(--white);
        border-radius: 50px;
        text-decoration: none;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        box-shadow: 0 5px 15px rgba(227,29,28,.4);
        transition: var(--transition);
    }
    .hero-button:hover { transform:translateY(-5px); }
    .scroll-down {
        position: absolute;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        color: var(--white);
        font-size: 2rem;
        animation: bounce 2s infinite;
    }
    @keyframes bounce {
        0%,20%,50%,80%,100% { transform:translateY(0) translateX(-50%); }
        40% { transform:translateY(-20px) translateX(-50%); }
        60% { transform:translateY(-10px) translateX(-50%); }
    }
    .calendar-section { padding:5rem 0; background:#fff; }
    .section-title { text-align:center; margin-bottom:30px; font-size:2rem; font-weight:700; }
    .calendar-nav { display:flex; justify-content:center; align-items:center; margin-bottom:30px; gap:15px; }
    .view-toggle { display:flex; gap:10px; background:#fff; border-radius:30px; padding:5px; box-shadow:0 4px 10px rgba(0,0,0,.1); }
    .view-button { background:none; border:none; color:var(--text-color); font-weight:600; cursor:pointer; padding:8px 15px; border-radius:25px; transition:all .3s ease; }
    .view-button.active { color:#fff; background:var(--accent-red); }
    .view-content { display:none; }
    .view-content.active { display:block; }
    .calendar-days { display:flex; justify-content:center; flex-wrap:wrap; gap:10px; margin-bottom:30px; }
    .day-button { padding:10px 16px; background:#fff; border:none; border-radius:25px; cursor:pointer; font-weight:600; box-shadow:0 4px 10px rgba(0,0,0,.1); transition:all .3s ease; min-width:120px; font-family: inherit; }
    .day-button:hover { transform:translateY(-3px); }
    .day-button.active { background:var(--accent-red); color:#fff; }
    .day-events { display:none; animation:fadeIn .5s ease; }
    .day-events.active { display:block; }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    .timeline { position:relative; max-width:800px; margin:0 auto; padding:20px 0; }
    .timeline::before { content:''; position:absolute; top:0; left:50%; transform:translateX(-50%); width:4px; height:100%; background:linear-gradient(to bottom,var(--accent-red),var(--accent-blue)); border-radius:2px; }
    .timeline-event { 
        position:relative; 
        margin-bottom:2rem; 
        width:45%; 
        background:var(--card-bg); 
        border-radius:var(--border-radius); 
        box-shadow:var(--shadow); 
        padding:20px; 
        transition:var(--transition); 
        cursor:pointer;
        /* Fix para texto embaçado no hover */
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        transform: translateZ(0);
        -webkit-transform: translateZ(0);
    }
    .timeline-event:hover { transform:translateY(-8px); }
    .timeline-event:nth-child(odd) { margin-left:auto; }
    .timeline-event::before { content:''; position:absolute; top:20px; width:20px; height:20px; background:#fff; border:4px solid var(--accent-red); border-radius:50%; z-index: 1; }
    .timeline-event:nth-child(odd)::before  { left:-60px; }
    .timeline-event:nth-child(even)::before { right:-60px; }
    .event-time { display:inline-block; background:var(--accent-blue); color:#fff; padding:5px 15px; border-radius:20px; font-weight:500; margin-bottom:10px; }
    .event-title { display:flex; align-items:center; gap:10px; font-size:1.5rem; font-weight:600; margin-bottom:10px; flex-wrap:wrap; }
    .event-social-links { display:flex; gap:5px; margin-left:10px; }
    .social-icon { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; font-size:.85rem; background:#f0f2f5; color:var(--text-color); border:1px solid #ddd; transition:all .3s ease; }
    .social-icon:hover { transform:translateY(-2px); }
    .whatsapp-icon:hover { color:#25D366; }
    .instagram-icon:hover { color:#E1306C; }
    .flag-icon { width:24px; height:16px; vertical-align:middle; border-radius:2px; object-fit:cover; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .event-description { margin:10px 0 15px; opacity:.8; font-size: 0.95rem; }
    .event-actions { display:flex; gap:10px; position:relative; }
    .event-button { 
        flex:1; 
        padding:10px 20px; 
        border:none; 
        border-radius:25px; 
        cursor:pointer; 
        font-weight:600; 
        transition:var(--transition); 
        text-align:center; 
        text-decoration:none; 
        display:inline-flex; 
        align-items:center; 
        justify-content:center; 
        gap:8px;
        font-size: 0.9rem; 
        font-family: inherit;
    }
    .join-button { background:linear-gradient(to right,var(--accent-red),var(--accent-blue)); color:#fff; }
    .replay-button { background:var(--bg-light); color:#f00; border:1px solid #ddd; }
    .replay-button i { font-size: 1.2rem; }
    .event-button:hover { transform:translateY(-3px); box-shadow:0 5px 15px rgba(0,0,0,.1); }
    .join-button.disabled { background:var(--disabled-bg); color:var(--disabled-color); cursor:not-allowed; border: 1px solid #ddd; }
    .join-button.disabled:hover { transform:none; box-shadow:none; }
    .now-badge { display:inline-block; background:var(--now-badge-bg); color:#fff; font-size:.7rem; font-weight:bold; padding:3px 8px; border-radius:12px; margin-left:10px; animation:pulse 1.5s infinite; }
    @keyframes pulse { 0%{opacity:.7} 50%{opacity:1} 100%{opacity:.7} }
    .happening-now { border:2px solid var(--highlight-border); background:var(--highlight-bg); box-shadow: 0 10px 30px rgba(255, 215, 0, 0.2); }
    
    .wait-button { background:#e0e0e0; color:#666; cursor:not-allowed; border: 1px solid #ccc; }
    .wait-button i { margin-right: 5px; }
    .fa-spin-slow { animation: fa-spin 3s infinite linear; }
    @keyframes fa-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(359deg); } }
    .no-events { text-align:center; padding:30px; background:#fff; border-radius:10px; box-shadow:var(--shadow); margin:20px 0; }
    
    .mobile-dropdown { display:flex; flex-direction:column; position:relative; width:100%; max-width:500px; margin:0 auto 20px; }
    .dropdown-button { display:flex; justify-content:space-between; align-items:center; padding:15px 20px; background:var(--accent-red); color:#fff; border:none; border-radius:25px; font-size:16px; font-weight:600; cursor:pointer; box-shadow:0 4px 10px rgba(227,29,28,.3); transition:all .3s; font-family: inherit; }
    .dropdown-content { display:none; position:absolute; top:calc(100% + 10px); left:0; width:100%; background:#fff; border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,.15); z-index:100; max-height:350px; overflow-y:auto; padding:8px 0; }
    .dropdown-content.show { display:block; animation:fadeInDown .3s ease-out; }
    @keyframes fadeInDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
    .language-button { display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border:none; border-bottom:1px solid #f0f0f0; width:100%; text-align:left; background:#fff; cursor:pointer; transition:background .2s; font-family: inherit; }
    .language-button:hover { background:#f5f5f5; }
    .language-info { display:flex; align-items:center; gap:10px; }
    .search-filter { padding:10px 15px; margin:5px 15px 10px; position:relative; }
    .search-input { width:100%; padding:10px 15px 10px 35px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; background:#f5f5f7; font-family: inherit; }
    .search-input:focus { border-color:var(--accent-red); outline:none; }
    .search-icon { position:absolute; left:25px; top:50%; transform:translateY(-50%); color:#888; font-size:14px; }
    .dropdown-flag-container { display:flex; align-items:center; gap:10px; }
    #selected-language-flag { width:24px; height:18px; border-radius:3px; }

    @media (max-width:768px) {
        .timeline::before { left:20px; }
        .timeline-event { width:calc(100% - 60px); margin-left:50px !important; }
        .timeline-event::before { left:-40px !important; right:auto !important; }
        .hero h1 { font-size:2.5rem; }
        .calendar-days { padding:0 10px; gap:8px; }
        .day-button { flex:1 1 calc(50% - 8px); min-width:0; padding:10px 5px; font-size:.9rem; }
    }
    @media (max-width:480px) {
        .hero h1 { font-size:2rem; }
        .calendar-days { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin:0 10px 20px; }
        .day-button { width:100%; font-size:.85rem; }
        .event-actions { flex-direction:column; }
        .event-button { width:100%; }
    }
<?php
$page_styles = ob_get_clean();

include 'includes/header.php';
?>

<main>
    <section class="hero">
        <div class="hero-content container">
            <h1>Encontro de Idiomas Online</h1>
            <p>Comunidade gratuita para praticar idiomas via videoconferência</p>
            <a href="#calendar" class="hero-button">Ver Programação</a>
        </div>
        <a href="#calendar" class="scroll-down"><i class="fas fa-chevron-down"></i></a>
    </section>

    <section id="calendar" class="calendar-section">
        <div class="container">
            <h2 class="section-title">Programação Semanal</h2>
            <p style="text-align:center;margin-bottom:2rem;">Fuso horário: GMT-3 (Horário de Brasília)<br>As chamadas são gravadas e disponibilizadas no YouTube.</p>

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
                    <p style="text-align:center;margin-bottom:10px;font-size:.9rem;font-weight:500;">Selecione um idioma:</p>
                    <button class="dropdown-button" id="lang-dropdown-btn">
                        <div class="dropdown-flag-container">
                            <img id="selected-language-flag" src="https://flagcdn.com/32x24/us.png" class="flag-icon" alt="Bandeira">
                            <span id="selected-language">Carregando...</span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content" id="lang-dropdown-content">
                        <div class="search-filter">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" id="language-search" placeholder="Buscar idioma...">
                        </div>
                        <?php foreach ($languages as $lang): ?>
                        <button class="language-button"
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
                    <div class="timeline" id="language-timeline"></div>
                </div>
            </div>

            <!-- VIEW: DIA DA SEMANA -->
            <div id="day-view" class="view-content <?= $initialView === 'day' ? 'active' : '' ?>">
                <div class="calendar-days">
                    <?php
                    $dayNames = [1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado',7=>'Domingo'];
                    foreach ($dayNames as $num => $name):
                        if (empty($byDay[$num])) continue;
                    ?>
                    <button class="day-button <?= $num == $initialDay ? 'active' : '' ?>"
                            data-day="<?= $num ?>"><?= $name ?></button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($dayNames as $dayNum => $dayName): ?>
                <div id="day-<?= $dayNum ?>" class="day-events <?= ($initialView === 'day' && $dayNum == $initialDay) ? 'active' : '' ?>">
                    <div class="timeline">
                    <?php
                    $dayEvents = $byDay[$dayNum] ?? [];
                    if (empty($dayEvents)):
                    ?>
                        <div class="no-events"><p>Nenhum evento neste dia ainda. Em breve!</p></div>
                    <?php else:
                        $currentHour = (int)date('G');
                        foreach ($dayEvents as $ev):
                            $evDay   = (int)$ev['day_of_week'];
                            $evHour  = (int)$ev['time_hour'];
                            
                            $isToday = ($currentDayOfWeek === $evDay);
                            $isNow   = $isToday && ($currentHour === $evHour);
                            $isPast  = ($evDay < $currentDayOfWeek) || ($isToday && $currentHour > $evHour);
                            $isFuture = ($evDay > $currentDayOfWeek) || ($isToday && $currentHour < $evHour);
                            
                            $flagCode  = $ev['flag_code'] ?? '';
                            $flagEmoji = $ev['flag_emoji'] ?? '';
                    ?>
                        <div class="timeline-event <?= $isNow ? 'happening-now' : '' ?>">
                            <span class="event-time"><?= $evHour ?>h</span>
                            <?php if ($isNow): ?>
                            <span class="now-badge">AO VIVO</span>
                            <?php endif; ?>
                            <div class="event-title">
                                <?php if ($flagCode): ?>
                                    <img src="https://flagcdn.com/32x24/<?= htmlspecialchars($flagCode) ?>.png" class="flag-icon" alt="<?= htmlspecialchars($ev['language_name']) ?>">
                                <?php elseif ($flagEmoji): ?>
                                    <span style="font-size:1.2rem;"><?= $flagEmoji ?></span>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($ev['language_name']) ?></span>
                                <div class="event-social-links">
                                    <?php if (!empty($ev['whatsapp_group_link'])): ?>
                                    <a href="<?= htmlspecialchars($ev['whatsapp_group_link']) ?>" target="_blank" class="social-icon whatsapp-icon" title="Grupo WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['instagram_link'])): ?>
                                    <a href="<?= htmlspecialchars($ev['instagram_link']) ?>" target="_blank" class="social-icon instagram-icon" title="Instagram"><i class="fab fa-instagram"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($ev['description'])): ?>
                            <p class="event-description"><?= htmlspecialchars($ev['description']) ?></p>
                            <?php endif; ?>
                            <div class="event-actions">
                                <?php if (!empty($ev['meet_link'])): ?>
                                    <?php if ($isNow): ?>
                                        <a href="<?= htmlspecialchars($ev['meet_link']) ?>" target="_blank" class="event-button join-button">Participar</a>
                                    <?php elseif ($isPast): ?>
                                        <div class="event-button join-button disabled"><i class="fa-solid fa-check"></i> Finalizado</div>
                                    <?php else: ?>
                                        <div class="event-button wait-button"><i class="fa-solid fa-clock fa-spin-slow"></i> Aguarde</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($ev['youtube_link'])): ?>
                                <a href="<?= htmlspecialchars($ev['youtube_link']) ?>" target="_blank" class="event-button replay-button">
                                    <i class="fa-solid fa-square-play"></i> Anteriores
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div><!-- /day-view -->

        </div>
    </section>
</main>

<?php
$page_scripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dayFilterBtn  = document.getElementById('day-filter-btn');
    const langFilterBtn = document.getElementById('language-filter-btn');
    const dayView       = document.getElementById('day-view');
    const langView      = document.getElementById('language-view');
    
    let currentView = '{$initialView}';
    let currentDay  = '{$initialDay}';
    let currentLang = '{$initialLang}';

    function updateURL() {
        const url = new URL(window.location);
        url.searchParams.set('view', currentView);
        if (currentView === 'day') {
            url.searchParams.set('dia', currentDay);
            url.searchParams.delete('idioma');
        } else {
            url.searchParams.set('idioma', currentLang);
            url.searchParams.delete('dia');
        }
        window.history.replaceState({}, '', url);
    }

    // Toggle views
    dayFilterBtn.addEventListener('click', function() {
        currentView = 'day';
        dayView.classList.add('active');    langView.classList.remove('active');
        dayFilterBtn.classList.add('active'); langFilterBtn.classList.remove('active');
        updateURL();
    });

    langFilterBtn.addEventListener('click', function() {
        currentView = 'language';
        langView.classList.add('active');   dayView.classList.remove('active');
        langFilterBtn.classList.add('active'); dayFilterBtn.classList.remove('active');
        
        // Se não tiver idioma selecionado, seleciona o primeiro
        if (!currentLang) {
            const first = document.querySelector('.language-button');
            if (first) first.click();
        } else {
            // Se já tem, garante que o botão está marcado como ativo
            const activeBtn = document.querySelector(`.language-button[data-language-id="\${currentLang}"]`);
            if (activeBtn) activeBtn.click();
            else document.querySelector('.language-button').click();
        }
        updateURL();
    });

    // Day buttons
    document.querySelectorAll('.day-button').forEach(btn => {
        btn.addEventListener('click', function() {
            currentDay = this.dataset.day;
            document.querySelectorAll('.day-button').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.day-events').forEach(d => d.classList.remove('active'));
            this.classList.add('active');
            const target = document.getElementById('day-' + currentDay);
            if (target) target.classList.add('active');
            updateURL();
        });
    });

    // Dropdown toggle
    const dropBtn     = document.getElementById('lang-dropdown-btn');
    const dropContent = document.getElementById('lang-dropdown-content');
    if (dropBtn) {
        dropBtn.addEventListener('click', () => dropContent.classList.toggle('show'));
    }
    document.addEventListener('click', e => {
        if (dropBtn && !dropBtn.contains(e.target) && !dropContent.contains(e.target))
            dropContent.classList.remove('show');
    });

    // Search filter
    const langSearch = document.getElementById('language-search');
    if (langSearch) {
        langSearch.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.language-button').forEach(btn => {
                btn.style.display = btn.dataset.language.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Language button click → AJAX
    document.querySelectorAll('.language-button').forEach(btn => {
        btn.addEventListener('click', function() {
            currentLang = this.dataset.languageId;
            const langName = this.dataset.language;
            const flagCode = this.dataset.flagCode;

            document.getElementById('selected-language').textContent = langName;
            const flagEl = document.getElementById('selected-language-flag');
            if (flagCode && flagEl) {
                flagEl.src = `https://flagcdn.com/32x24/\${flagCode}.png`;
                flagEl.style.display = '';
            }

            document.querySelectorAll('.language-button').forEach(b => b.classList.remove('active-lang'));
            this.classList.add('active-lang');
            dropContent.classList.remove('show');

            loadLanguageEvents(currentLang, langName, flagCode);
            updateURL();
        });
    });

    function loadLanguageEvents(langId, langName, flagCode) {
        const timeline = document.getElementById('language-timeline');
        timeline.innerHTML = '<p style="text-align:center;padding:30px;">Carregando...</p>';

        fetch(`ajax/get_language_events.php?language_id=\${langId}`)
            .then(r => r.json())
            .then(events => {
                if (!events.length) {
                    timeline.innerHTML = '<div class="no-events"><p>Nenhum evento para este idioma ainda.</p></div>';
                    return;
                }
                timeline.innerHTML = '';
                const currentDay = new Date().getDay() || 7; // 1-7
                const currentHour = new Date().getHours();

                events.forEach((ev, i) => {
                    const evDay = parseInt(ev.day_of_week);
                    const evHour = parseInt(ev.time_hour);
                    const isNow = (evDay === currentDay && currentHour === evHour);
                    const isPast = (evDay < currentDay || (evDay === currentDay && currentHour > evHour));
                    const isFuture = (evDay > currentDay || (evDay === currentDay && currentHour < evHour));
                    
                    const div = document.createElement('div');
                    div.className = 'timeline-event' + (isNow ? ' happening-now' : '');
                    const flagHtml = flagCode
                        ? `<img src="https://flagcdn.com/32x24/\${flagCode}.png" class="flag-icon" alt="\${langName}">`
                        : '';
                    
                    let actionButton = '';
                    if (ev.meet_link) {
                        if (isNow) {
                            actionButton = `<a href="\${ev.meet_link}" target="_blank" class="event-button join-button">Participar</a>`;
                        } else if (isPast) {
                            actionButton = `<div class="event-button join-button disabled"><i class="fa-solid fa-check" style="color:#28a745;"></i> Finalizado</div>`;
                        } else {
                            actionButton = `<div class="event-button wait-button"><i class="fa-solid fa-clock fa-spin-slow"></i> Aguarde</div>`;
                        }
                    }

                    div.innerHTML = `
                        <span class="event-time">\${evHour}h</span>
                        \${isNow ? '<span class="now-badge">AO VIVO</span>' : ''}
                        <div class="event-title">\${flagHtml}<span>\${langName}</span></div>
                        <p class="event-description">\${ev.description || ''}</p>
                        <div class="event-actions">
                            \${actionButton}
                            \${ev.youtube_link ? `<a href="\${ev.youtube_link}" target="_blank" class="event-button replay-button"><i class="fa-solid fa-square-play"></i> Anteriores</a>` : ''}
                        </div>`;
                    timeline.appendChild(div);
                });
            })
            .catch(() => {
                timeline.innerHTML = '<div class="no-events"><p>Erro ao carregar eventos.</p></div>';
            });
    }

    // Inicialização baseada no estado
    if (currentView === 'language' && currentLang) {
        const activeBtn = document.querySelector(`.language-button[data-language-id="\${currentLang}"]`);
        if (activeBtn) activeBtn.click();
    } else {
        const activeDayBtn = document.querySelector(`.day-button[data-day="\${currentDay}"]`);
        if (activeDayBtn) activeDayBtn.click();
        else {
            const firstDay = document.querySelector('.day-button');
            if (firstDay) firstDay.click();
        }
    }
});
</script>
JS;

include 'includes/footer.php';
?>
