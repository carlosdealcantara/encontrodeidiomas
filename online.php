<?php
// Get all events
require_once 'config.php';
$allEvents = getEvents();

// Organize events by day
$eventsByDay = [];
for ($i = 1; $i <= 7; $i++) {
    $eventsByDay[$i] = [];
}

// Group events by day
foreach ($allEvents as $event) {
    $eventsByDay[$event['day_of_week']][] = $event;
}

// Count events per language
$languageCounts = [];
$languages = getLanguages();
foreach ($languages as $language) {
    $languageCounts[$language['id']] = 0;
}

foreach ($allEvents as $event) {
    if (isset($languageCounts[$event['language_id']])) {
        $languageCounts[$event['language_id']]++;
    }
}

// Get current day of week (1-7, Monday is 1)
$currentDayOfWeek = date('N');

// Day names mapping
$dayNames = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo'
];

$title = "Encontros Online";

// Additional styles for this page
$page_styles = <<<EOT
body {
    color: var(--text-color);
    background-color: #f7f7f7;
    line-height: 1.6;
    overflow-x: hidden;
    width: 100%;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
    will-change: scroll-position;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
}

.header {
    background: var(--primary-color);
    color: var(--white);
    padding: 1rem 0;
    position: fixed;
    width: 100%;
    z-index: 1000;
    top: 0;
    left: 0;
    transform: translateZ(0);
    will-change: transform;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.main-content {
    padding-top: 120px;
    padding-bottom: 60px;
}

.page-title {
    text-align: center;
    margin-bottom: 20px;
}

.page-title h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.page-title p {
    font-size: 1.1rem;
    color: #666;
    max-width: 800px;
    margin: 0 auto;
}

/* Dropdown Estilo para Mobile */
.mobile-dropdown {
    display: flex;
    flex-direction: column;
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto 20px;
}

@media (min-width: 769px) {
    .mobile-dropdown {
        max-width: 400px;
    }
}

.dropdown-button {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: var(--accent-red);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    z-index: 10;
}

.dropdown-button:hover {
    background-color: #c51919;
}

.dropdown-flag-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dropdown-content {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    padding: 15px;
    z-index: 100;
    margin-top: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.dropdown-content.show {
    display: block;
    animation: fadeIn 0.3s ease;
}

.search-filter {
    display: flex;
    align-items: center;
    background-color: #f5f5f5;
    border-radius: 8px;
    padding: 10px 15px;
    margin-bottom: 15px;
}

.search-icon {
    color: #777;
    margin-right: 10px;
}

.search-input {
    border: none;
    background: transparent;
    width: 100%;
    font-size: 1rem;
    color: #333;
    outline: none;
}

.no-results {
    display: none;
    text-align: center;
    padding: 15px;
    color: #666;
    font-style: italic;
}

.language-button {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    text-align: left;
    padding: 12px 15px;
    background: none;
    border: none;
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.language-button:hover {
    background-color: #f8f8f8;
}

.language-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.flag-icon {
    width: 24px;
    height: auto;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.language-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #f0f0f0;
    color: #666;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Seletor de dias da semana */
.days-selector {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin: 30px auto;
    flex-wrap: wrap;
    max-width: 800px;
}

.day-button {
    padding: 10px 15px;
    background-color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.day-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.day-button.active {
    background-color: var(--accent-red);
    color: white;
}

/* Events Section */
.day-events {
    display: none;
    animation: fadeIn 0.5s ease;
}

.day-events.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.timeline {
    position: relative;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 100%;
    background: linear-gradient(to bottom, var(--accent-red), var(--accent-blue));
    border-radius: 2px;
}

.timeline-event {
    position: relative;
    margin-bottom: 2rem;
    width: 45%;
    background-color: var(--card-bg);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 20px;
    transition: var(--transition);
    cursor: pointer;
    transform: translateZ(0);
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
    will-change: transform;
}

.timeline-event:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.timeline-event:nth-child(odd) {
    margin-left: auto;
}

.timeline-event::before {
    content: '';
    position: absolute;
    top: 20px;
    width: 20px;
    height: 20px;
    background-color: var(--white);
    border: 4px solid var(--accent-red);
    border-radius: 50%;
}

.timeline-event:nth-child(odd)::before {
    left: -60px;
}

.timeline-event:nth-child(even)::before {
    right: -60px;
}

.day-info {
    display: inline-block;
    background-color: var(--accent-blue);
    color: var(--white);
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 500;
    margin-bottom: 10px;
}

.event-time {
    display: inline-block;
    background-color: var(--accent-blue);
    color: var(--white);
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 500;
    margin-bottom: 10px;
}

.event-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.event-social-links {
    display: flex;
    gap: 5px;
    margin-left: 10px;
}

.social-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background-color: #f0f2f5;
    color: var(--text-color);
    border: 1px solid #ddd;
}

.social-icon:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    background-color: #e6e9ec;
}

.whatsapp-icon:hover {
    color: #25D366;
}

.instagram-icon:hover {
    color: #E1306C;
}

.event-description {
    color: #666;
    margin-bottom: 15px;
    line-height: 1.5;
}

.event-actions {
    display: flex;
    gap: 10px;
}

.event-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.join-button {
    background-color: var(--accent-red);
    color: white;
}

.join-button:hover {
    background-color: #c51919;
    transform: translateY(-2px);
}

.replay-button {
    background-color: #f0f2f5;
    color: var(--text-color);
}

.replay-button:hover {
    background-color: #e6e9ec;
    transform: translateY(-2px);
}

.replay-button i {
    color: #ff0000;
    margin-right: 5px;
}

.now-badge {
    display: inline-block;
    background-color: var(--now-badge-bg);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-left: 10px;
    animation: pulse 1.5s infinite;
}

.join-button.disabled {
    background-color: var(--disabled-bg);
    color: var(--disabled-color);
    cursor: not-allowed;
    pointer-events: none;
}

.no-events {
    text-align: center;
    padding: 30px;
    color: #666;
    background-color: var(--card-bg);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    margin: 20px auto;
    max-width: 600px;
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1);
    }
}

/* Highlighted current day */
.day-button.current-day {
    position: relative;
    overflow: hidden;
}

.day-button.current-day::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background-color: var(--accent-yellow);
}

