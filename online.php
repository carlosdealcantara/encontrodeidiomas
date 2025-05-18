<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'config.php';

// Get all events from database
$allEvents = getEvents();

// Get all languages
$languages = getLanguages();

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

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    width: 100%;
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
    border-radius: 25px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(227, 29, 28, 0.3);
    transition: all 0.3s;
}

.dropdown-button:hover {
    background-color: #c01a19;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(227, 29, 28, 0.4);
}

.dropdown-flag-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dropdown-content {
    display: none;
    position: absolute;
    top: calc(100% + 10px); /* Posicionamento abaixo do botão com espaço */
    left: 0;
    width: 100%;
    background-color: white;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    z-index: 100;
    max-height: 350px;
    overflow-y: auto;
    padding: 8px 0;
}

.dropdown-content.show {
    display: block;
    animation: fadeInDown 0.3s ease-out;
}

/* Barra de pesquisa no dropdown */
.search-filter {
    padding: 10px 15px;
    margin: 5px 15px 10px;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 10px 15px 10px 35px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    background-color: #f5f5f7;
    transition: all 0.3s;
}

.search-input:focus {
    border-color: var(--accent-red);
    background-color: white;
    box-shadow: 0 0 0 2px rgba(227, 29, 28, 0.1);
    outline: none;
}

.search-icon {
    position: absolute;
    left: 25px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
    font-size: 14px;
}

