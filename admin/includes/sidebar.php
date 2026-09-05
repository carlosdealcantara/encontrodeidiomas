<?php
/**
 * Admin Sidebar — Encontro de Idiomas
 * Include this file inside <body>, before <main>.
 * Active state detected automatically via PHP_SELF.
 */
$admin_current_page = basename($_SERVER['PHP_SELF']);
$is_whatsapp_page = in_array($admin_current_page, ['meetup_groups.php', 'conectar_whatsapp.php', 'meetup_templates.php', 'wpp_broadcast.php', 'wpp_resumo_semanal.php', 'wpp_contencao.php']);

$nav_items = [
    'index.php'        => ['icon' => 'fas fa-chart-pie',      'label' => 'Dashboard'],
    'hosts.php'        => ['icon' => 'fas fa-users',           'label' => 'Equipe'],
    'meetings.php'     => ['icon' => 'fas fa-calendar-alt',   'label' => 'Online'],
    'presencial.php'   => ['icon' => 'fas fa-map-marker-alt', 'label' => 'Presencial'],
    'languages.php'    => ['icon' => 'fas fa-language',       'label' => 'Idiomas'],
    'mentoria.php'         => ['icon' => 'fas fa-graduation-cap', 'label' => 'Mentoria'],
    'whatsapp'             => ['icon' => 'fab fa-whatsapp',       'label' => 'WhatsApp', 'link' => 'meetup_groups.php', 'active' => $is_whatsapp_page],
    'comunidade_global.php'=> ['icon' => 'fas fa-globe',          'label' => 'Global'],
    'odysee.php'           => ['icon' => 'fas fa-cloud-upload-alt','label' => 'Odysee'],
    'telegram_bot.php'     => ['icon' => 'fab fa-telegram',       'label' => 'Telegram'],
    'useful_links.php'     => ['icon' => 'fas fa-link',           'label' => 'Links'],
    'settings.php'         => ['icon' => 'fas fa-cog',            'label' => 'Configurações'],
];
?>
<style>
    /* ─── Shared Admin Variables & Reset ─────────────────────────── */
    :root {
        --primary-bg: #0f172a;
        --sidebar-bg: #1e293b;
        --accent-red: #e31d1c;
        --accent-blue: #38bdf8;
        --text-main:  #f1f5f9;
        --text-dim:   #94a3b8;
        --white:      #ffffff;
        --card-bg:    #1e293b;
        --input-bg:   #0f172a;
        --success:    #10b981;
        --danger:     #ef4444;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
    body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

    /* ─── Sidebar ─────────────────────────────────────────────────── */
    .sidebar {
        width: 260px;
        flex-shrink: 0;
        background: var(--sidebar-bg);
        padding: 28px 20px;
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(255,255,255,0.05);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
    }
    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 36px;
        padding: 0 8px;
    }
    .brand-logo {
        width: 36px;
        height: 36px;
        background: var(--accent-red);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .brand-name {
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: -0.3px;
        line-height: 1.2;
    }
    .nav-menu {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        color: var(--text-dim);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.2s;
        font-weight: 500;
        font-size: 0.92rem;
    }
    .nav-item i { width: 20px; font-size: 0.95rem; text-align: center; flex-shrink: 0; }
    .nav-item:hover { background: rgba(255,255,255,0.06); color: var(--white); }
    .nav-item.active { background: var(--accent-red); color: white; }
    
    .submenu { list-style: none; padding-left: 45px; margin-top: 5px; margin-bottom: 5px; }
    .submenu a { color: var(--text-dim); text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; padding: 6px 0; transition: color 0.2s; }
    .submenu a:hover { color: var(--white); }
    
    .nav-divider { height: 1px; background: rgba(255,255,255,0.06); margin: 10px 0; }
    .nav-logout {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        color: #ff6b6b;
        text-decoration: none;
        border-radius: 10px;
        border: 1px solid rgba(255,107,107,0.2);
        transition: all 0.2s;
        font-weight: 500;
        font-size: 0.92rem;
        margin-top: auto;
    }
    .nav-logout:hover { background: rgba(255,107,107,0.1); }
    .nav-logout i { width: 20px; text-align: center; }

    /* ─── Main Content (default, can be overridden per-page) ──────── */
    .main-content { flex: 1; padding: 36px 40px; overflow-y: auto; }

    /* ─── Mobile Sidebar ──────────────────────────────────────────── */
    .hamburger-btn {
        display: none;
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1001;
        background: var(--sidebar-bg);
        color: var(--white);
        border: 1px solid rgba(255,255,255,0.1);
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1.2rem;
    }
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.6);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .sidebar-overlay.show {
        display: block;
        opacity: 1;
    }

    @media (max-width: 768px) {
        .hamburger-btn { display: block; }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .main-content {
            width: 100%;
            padding: 70px 16px 20px 16px; /* Extra top padding for hamburger */
        }
    }
</style>

<button class="hamburger-btn" id="hamburger-btn">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-logo">Ei</div>
        <span class="brand-name">ADMIN CENTRAL</span>
    </div>

    <nav class="nav-menu">
        <?php foreach ($nav_items as $file => $item): ?>
        <?php 
            $href = $item['link'] ?? $file; 
            $isActive = isset($item['active']) ? $item['active'] : ($admin_current_page === $file);
        ?>
        <a href="<?= $href ?>" class="nav-item <?= $isActive ? 'active' : '' ?>">
            <i class="<?= $item['icon'] ?>"></i> <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="nav-divider"></div>
    <a href="logout.php" class="nav-logout">
        <i class="fas fa-sign-out-alt"></i> Sair do Painel
    </a>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    function toggleSidebar() {
        if(sidebar && overlay) {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }
    }

    if(hamburgerBtn) {
        hamburgerBtn.addEventListener('click', toggleSidebar);
    }
    if(overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }
    
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            if(window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
                toggleSidebar();
            }
        });
    });
});
</script>
