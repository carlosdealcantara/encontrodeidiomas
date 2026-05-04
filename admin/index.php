<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Hosts
$totalHosts  = $conn->query("SELECT COUNT(*) FROM hosts")->fetchColumn();
$activeHosts = $conn->query("SELECT COUNT(*) FROM hosts WHERE status='ativo'")->fetchColumn();

// Idiomas
$totalLanguages = $conn->query("SELECT COUNT(*) FROM languages")->fetchColumn();

// Encontros Online
$totalMeetings  = $conn->query("SELECT COUNT(*) FROM meetings")->fetchColumn();
$activeMeetings = $conn->query("SELECT COUNT(*) FROM meetings WHERE active=1")->fetchColumn();

// Encontros Presenciais
$totalPresencial  = $conn->query("SELECT COUNT(*) FROM in_person_events WHERE active=1")->fetchColumn();
$totalPaises      = $conn->query("SELECT COUNT(DISTINCT country) FROM in_person_events WHERE active=1")->fetchColumn();

// Links
$totalLinks = $conn->query("SELECT COUNT(*) FROM useful_links")->fetchColumn();

$currentHour = (int)date('H');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Admin Encontro de Idiomas</title>
    <style>
        /* PAGE SPECIFIC STYLES */
        .main-content { flex: 1; padding: 36px 40px; overflow-y: auto; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <div class="header-title">
                <h2>Dashboard Overview</h2>
                <p>Bem-vindo de volta, Carlos.</p>
            </div>
            <div class="header-actions">
                <span style="color: var(--text-dim);"><i class="far fa-calendar-alt" style="margin-right: 8px;"></i> <?= date('d/m/Y') ?></span>
            </div>
        </header>

        <section class="welcome-banner">
            <div class="welcome-text">
                <h1>Olá, Carlos de Alcântara!</h1>
                <p>O Encontro de Idiomas continua crescendo. Aqui você tem o controle total sobre a equipe e a vitrine do projeto.</p>
            </div>
            <div class="welcome-img">
                <i class="fas fa-rocket"></i>
            </div>
        </section>

        <p class="section-title">Equipe &amp; Idiomas</p>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?= $totalHosts ?></h3>
                    <p>Total de Hosts</p>
                    <a href="hosts.php" class="stat-link">Gerenciar &rarr;</a>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?= $activeHosts ?></h3>
                    <p>Hosts Ativos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fas fa-language"></i></div>
                <div class="stat-info">
                    <h3><?= $totalLanguages ?></h3>
                    <p>Idiomas Cadastrados</p>
                    <a href="languages.php" class="stat-link">Gerenciar &rarr;</a>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-amber"><i class="fas fa-link"></i></div>
                <div class="stat-info">
                    <h3><?= $totalLinks ?></h3>
                    <p>Links Úteis</p>
                    <a href="useful_links.php" class="stat-link">Gerenciar &rarr;</a>
                </div>
            </div>
        </div>

        <p class="section-title">Encontros Online</p>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-info">
                    <h3><?= $totalMeetings ?></h3>
                    <p>Encontros Cadastrados</p>
                    <a href="meetings.php" class="stat-link">Gerenciar &rarr;</a>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3><?= $activeMeetings ?></h3>
                    <p>Encontros Ativos</p>
                </div>
            </div>
        </div>

        <p class="section-title">Encontros Presenciais</p>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-red"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-info">
                    <h3><?= $totalPresencial ?>+</h3>
                    <p>Grupos Ativos</p>
                    <a href="presencial.php" class="stat-link">Gerenciar &rarr;</a>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-teal"><i class="fas fa-globe"></i></div>
                <div class="stat-info">
                    <h3><?= $totalPaises ?>+</h3>
                    <p>Países com Grupos</p>
                </div>
            </div>
        </div>

        <!-- Espaço para mais seções no futuro -->
    </main>
</body>
</html>
