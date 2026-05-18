<?php
$citySlugVal = getCitySlug($ev['city']);
$cityHref = $citySlugVal
    ? SITE_URL . (CURRENT_LANG === 'pt' ? '/' : '/en/') . $citySlugVal
    : SITE_URL . langUrl('presencial.php') . '?cidade=' . urlencode($ev['city']);
?>
<div class="city-card">
    <div class="city-card-header">
        <i class="fas fa-map-marker-alt city-pin"></i>
        <div>
            <div class="city-name"><?= htmlspecialchars($ev['city']) ?></div>
            <?php if (!empty($ev['state'])): ?>
                <span class="city-state-badge"><?= htmlspecialchars($ev['state']) ?></span>
            <?php endif; ?>
        </div>
        <button class="copy-city-link-btn" data-share-url="<?= $cityHref ?>" aria-label="<?= t('events.copy_link_aria') ?>" title="<?= t('events.copy_link_title') ?>">
            <i class="far fa-copy"></i>
            <span class="tooltip-text"><?= t('events.copy_link_tooltip') ?></span>
        </button>
    </div>
    <div class="city-card-body">
        <div class="city-event-title"><?= htmlspecialchars($ev['title']) ?></div>
        <?php if (!empty($ev['description'])): ?>
            <p class="city-desc"><?= htmlspecialchars($ev['description']) ?></p>
        <?php endif; ?>

        <?php if (!empty($ev['host_name'])): ?>
        <div class="city-host">
            <?php 
                $hostPhotoUrl = getHostPhotoUrl($ev['host_photo'] ?? null);
            ?>
            <img src="<?= $hostPhotoUrl ?>" alt="<?= htmlspecialchars($ev['host_name']) ?>" onerror="this.src='/assets/images/HostSemFoto.png'">
            <div>
                <div class="city-host-name"><?= htmlspecialchars($ev['host_name']) ?></div>
                <div class="city-host-label"><?= t('events.organizer_label') ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="city-links">
            <?php if (!empty($ev['whatsapp_link'])): ?>
                <a href="<?= htmlspecialchars($ev['whatsapp_link']) ?>" target="_blank" rel="noopener" class="city-link city-link-whatsapp">
                    <i class="fab fa-whatsapp"></i> <?= t('events.join_group') ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($ev['instagram_link'])): ?>
                <a href="<?= htmlspecialchars($ev['instagram_link']) ?>" target="_blank" rel="noopener" class="city-link city-link-instagram">
                    <i class="fab fa-instagram"></i> Instagram
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
