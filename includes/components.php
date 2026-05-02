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
                <?php if (!empty($ev['final_whatsapp'])): ?>
                <a href="<?= htmlspecialchars($ev['final_whatsapp']) ?>" target="_blank" class="social-icon whatsapp-icon" title="Grupo WhatsApp"><i class="fab fa-whatsapp"></i></a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="event-host-info" style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; font-size: 0.85rem; color: var(--text-color); opacity: 0.8;">
            <?php 
            $photo = !empty($ev['host_photo']) ? $ev['host_photo'] : 'favicon.png';
            $isFallback = empty($ev['host_photo']);
            // Tenta usar a miniatura se existir
            $thumbPath = !empty($ev['host_photo']) ? str_replace('.', '_thumb.', $photo) : $photo;
            $finalPhoto = (file_exists('assets/images/' . $thumbPath)) ? $thumbPath : $photo;
            
            // Estilo específico para o fallback: 16px é o tamanho real do favicon, evita borrão
            $imgStyle = $isFallback 
                ? "width: 16px; height: 16px; object-fit: contain; image-rendering: pixelated; image-rendering: -moz-crisp-edges;" 
                : "width: 24px; height: 24px; border-radius: 50%; object-fit: cover;";
            ?>
            <img src="assets/images/<?= $finalPhoto ?>" style="<?= $imgStyle ?>" alt="Host">
            <span><?= $isFallback ? '<strong>Conversação Livre</strong>' : 'Host: <strong>' . htmlspecialchars($ev['host_name']) . '</strong>' ?></span>
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

/**
 * Renderiza um card de anfitrião
 */
function renderHostCard($host) {
    // Mapeamento de colunas da produção (com fallback para colunas comuns)
    $photo = !empty($host['profile_picture']) ? 'assets/images/' . $host['profile_picture'] : 
            (!empty($host['photo']) ? 'assets/images/' . $host['photo'] : 'assets/images/HostSemFoto.png');
    
    // Processamento de Categorias
    $rawCats = $host['category'] ?? $host['categories'] ?? '';
    $categories = array_map('trim', explode(',', $rawCats));
    
    // Adiciona 'tecnica' se status técnico estiver ativo
    if (!empty($host['technical_status']) && $host['technical_status'] === 'ativo') {
        if (!in_array('Técnica', $categories) && !in_array('tecnica', $categories)) {
            $categories[] = 'tecnica';
        }
    }
    
    // Se estiver vazio, assume Online por padrão
    if (empty(array_filter($categories))) {
        $categories[] = 'online';
    }
    ?>
    <div class="team-card" data-category="<?= implode(' ', $categories) ?>">
        <div class="team-card-inner">
            <div class="team-img-container">
                <img src="<?= $photo ?>" alt="<?= htmlspecialchars($host['full_name']) ?>" class="team-img">
                <div class="team-social">
                    <?php 
                    $social = !empty($host['social_media_links']) ? json_decode($host['social_media_links'], true) : [];
                    if (!empty($social['whatsapp'])): ?>
                        <a href="https://wa.me/<?= $social['whatsapp'] ?>" target="_blank" class="social-icon"><i class="fab fa-whatsapp"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($social['instagram'])): ?>
                        <a href="https://instagram.com/<?= $social['instagram'] ?>" target="_blank" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($social['linkedin'])): ?>
                        <a href="https://linkedin.com/in/<?= $social['linkedin'] ?>" target="_blank" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="team-info">
                <h3><?= htmlspecialchars($host['full_name']) ?></h3>
                <?php if (!empty($host['languages'])): ?>
                    <div class="team-tags">
                        <?php 
                        $langs = explode(',', $host['languages']);
                        foreach (array_slice($langs, 0, 3) as $l): ?>
                            <span class="team-tag"><?= trim($l) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="team-bio"><?= htmlspecialchars($host['online_description'] ?? $host['bio'] ?? '') ?></p>
            </div>
        </div>
    </div>
    <?php
}
