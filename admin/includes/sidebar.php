<?php
/**
 * Admin Sidebar — Encontro de Idiomas
 * Usage: include __DIR__ . '/includes/sidebar.php';
 * Requires: $admin_current_page = 'filename.php' (set before including)
 */
$admin_current_page = $admin_current_page ?? basename($_SERVER['PHP_SELF']);

$svgFavicon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Crect width='512' height='512' rx='128' fill='%23e31d1c'/%3E%3Ctext x='256' y='256' dy='.35em' font-family='system-ui, -apple-system, sans-serif' font-weight='900' font-size='300' fill='white' text-anchor='middle'%3EEi%3C/text%3E%3C/svg%3E";

$nav_items = [
    'index.php'        => ['icon' => 'fas fa-chart-pie',      'label' => 'Dashboard'],
    'hosts.php'        => ['icon' => 'fas fa-users',           'label' => 'Equipe'],
    'meetings.php'     => ['icon' => 'fas fa-calendar-alt',   'label' => 'Online'],
    'presencial.php'   => ['icon' => 'fas fa-map-marker-alt', 'label' => 'Presencial'],
    'languages.php'    => ['icon' => 'fas fa-language',       'label' => 'Idiomas'],
    'useful_links.php' => ['icon' => 'fas fa-link',           'label' => 'Links'],
    'settings.php'     => ['icon' => 'fas fa-cog',            'label' => 'Configurações'],
];
?>
    <link rel="icon" type="image/svg+xml" href="<?= $svgFavicon ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a; --sidebar-bg: #1e293b; --accent-red: #e31d1c;
            --accent-blue: #38bdf8; --text-main: #f1f5f9; --text-dim: #94a3b8;
            --white: #ffffff; --card-bg: #1e293b; --success: #10b981; --danger: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width: 260px; flex-shrink: 0;
            background: var(--sidebar-bg);
            padding: 28px 20px;
            display: flex; flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.05);
            position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .brand {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 40px; padding: 0 8px;
        }
        .brand-logo {
            width: 36px; height: 36px; background: var(--accent-red);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; color: white; font-weight: 700; font-size: 1rem;
            flex-shrink: 0;
        }
        .brand-name { font-size: 1.05rem; font-weight: 700; letter-spacing: -0.3px; line-height: 1.2; }
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; color: var(--text-dim);
            text-decoration: none; border-radius: 12px;
            transition: all 0.25s; font-weight: 500; font-size: 0.95rem;
        }
        .nav-item i { width: 20px; font-size: 1rem; text-align: center; flex-shrink: 0; }
        .nav-item:hover { background: rgba(255,255,255,0.06); color: var(--white); }
        .nav-item.active { background: var(--accent-red); color: white; }
        .nav-divider { height: 1px; background: rgba(255,255,255,0.06); margin: 12px 0; }
        .nav-logout {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; color: #ff6b6b;
            text-decoration: none; border-radius: 12px;
            border: 1px solid rgba(255,107,107,0.2);
            transition: all 0.25s; font-weight: 500; font-size: 0.95rem;
            margin-top: auto;
        }
        .nav-logout:hover { background: rgba(255,107,107,0.1); }
        .nav-logout i { width: 20px; text-align: center; }

        /* ── Main Content ── */
        .main-content { flex: 1; padding: 36px 40px; overflow-y: auto; }
    </style>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-logo">Ei</div>
        <span class="brand-name">ADMIN CENTRAL</span>
    </div>

    <nav class="nav-menu">
        <?php foreach ($nav_items as $file => $item): ?>
        <a href="<?= $file ?>" class="nav-item <?= $admin_current_page === $file ? 'active' : '' ?>">
            <i class="<?= $item['icon'] ?>"></i> <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="nav-divider"></div>
    <a href="logout.php" class="nav-logout">
        <i class="fas fa-sign-out-alt"></i> Sair do Painel
    </a>
</aside>