.no-results {
    display: none;
    text-align: center;
    padding: 15px;
    color: #666;
    font-style: italic;
    font-size: 14px;
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

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-content .language-button {
    border-radius: 0;
    margin: 0;
    border: none;
    border-bottom: 1px solid #f0f0f0;
    width: 100%;
    text-align: left;
    padding: 12px 20px;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dropdown-content .language-button:hover {
    background-color: #f5f5f5;
    transform: none;
    box-shadow: none;
}

.dropdown-content .language-button.active {
    background-color: rgba(227, 29, 28, 0.05);
    color: var(--accent-red);
    font-weight: 700;
}

.dropdown-content .language-button:last-child {
    border-bottom: none;
    border-bottom-left-radius: 15px;
    border-bottom-right-radius: 15px;
}

.language-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.language-badge {
    display: none; /* Esconder os badges por enquanto */
    background-color: #f0f0f0;
    color: #666;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 500;
    min-width: 20px;
    text-align: center;
}

/* Esconder a lista horizontal de idiomas em todas as resoluções */
.language-filters {
    display: none !important;
    visibility: hidden;
    height: 0;
    overflow: hidden;
    margin: 0;
    padding: 0;
}

/* Hero Section */
.hero {
    min-height: 70vh; /* Reduzindo de 100vh para 70vh para não ocupar a tela inteira */
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
    text-align: center; /* Garantindo que o conteúdo esteja centralizado */
    margin: 0 auto; /* Centralizando o bloco de conteúdo */
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
    background-clip: text; /* Standard property */
    -webkit-background-clip: text; /* Vendor prefix for WebKit browsers */
    -webkit-text-fill-color: transparent;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    text-align: center; /* Garantindo alinhamento centralizado */
}

.hero p {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    max-width: 600px;
    margin-left: auto; /* Centralizando o parágrafo */
    margin-right: auto; /* Centralizando o parágrafo */
    text-align: center; /* Garantindo alinhamento centralizado */
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

/* Calendar Section */
.calendar-section {
    padding: 5rem 0;
    background-color: var(--white);
}

.section-title {
    text-align: center;
    margin-bottom: 30px;
}

.section-description {
    text-align: center;
    max-width: 800px;
    margin: 0 auto 40px;
    color: #666;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: linear-gradient(to right, var(--accent-red), var(--accent-blue));
    border-radius: 2px;
}

/* Calendar Component */
.calendar-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 30px;
}

.calendar-nav-title {
    font-size: 1.2rem;
    font-weight: 600;
}

.view-toggle {
    display: flex;
    gap: 10px;
    background-color: var(--white);
    border-radius: 30px;
    padding: 5px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    margin-left: 15px;
}

.view-button {
    background: none;
    border: none;
    color: var(--text-color);
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    padding: 8px 15px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.view-button.active {
    color: var(--white);
    background-color: var(--accent-red);
}

.view-button:hover:not(.active) {
    background-color: #f0f0f0;
}

.view-content {
    display: none;
}

.view-content.active {
    display: block;
}

.calendar-days {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 30px;
}

.day-button {
    padding: 10px 16px;
    background-color: white;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    min-width: 120px;
    text-align: center;
}

.day-button:hover {
    transform: translateY(-3px);
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

/* Responsive */
@media (max-width: 768px) {
    .timeline::before {
        left: 20px;
    }
    
    .timeline-event {
        width: 90%;
        margin-left: 50px !important;
    }
    
    .timeline-event::before {
        left: -30px !important;
        right: auto !important;
    }
    
    .calendar-days {
        padding: 0 10px;
        gap: 8px;
    }
    
    .day-button {
        flex: 1 1 calc(50% - 8px);
        min-width: 0;
        padding: 10px 5px;
        font-size: 0.9rem;
    }
    
    .event-title {
        font-size: 1.1rem;
        flex-wrap: wrap;
    }
    
    .event-social-links {
        margin-left: 0;
        margin-top: 8px;
        width: 100%;
        justify-content: flex-start;
    }
    
    .header-content {
        padding: 0 15px;
    }
    
    .logo {
        width: 50px;
        height: 50px;
    }
    
    .site-title {
        font-size: 1.2rem;
    }
    
    .site-description {
        font-size: 0.8rem;
    }
    
    .nav-links {
        gap: 10px;
    }
    
    .nav-links a {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    
    .hero h1 {
        font-size: 2.5rem;
    }
    
    .hero p {
        font-size: 1rem;
    }
    
    .event-title {
        flex-wrap: wrap;
    }
    
    .event-social-links {
        margin-left: 5px;
        margin-top: 5px;
    }
}

/* Mobile devices específica para telas muito pequenas */
@media (max-width: 480px) {
    .header-content {
        justify-content: center;
        padding: 10px;
    }
    
    .logo-container {
        margin-bottom: 10px;
        justify-content: center;
        width: 100%;
    }
    
    .nav-links {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .nav-links a {
        padding: 5px 10px;
        font-size: 0.85rem;
    }
    
    .hero {
        margin-top: 120px; /* Ajustado para o cabeçalho maior em mobile */
    }
    
    .hero h1 {
        font-size: 2rem;
    }
    
    .hero p {
        font-size: 0.9rem;
    }
    
    .hero-button {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
    
    .calendar-days {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        margin: 0 20px 20px;
    }
    
    .day-button {
        width: 100%;
        padding: 12px 10px;
    }
    
    .event-title {
        font-size: 1.1rem;
        flex-wrap: wrap;
    }
    
    .event-social-links {
        margin-left: 0;
        margin-top: 8px;
        width: 100%;
        justify-content: flex-start;
    }
    
    .social-icon {
        width: 20px;
        height: 20px;
        font-size: 0.65rem;
    }
    
    .event-actions {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .event-button {
        width: 100%;
        text-align: center;
    }
}

footer {
    background-color: #222;
    color: var(--white);
    text-align: center;
    padding: 30px 0 20px;
    margin-top: 0;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.social-links {
    margin-bottom: 20px;
}

.social-links a {
    color: white;
    font-size: 1.5rem;
    margin: 0 10px;
    text-decoration: none;
    transition: opacity 0.3s ease;
}

.social-links a:hover {
    opacity: 0.8;
}

.now-badge {
    display: inline-block;
    background-color: var(--now-badge-bg);
    color: var(--white);
    font-size: 0.7rem;
    font-weight: bold;
    padding: 3px 8px;
    border-radius: 12px;
    margin-left: 10px;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { opacity: 0.7; }
    50% { opacity: 1; }
    100% { opacity: 0.7; }
}

.happening-now {
    border: 2px solid var(--highlight-border);
    background-color: var(--highlight-bg);
    box-shadow: 0 10px 30px rgba(255, 215, 0, 0.15);
}

.join-button.disabled {
    background: var(--disabled-bg);
    color: var(--disabled-color);
    cursor: not-allowed;
    box-shadow: none;
}

.join-button.disabled:hover {
    transform: none;
    box-shadow: none;
}

.wait-icon {
    margin-right: 5px;
    animation: rotate 2s linear infinite;
}

.check-icon {
    margin-right: 5px;
    color: #4caf50;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.tooltip {
    position: absolute;
    top: -40px;
    left: 50%;
    transform: translateX(-50%);
    background-color: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.75rem;
    z-index: 100;
    pointer-events: none;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: #333 transparent transparent transparent;
}

/* White space between sections */
.white-space {
    background-color: #f7f7f7;
    padding: 40px 0;
}

/* Impede qualquer overflow horizontal em qualquer elemento */
html, body {
    max-width: 100%;
    overflow-x: hidden;
    position: relative;
}

.dropdown-flag-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

#selected-language-flag {
    width: 24px;
    height: 18px;
    border-radius: 3px;
}

/* Filter Toggle Buttons */
.filter-toggle {
    display: inline-flex;
    align-items: center;
    background: #f5f5f5;
    border-radius: 30px;
    padding: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.filter-button {
    border: none;
    border-radius: 30px;
    padding: 10px 20px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.filter-button.active {
    background-color: #e31d1c;
    color: white;
}

.filter-button:not(.active) {
    background: none;
    color: #333;
}

.filter-button:hover:not(.active) {
    background-color: rgba(0,0,0,0.05);
}

/* Card style for events */
.timeline-event {
    background-color: #fff;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.timeline-event:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.day-info {
    display: inline-block;
    background-color: #002654;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.now-badge {
    display: inline-block;
    background-color: #e31d1c;
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: bold;
    margin-left: 10px;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { opacity: 0.7; }
    50% { opacity: 1; }
    100% { opacity: 0.7; }
}

/* Updated day buttons */
.calendar-days {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 30px;
}

.day-button {
    background-color: white;
    border: none;
    border-radius: 25px;
    padding: 12px 20px;
    font-weight: 600;
    cursor: pointer;
    min-width: 120px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.day-button.active {
    background-color: #e31d1c;
    color: white;
}

.day-button:hover:not(.active) {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}

/* Responsive updates */
@media (max-width: 768px) {
    .calendar-days {
        flex-wrap: wrap;
    }
    
    .day-button {
        flex: 1 1 calc(33.333% - 10px);
        min-width: 0;
        padding: 10px;
    }
    
    .filter-toggle {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .day-button {
        flex: 1 1 calc(50% - 5px);
    }
    
    .filter-button {
        padding: 8px 15px;
        font-size: 0.9rem;
    }
}
EOT;

// Additional scripts for this page
$extra_head = <<<EOT
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment-with-locales.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.43/moment-timezone-with-data.min.js"></script>
EOT;

$page_scripts = <<<EOT
<script src="assets/js/online.js"></script>
EOT;

include 'includes/header.php';
?>

<div class="main-content">
    <div class="container">
        <div class="page-title">
            <h1>Encontros Online</h1>
            <p>Videoconferências gratuitas para praticar diversos idiomas. Conheça nossa programação semanal e participe dos encontros.</p>
        </div>
        
        <!-- Filter Toggle -->
        <div style="text-align: center; margin-bottom: 20px;">
            <div class="filter-toggle">
                <p style="margin: 0 15px 0 0; font-weight: 500;">Filtre por:</p>
                <button id="day-filter-btn" class="filter-button active">Dia da Semana</button>
                <button id="language-filter-btn" class="filter-button">Idioma</button>
            </div>
        </div>
        
        <!-- Day View (Default Active) -->
        <div id="day-view" class="filter-view active">
            <div class="calendar-days">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button class="day-button <?= $i == $currentDayOfWeek ? 'active' : '' ?>" data-day="<?= $i ?>">
                        <?= getDayName($i) ?>
                    </button>
                <?php endfor; ?>
            </div>
            
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <div id="day-<?= $i ?>" class="day-events <?= $i == $currentDayOfWeek ? 'active' : '' ?>">
                    <div class="timeline">
                        <?php if (empty($eventsByDay[$i])): ?>
                            <div class="no-events">
                                <p>Não há eventos programados para <?= getDayName($i) ?></p>
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
                                        <span><?= !empty($event['title']) ? $event['title'] : $event['language_name'] ?></span>
                                        
                                        <?php 
                                        // Check if event is happening now
                                        $currentHour = (int)date('G');
                                        $currentDay = (int)date('N');
                                        $isNow = ($currentDay == $i && $currentHour == $event['time_hour']);
                                        ?>
                                        
                                        <?php if ($isNow): ?>
                                            <span class="now-badge">AGORA</span>
                                        <?php endif; ?>
                                        
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
                                    <p class="event-description">
                                    <?php 
                                    // Just use the description field
                                    if (!empty($event['description'])) {
                                        echo $event['description'];
                                    } else {
                                        echo '';
                                    }
                                    ?>
                                    </p>
                                    <div class="event-actions">
                                        <?php if (!empty($event['meet_link'])): ?>
                                            <a href="<?= $event['meet_link'] ?>" target="_blank" class="event-button join-button <?= $isNow ? '' : ($currentDay == $i && $currentHour > $event['time_hour'] ? 'disabled' : '') ?>">
                                                <?php if ($isNow): ?>
                                                    Participar
                                                <?php elseif ($currentDay == $i && $currentHour > $event['time_hour']): ?>
                                                    <i class="fas fa-check check-icon"></i> Finalizado
                                                <?php else: ?>
                                                    Participar
                                                <?php endif; ?>
                                            </a>
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
        </div>
        
        <!-- Language View (Initially Hidden) -->
        <div id="language-view" class="filter-view" style="display:none;">
            <!-- Language Selection Dropdown -->
            <div style="margin-bottom: 30px; text-align: center;">
                <p style="margin-bottom: 10px; font-weight: 500;">Selecione um idioma:</p>
                <div style="max-width: 400px; margin: 0 auto; position: relative;">
                    <button id="language-dropdown-btn" style="width: 100%; justify-content: space-between; background-color: #e31d1c; color: white; border: none; border-radius: 30px; padding: 12px 20px; cursor: pointer; display: flex; align-items: center; box-shadow: 0 4px 10px rgba(227,29,28,0.3);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img id="selected-language-flag" src="https://flagcdn.com/32x24/us.png" style="width: 24px; height: 18px; border-radius: 3px;" alt="EUA">
                            <span id="selected-language">Inglês</span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="language-dropdown" style="display: none; position: absolute; top: 100%; left: 0; width: 100%; background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); margin-top: 10px; z-index: 100; max-height: 300px; overflow-y: auto;">
                        <div style="padding: 10px 15px; border-bottom: 1px solid #eee;">
                            <div style="position: relative;">
                                <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                                <input type="text" id="language-search" placeholder="Buscar idioma..." style="width: 100%; padding: 8px 10px 8px 30px; border: 1px solid #ddd; border-radius: 20px; outline: none;">
                            </div>
                        </div>
                        <div id="language-list">
                            <?php foreach ($languages as $index => $language): ?>
                                <button class="language-option <?= $index === 0 ? 'active' : '' ?>" data-language-id="<?= $language['id'] ?>" data-language="<?= $language['name'] ?>" style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 12px 15px; border: none; background: <?= $index === 0 ? 'rgba(227, 29, 28, 0.05)' : 'white' ?>; text-align: left; cursor: pointer; border-bottom: 1px solid #eee;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if (!empty($language['flag_emoji'])): ?>
                                            <span style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center;"><?= $language['flag_emoji'] ?></span>
                                        <?php elseif (!empty($language['flag_code'])): ?>
                                            <img src="https://flagcdn.com/32x24/<?= $language['flag_code'] ?>.png" style="width: 24px; height: 18px; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" alt="<?= $language['name'] ?>">
                                        <?php else: ?>
                                            <span style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center;">🌍</span>
                                        <?php endif; ?>
                                        <span><?= $language['name'] ?></span>
                                    </div>
                                    <?php if (isset($languageCounts[$language['id']]) && $languageCounts[$language['id']] > 0): ?>
                                        <span style="background: #f0f0f0; color: #666; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;"><?= $languageCounts[$language['id']] ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Language Events Timeline -->
            <div id="language-events-container">
                <!-- Will be populated via AJAX -->
            </div>
        </div>
    </div>
</div>

<footer>
    <div class="footer-content">
        <div class="social-links">
            <a href="https://instagram.com/encontrodeidiomas" target="_blank"><i class="fab fa-instagram"></i></a>
            <a href="https://youtube.com/@encontrodeidiomas" target="_blank"><i class="fab fa-youtube"></i></a>
            <a href="https://chat.whatsapp.com/KggtrgpvUAU0ayJzJinIGq" target="_blank"><i class="fab fa-whatsapp"></i></a>
            <a href="mailto:contato@encontrodeidiomas.com.br"><i class="far fa-envelope"></i></a>
        </div>
        <p>&copy; <?= date('Y') ?> Encontro de Idiomas. Todos os direitos reservados.</p>
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filter toggle functionality
        const dayFilterBtn = document.getElementById('day-filter-btn');
        const languageFilterBtn = document.getElementById('language-filter-btn');
        const dayView = document.getElementById('day-view');
        const languageView = document.getElementById('language-view');
        
        // Day filter button click event
        dayFilterBtn.addEventListener('click', function() {
            dayFilterBtn.classList.add('active');
            languageFilterBtn.classList.remove('active');
            languageFilterBtn.style.background = 'none';
            languageFilterBtn.style.color = '#333';
            dayFilterBtn.style.backgroundColor = '#e31d1c';
            dayFilterBtn.style.color = 'white';
            
            dayView.style.display = 'block';
            languageView.style.display = 'none';
            
            // Update URL parameter for view type
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('view', 'day');
            urlParams.delete('language_id'); // Remove language filter when switching to day view
            history.pushState(null, '', urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname);
        });
        
        // Language filter button click event
        languageFilterBtn.addEventListener('click', function() {
            languageFilterBtn.classList.add('active');
            dayFilterBtn.classList.remove('active');
            dayFilterBtn.style.background = 'none';
            dayFilterBtn.style.color = '#333';
            languageFilterBtn.style.backgroundColor = '#e31d1c';
            languageFilterBtn.style.color = 'white';
            
            languageView.style.display = 'block';
            dayView.style.display = 'none';
            
            // Update URL parameter for view type
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('view', 'language');
            urlParams.delete('day'); // Remove day filter when switching to language view
            history.pushState(null, '', urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname);
            
            // Load the default language (English) events if not already loaded
            if (document.getElementById('language-events-container').innerHTML === '') {
                const defaultLanguageButton = document.querySelector('.language-option[data-language="Inglês"]') || 
                                             document.querySelector('.language-option[data-language="English"]') || 
                                             document.querySelector('.language-option:first-child');
                
                if (defaultLanguageButton) {
                    // Simulate click on default language button to load events
                    const languageId = defaultLanguageButton.getAttribute('data-language-id');
                    loadLanguageEvents(languageId, defaultLanguageButton.querySelector('span:last-child').textContent);
                }
            }
        });
        
        // Select current day by default
        const currentDayOfWeek = <?= $currentDayOfWeek ?>; // PHP variable with current day (1-7)
        const currentDayButton = document.querySelector(`.day-button[data-day="${currentDayOfWeek}"]`);
        if (currentDayButton) {
            currentDayButton.click();
        }
        
        // Language dropdown functionality
        const languageDropdownBtn = document.getElementById('language-dropdown-btn');
        const languageDropdown = document.getElementById('language-dropdown');
        const languageOptions = document.querySelectorAll('.language-option');
        const languageSearch = document.getElementById('language-search');
        
        // Toggle dropdown
        languageDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            languageDropdown.style.display = languageDropdown.style.display === 'none' ? 'block' : 'none';
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#language-dropdown') && !e.target.closest('#language-dropdown-btn')) {
                languageDropdown.style.display = 'none';
            }
        });
        
        // Search functionality
        languageSearch.addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            let hasResults = false;
            
            languageOptions.forEach(option => {
                const languageName = option.getAttribute('data-language').toLowerCase();
                if (languageName.includes(searchValue)) {
                    option.style.display = 'flex';
                    hasResults = true;
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Show no results message if needed
            const noResultsMsg = document.getElementById('no-results-msg');
            if (!hasResults) {
                if (!noResultsMsg) {
                    const msg = document.createElement('div');
                    msg.id = 'no-results-msg';
                    msg.style.padding = '15px';
                    msg.style.textAlign = 'center';
                    msg.style.color = '#666';
                    msg.textContent = 'Nenhum idioma encontrado.';
                    document.getElementById('language-list').appendChild(msg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });
        
        // Language selection
        languageOptions.forEach(option => {
            option.addEventListener('click', function() {
                const languageId = this.getAttribute('data-language-id');
                const languageName = this.getAttribute('data-language');
                const flagElem = this.querySelector('img, span:first-child');
                
                // Update selected language UI
                document.getElementById('selected-language').textContent = languageName;
                
                if (flagElem.tagName === 'IMG') {
                    document.getElementById('selected-language-flag').src = flagElem.src;
                    document.getElementById('selected-language-flag').style.display = 'inline-block';
                } else {
                    // Handle emoji flags
                    document.getElementById('selected-language-flag').style.display = 'none';
                }
                
                // Mark this option as active
                languageOptions.forEach(opt => {
                    opt.classList.remove('active');
                    opt.style.background = 'white';
                });
                this.classList.add('active');
                this.style.background = 'rgba(227, 29, 28, 0.05)';
                
                // Load events for this language
                loadLanguageEvents(languageId, languageName);
                
                // Update URL parameter for language
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('language_id', languageId);
                urlParams.set('language_name', languageName);
                history.pushState(null, '', urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname);
                
                // Close dropdown
                languageDropdown.style.display = 'none';
            });
        });
        
        // Day buttons functionality
        const dayButtons = document.querySelectorAll('.day-button');
        const dayEvents = document.querySelectorAll('.day-events');
        
        dayButtons.forEach(button => {
            button.addEventListener('click', function() {
                const day = this.getAttribute('data-day');
                
                // Toggle button active state
                dayButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Toggle day events
                dayEvents.forEach(events => events.classList.remove('active'));
                document.getElementById('day-' + day).classList.add('active');
                
                // Update URL parameter for day
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('day', day);
                history.pushState(null, '', urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname);
            });
        });
        
        // Function to load language events
        function loadLanguageEvents(languageId, languageName) {
            const container = document.getElementById('language-events-container');
            container.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #e31d1c;"></i><p style="margin-top: 15px;">Carregando eventos...</p></div>';
            
            // Fetch events for the selected language
            fetch(`ajax/get_language_events.php?language_id=${languageId}`)
                .then(response => response.json())
                .then(data => {
                    const events = data.events || [];
                    
                    // Clear loading state
                    container.innerHTML = '';
                    
                    if (events.length === 0) {
                        container.innerHTML = `
                            <div class="no-events">
                                <p>Não há eventos programados para ${languageName}</p>
                            </div>
                        `;
                        return;
                    }
                    
                    // Create timeline container
                    const timeline = document.createElement('div');
                    timeline.className = 'timeline';
                    container.appendChild(timeline);
                    
                    // Add events to timeline
                    events.forEach(event => {
                        // Get current day and time
                        const currentDay = (new Date()).getDay() || 7; // JS returns 0-6 (0 is Sunday), we need 1-7 (1 is Monday)
                        const currentHour = (new Date()).getHours();
                        
                        // Check if event is happening now
                        const isNow = (currentDay === parseInt(event.day_of_week) && currentHour === parseInt(event.time_hour));
                        const isPast = (currentDay === parseInt(event.day_of_week) && currentHour > parseInt(event.time_hour));
                        
                        // Create event element
                        const eventElem = document.createElement('div');
                        eventElem.className = 'timeline-event';
                        
                        // Create flag HTML
                        let flagHtml = '';
                        if (event.flag_emoji) {
                            flagHtml = `<span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">${event.flag_emoji}</span>`;
                        } else if (event.flag_code) {
                            flagHtml = `<img src="https://flagcdn.com/32x24/${event.flag_code}.png" class="flag-icon" alt="${event.language_name}">`;
                        } else {
                            flagHtml = `<span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🌍</span>`;
                        }
                        
                        // Get day name
                        const dayNames = {
                            1: 'Segunda-feira',
                            2: 'Terça-feira',
                            3: 'Quarta-feira',
                            4: 'Quinta-feira',
                            5: 'Sexta-feira',
                            6: 'Sábado',
                            7: 'Domingo'
                        };
                        
                        // Create social links
                        let socialLinks = '';
                        if (event.whatsapp_group_link) {
                            socialLinks += `<a href="${event.whatsapp_group_link}" target="_blank" class="social-icon whatsapp-icon" title="Grupo de ${event.language_name}"><i class="fab fa-whatsapp"></i></a>`;
                        }
                        if (event.instagram_link) {
                            socialLinks += `<a href="${event.instagram_link}" target="_blank" class="social-icon instagram-icon" title="Perfil de ${event.language_name}"><i class="fab fa-instagram"></i></a>`;
                        }
                        
                        // Create HTML for the event
                        eventElem.innerHTML = `
                            <span class="day-info">${dayNames[event.day_of_week]}</span>
                            <span class="event-time">${event.time_hour}h</span>
                            <div class="event-title">
                                ${flagHtml}
                                <span>${event.language_name}</span>
                                ${isNow ? '<span class="now-badge">AGORA</span>' : ''}
                                <div class="event-social-links">
                                    ${socialLinks}
                                </div>
                            </div>
                            <p class="event-description">${event.description || ''}</p>
                            <div class="event-actions">
                                ${event.meet_link ? `
                                    <a href="${event.meet_link}" target="_blank" class="event-button join-button ${isPast ? 'disabled' : ''}">
                                        ${isNow ? 'Participar' : (isPast ? '<i class="fas fa-check check-icon"></i> Finalizado' : 'Participar')}
                                    </a>
                                ` : ''}
                                ${event.youtube_link ? `
                                    <a href="${event.youtube_link}" target="_blank" class="event-button replay-button">
                                        <i class="fab fa-youtube"></i> Anteriores
                                    </a>
                                ` : ''}
                            </div>
                        `;
                        
                        timeline.appendChild(eventElem);
                    });
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    container.innerHTML = `
                        <div class="no-events">
                            <p>Erro ao carregar eventos para ${languageName}</p>
                        </div>
                    `;
                });
        }
        
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Check URL parameters on page load to set initial state
        function checkURLParameters() {
            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view');
            const day = urlParams.get('day');
            const languageId = urlParams.get('language_id');
            const languageName = urlParams.get('language_name');
            
            // Set the view based on URL parameter
            if (view === 'language') {
                languageFilterBtn.click();
                
                // If there's a specific language ID, select it
                if (languageId) {
                    const langOption = document.querySelector(`.language-option[data-language-id="${languageId}"]`);
                    if (langOption) {
                        setTimeout(() => {
                            langOption.click();
                        }, 100);
                    }
                }
            } else {
                // Default to day view
                dayFilterBtn.click();
                
                // If there's a specific day, select it
                if (day) {
                    const dayBtn = document.querySelector(`.day-button[data-day="${day}"]`);
                    if (dayBtn) {
                        setTimeout(() => {
                            dayBtn.click();
                        }, 100);
                    }
                } else {
                    // Select current day by default
                    const currentDayOfWeek = <?= $currentDayOfWeek ?>; // PHP variable with current day (1-7)
                    const currentDayButton = document.querySelector(`.day-button[data-day="${currentDayOfWeek}"]`);
                    if (currentDayButton) {
                        setTimeout(() => {
                            currentDayButton.click();
                        }, 100);
                    }
                }
            }
        }
        
        // Call the function to check URL parameters on page load
        checkURLParameters();
    });
</script>
</body>
</html>