/* Language specific events */
#language-events {
    margin-top: 30px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .timeline::before {
        left: 20px;
    }
    
    .timeline-event {
        width: calc(100% - 60px);
        margin-left: 60px !important;
    }
    
    .timeline-event::before {
        left: -40px !important;
    }
    
    .days-selector {
        flex-wrap: nowrap;
        overflow-x: auto;
        justify-content: flex-start;
        padding-bottom: 15px;
        -webkit-overflow-scrolling: touch;
    }
    
    .day-button {
        flex: 0 0 auto;
    }
}
EOT;

// Additional scripts for this page
$extra_head = <<<EOT
<meta property="og:title" content="Encontros Online - Encontro de Idiomas">
<meta property="og:description" content="Encontro de Idiomas Online - Comunidade gratuita para praticar idiomas via videoconferência. Participe de encontros semanais de diversos idiomas.">
<meta property="og:image" content="https://encontrodeidiomas.com.br/assets/images/og_image.png">
<meta property="og:url" content="https://encontrodeidiomas.com.br/online.php">
<meta property="twitter:title" content="Encontros Online - Encontro de Idiomas">
<meta property="twitter:description" content="Encontro de Idiomas Online - Comunidade gratuita para praticar idiomas via videoconferência. Participe de encontros semanais de diversos idiomas.">
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment-with-locales.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.43/moment-timezone-with-data.min.js"></script>
EOT;

// Define page_scripts to be included at the end
$page_scripts = <<<EOT
<script src="assets/js/online.php.js"></script>
EOT;

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1>Encontros Online</h1>
        <p>Participe de videoconferências gratuitas para praticar diversos idiomas com outros estudantes e poliglotas. Encontros semanais em vários horários.</p>
        <a href="#" class="hero-button">Ver Programação</a>
    </div>
    <a href="#" class="scroll-down">
        <i class="fas fa-chevron-down"></i>
    </a>
</section>

<style>
    /* Hero Section */
    .hero {
        min-height: 70vh;
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
        margin-top: 80px; /* account for fixed header */
    }
    
    .hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: url('assets/images/encontrodeidiomas-20250407-0002.jpg') no-repeat center center/cover;
        opacity: 0.2;
        z-index: 0;
    }
    
    .hero-content {
        position: relative;
        z-index: 1;
        max-width: 800px;
        animation: fadeUp 1s ease;
        padding: 0 20px;
        text-align: center;
        margin: 0 auto;
    }
    
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .hero h1 {
        font-size: 4rem;
        font-weight: 700;
        margin-bottom: 1rem;
        line-height: 1.2;
        background: linear-gradient(to right, var(--accent-red), var(--accent-yellow));
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        text-align: center;
    }
    
    .hero p {
        font-size: 1.2rem;
        margin-bottom: 2rem;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        text-align: center;
    }
    
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
        transition: var(--transition);
        box-shadow: 0 5px 15px rgba(227, 29, 28, 0.4);
    }
    
    .hero-button:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 25px rgba(227, 29, 28, 0.5);
    }
    
    .scroll-down {
        position: absolute;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        animation: bounce 2s infinite;
        cursor: pointer;
        color: var(--white);
        font-size: 2rem;
    }
    
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% { transform: translateY(0) translateX(-50%); }
        40% { transform: translateY(-20px) translateX(-50%); }
        60% { transform: translateY(-10px) translateX(-50%); }
    }
