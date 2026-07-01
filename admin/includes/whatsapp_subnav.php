<?php
$current_page = basename($_SERVER['PHP_SELF']);
$tabs = [
    'meetup_groups.php'      => ['icon' => 'fab fa-whatsapp',       'label' => 'Grupos'],
    'meetup_templates.php'   => ['icon' => 'fas fa-comment-dots',   'label' => 'Templates'],
    'wpp_broadcast.php'      => ['icon' => 'fas fa-bullhorn',       'label' => 'Disparar'],
    'wpp_resumo_semanal.php' => ['icon' => 'fas fa-calendar-alt',   'label' => 'Resumo Semanal'],
    'conectar_whatsapp.php'  => ['icon' => 'fas fa-qrcode',         'label' => 'Conexão'],
    'wpp_contencao.php'      => ['icon' => 'fas fa-shield-alt',     'label' => 'Modo Contenção'],
];
?>
<div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; flex-wrap: wrap;">
    <?php foreach ($tabs as $url => $tab): ?>
        <?php $activeClass = ($current_page === $url) ? 'btn-primary' : 'btn-secondary'; ?>
        <a href="<?= $url ?>" class="btn <?= $activeClass ?>"><i class="<?= $tab['icon'] ?>"></i> <?= $tab['label'] ?></a>
    <?php endforeach; ?>
</div>
