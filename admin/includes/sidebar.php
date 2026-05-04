<?php
/**
 * Admin Sidebar — Encontro de Idiomas
 *
 * Include this file INSIDE <body>, right before <main>.
 * Active state is detected automatically via PHP_SELF.
 */
$admin_current_page = basename($_SERVER['PHP_SELF']);

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

    <a href="logout.php" class="nav-item nav-logout">
        <i class="fas fa-sign-out-alt"></i> Sair do Painel
    </a>
</aside>