</style>

<div class="main-content">
    <div class="container">
        <div class="page-title">
            <h1>Encontros Online</h1>
            <p>Videoconferências gratuitas para praticar diversos idiomas. Conheça nossa programação semanal e participe dos encontros.</p>
        </div>
        
        <div class="days-selector">
            <?php for ($i = 1; $i <= 7; $i++): ?>
                <button class="day-button <?= $i == $currentDayOfWeek ? 'active current-day' : '' ?>" data-day="<?= $i ?>">
                    <?= substr($dayNames[$i], 0, 3) ?>
                </button>
            <?php endfor; ?>
        </div>
        
        <?php for ($i = 1; $i <= 7; $i++): ?>
            <div id="day-<?= $i ?>" class="day-events <?= $i == $currentDayOfWeek ? 'active' : '' ?>">
                <div class="timeline">
                    <?php if (empty($eventsByDay[$i])): ?>
                        <div class="no-events">
                            Não há encontros agendados para <?= $dayNames[$i] ?>.
                        </div>
                    <?php else: ?>
                        <?php foreach ($eventsByDay[$i] as $event): ?>
                            <div class="timeline-event" data-time="<?= $event['time_hour'] ?>" data-language="<?= strtolower($event['language_name']) ?>">
                                <span class="event-time"><?= $event['time_hour'] ?>h</span>
                                <div class="event-title">
                                    <?php if (!empty($event['flag_emoji'])): ?>
                                        <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;"><?= $event['flag_emoji'] ?></span>
                                    <?php elseif (!empty($event['flag_code'])): ?>
                                        <img src="https://flagcdn.com/32x24/<?= strtolower($event['flag_code']) ?>.png" class="flag-icon" alt="<?= $event['language_name'] ?>">
                                    <?php endif; ?>
                                    <span><?= $event['language_name'] ?></span>
                                    <div class="event-social-links">
                                        <?php if (!empty($event['whatsapp_group_link'])): ?>
                                            <a href="<?= $event['whatsapp_group_link'] ?>" target="_blank" class="social-icon whatsapp-icon" title="Grupo de <?= $event['language_name'] ?>">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($event['instagram_link'])): ?>
                                            <a href="<?= $event['instagram_link'] ?>" target="_blank" class="social-icon instagram-icon" title="Perfil de <?= $event['language_name'] ?>">
                                                <i class="fab fa-instagram"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="event-description"><?= $event['description'] ?></p>
                                <div class="event-actions">
                                    <?php if (!empty($event['meet_link'])): ?>
                                        <a href="<?= $event['meet_link'] ?>" target="_blank" class="event-button join-button" data-day="<?= $i ?>" data-time="<?= $event['time_hour'] ?>">Participar</a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($event['youtube_link'])): ?>
                                        <a href="<?= $event['youtube_link'] ?>" target="_blank" class="event-button replay-button">
                                            <i class="fab fa-youtube"></i> Anteriores
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
        
        <div class="mobile-dropdown">
            <p style="text-align: center; margin-bottom: 10px; font-size: 0.9rem; font-weight: 500;">Selecione um idioma:</p>
            <button class="dropdown-button">
                <div class="dropdown-flag-container">
                    <img id="selected-language-flag" src="https://flagcdn.com/32x24/us.png" class="flag-icon" alt="EUA">
                    <span id="selected-language">Inglês</span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-content">
                <div class="search-filter">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="language-search" placeholder="Buscar idioma...">
                </div>
                <div class="no-results" id="no-results">
                    Nenhum idioma encontrado.
                </div>
                
                <?php foreach ($languages as $language): ?>
                    <button class="language-button" data-language="<?= strtolower($language['name']) ?>" data-language-id="<?= $language['id'] ?>">
                        <div class="language-info">
                            <?php if (!empty($language['flag_emoji'])): ?>
                                <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;"><?= $language['flag_emoji'] ?></span>
                            <?php elseif (!empty($language['flag_code'])): ?>
                                <img src="https://flagcdn.com/32x24/<?= strtolower($language['flag_code']) ?>.png" class="flag-icon" alt="<?= $language['name'] ?>">
                            <?php endif; ?>
                            <span><?= $language['name'] ?></span>
                        </div>
                        <span class="language-badge"><?= $languageCounts[$language['id']] ?? 0 ?></span>
                    </button>
                <?php endforeach; ?>
                
                <button class="language-button" data-language="seu">
                    <div class="language-info">
                        <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🚩</span>
                        <span>Seu idioma aqui!</span>
                    </div>
                    <span class="language-badge">+</span>
                </button>
            </div>
        </div>
        
        <div id="language-events">
            <div class="timeline">
                <!-- Events will be displayed here -->
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>