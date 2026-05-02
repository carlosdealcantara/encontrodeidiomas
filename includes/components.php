<?php
/**
 * Renderiza um card de evento único
 * Centralizado para garantir consistência total entre as views
 */
function renderEventCard($ev, $currentDayOfWeek, $currentHour, $isTarget = false) {
    $evDay   = (int)$ev['day_of_week'];
    $evHour  = (int)$ev['time_hour'];
    
    $isToday = ($currentDayOfWeek === $evDay);
    $isNow   = $isToday && ($currentHour === $evHour);
    $isPast  = ($evDay < $currentDayOfWeek) || ($isToday && $currentHour > $evHour);
    
    $flagCode  = $ev['flag_code'] ?? '';
    $flagEmoji = $ev['flag_emoji'] ?? '';
    $langName  = $ev['language_name'];
    ?>
    <div class="timeline-event <?= $isNow ? 'happening-now' : '' ?> <?= $isTarget ? 'scroll-target' : '' ?>">
        <div class="event-header-row">
            <div class="event-tags">
                <span class="event-tag"><?= getDayName($evDay) ?> às <?= $evHour ?>h</span>
            </div>
            <?php if ($isNow): ?>
            <span class="now-badge">AO VIVO</span>
            <?php endif; ?>
        </div>
        <div class="event-title">
            <?php if ($flagCode): ?>
                <img src="https://flagcdn.com/32x24/<?= htmlspecialchars($flagCode) ?>.png" class="flag-icon" alt="<?= htmlspecialchars($langName) ?>">
            <?php elseif ($flagEmoji): ?>
                <span style="font-size:1.2rem;"><?= $flagEmoji ?></span>
            <?php endif; ?>
            <span><?= htmlspecialchars($langName) ?></span>
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
                    <div class="event-button join-button wait-button"><i class="fa-solid fa-clock fa-spin-slow"></i> Aguarde</div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($ev['replay_link'])): ?>
            <a href="<?= htmlspecialchars($ev['replay_link']) ?>" target="_blank" class="event-button replay-button">
                <i class="fa-solid fa-circle-play"></i> Anteriores
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
