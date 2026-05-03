<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Estatísticas Rápidas
$stmt = $conn->query("SELECT COUNT(*) as total FROM hosts");
$totalHosts = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM hosts WHERE status = 'ativo'");
$activeHosts = $stmt->fetch()['total'];

// Próximos encontros (exemplo estático por enquanto)
$currentHour = (int)date('H');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Admin Encontro de Idiomas</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --white: #ffffff;
            --card-bg: #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }

        body {
            background: var(--primary-bg);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            padding: 30px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.05);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 50px;
            padding: 0 10px;
        }
        .brand-logo {
            width: 35px;
            height: 35px;
            background: var(--accent-red);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
        .brand-name { font-size: 1.2rem; font-weight: 700; letter-spacing: -0.5px; }

        .nav-menu { flex: 1; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            color: var(--text-dim);
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(227, 29, 28, 0.1);
            color: var(--white);
        }
        .nav-item.active { background: var(--accent-red); }
        .nav-item i { width: 20px; font-size: 1.1rem; }

        .nav-logout {
            margin-top: auto;
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.2);
        }
        .nav-logout:hover { background: rgba(255, 107, 107, 0.1); color: #ff6b6b; }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }
        .header-title h2 { font-size: 1.8rem; font-weight: 700; }
        .header-title p { color: var(--text-dim); }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .icon-blue { background: rgba(56, 189, 248, 0.1); color: var(--accent-blue); }
        .icon-red { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); }
        .stat-info h3 { font-size: 1.8rem; font-weight: 700; line-height: 1; }
        .stat-info p { color: var(--text-dim); font-size: 0.9rem; margin-top: 5px; }

        /* Welcome Section */
        .welcome-banner {
            background: linear-gradient(135deg, var(--accent-red), #991b1b);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-text h1 { font-size: 2rem; margin-bottom: 10px; }
        .welcome-text p { opacity: 0.9; max-width: 500px; }
        .welcome-img { font-size: 4rem; opacity: 0.3; }

    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">EI</div>
            <span class="brand-name">ADMIN CENTRAL</span>
        </div>

        <nav class="nav-menu">
            <a href="index.php" class="nav-item active">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            <a href="hosts.php" class="nav-item">
                <i class="fas fa-users"></i> Equipe
            </a>
            <a href="meetings.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> Online
            </a>
            <a href="presencial.php" class="nav-item">
                <i class="fas fa-map-marker-alt"></i> Presencial
            </a>
            <a href="languages.php" class="nav-item">
                <i class="fas fa-language"></i> Idiomas
            </a>
            <a href="useful_links.php" class="nav-item">
                <i class="fas fa-link"></i> Links
            </a>
            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i> Configurações
            </a>

            <a href="logout.php" class="nav-item nav-logout">
                <i class="fas fa-sign-out-alt"></i> Sair do Painel
            </a>
        </nav>
    </aside>

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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $totalHosts ?></h3>
                    <p>Total de Hosts</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-red">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $activeHosts ?></h3>
                    <p>Hosts Ativos</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fas fa-language"></i>
                </div>
                <div class="stat-info">
                    <h3>+15</h3>
                    <p>Idiomas no Radar</p>
                </div>
            </div>
        </div>

        <!-- Espaço para mais seções no futuro -->
    </main>
</body>
</html>
