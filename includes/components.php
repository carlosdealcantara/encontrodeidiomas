<?php
/**
 * Renderiza um card de evento único
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
                <?php if (!empty($ev['final_instagram'])): ?>
                <a href="<?= htmlspecialchars($ev['final_instagram']) ?>" target="_blank" class="social-icon instagram-icon" title="Instagram"><i class="fab fa-instagram"></i></a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($ev['host_name'])): ?>
        <div class="event-host-info" style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; font-size: 0.85rem; color: var(--text-color); opacity: 0.8;">
            <?php if (!empty($ev['host_photo'])): ?>
                <img src="assets/images/<?= htmlspecialchars($ev['host_photo']) ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;" alt="Host">
            <?php else: ?>
                <i class="fas fa-user-circle"></i>
            <?php endif; ?>
            <span>Host: <strong><?= htmlspecialchars($ev['host_name']) ?></strong></span>
        </div>
        <?php else: ?>
        <div class="event-host-info" style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; font-size: 0.85rem; color: var(--text-color); opacity: 0.8;">
            <div style="width: 24px; height: 24px; border-radius: 50%; background-image: url('assets/images/logo.png'); background-size: 110%; background-position: center; flex-shrink: 0; border: 1px solid rgba(0,0,0,0.05); background-color: #fff;"></div>
            <span><strong>Conversação Livre</strong></span>
        </div>
        <?php endif; ?>

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
 * Renderiza um card de anfitrião — estrutura original usada por equipe.php
 */
function renderHostCard($host) {
    $photo = !empty($host['profile_picture']) ? 'assets/images/' . $host['profile_picture'] : 
            (!empty($host['photo']) ? 'assets/images/' . $host['photo'] : 'assets/images/HostSemFoto.png');
    
    $rawCats = $host['category'] ?? $host['categories'] ?? '';
    $categories = array_map('trim', explode(',', $rawCats));
    
    if (!empty($host['technical_status']) && $host['technical_status'] === 'ativo') {
        if (!in_array('Técnica', $categories) && !in_array('tecnica', $categories)) {
            $categories[] = 'tecnica';
        }
    }
    
    if (empty(array_filter($categories))) {
        $categories[] = 'online';
    }
    
    $categoriesAttr = strtolower(implode(' ', $categories));
    $categoriesAttr = str_replace('técnica', 'tecnica', $categoriesAttr);
    
    $region = $host['region'] ?? '';
    $langs = !empty($host['languages']) ? array_map('trim', explode(',', $host['languages'])) : [];
    
    $roles = [];
    if (!empty($host['technical_status']) && $host['technical_status'] === 'ativo' && !empty($host['technical_roles'])) {
        $roles = array_map('trim', explode(',', $host['technical_roles']));
    } else if (!empty($host['role'])) {
        $roles = array_map('trim', explode(',', $host['role']));
    } else if (!empty($host['roles'])) {
        $roles = array_map('trim', explode(',', $host['roles']));
    }
    
    $skills = !empty($host['technical_skills']) ? array_map('trim', explode(',', $host['technical_skills'])) : [];

    $social    = !empty($host['social_media_links']) ? json_decode($host['social_media_links'], true) : [];
    $whatsapp  = $social['whatsapp']  ?? $host['whatsapp']  ?? '';
    $email     = $social['email']     ?? $host['email']     ?? '';
    $instagram = $social['instagram'] ?? $host['instagram'] ?? '';
    $linkedin  = $social['linkedin']  ?? $host['linkedin']  ?? '';
    ?>
    <div class="host-card" 
         data-categories="<?= $categoriesAttr ?>" 
         data-languages="<?= strtolower(implode(',', $langs)) ?>" 
         data-region="<?= strtolower($region) ?>"
         data-roles="<?= strtolower(implode(',', $roles)) ?>">
        
        <div class="host-badges-container">
            <?php 
            $displayBadges = !empty($langs) ? $langs : (!empty($host['badge']) ? [$host['badge']] : []);
            foreach ($displayBadges as $badge): 
            ?>
                <span class="host-badge"><?= htmlspecialchars($badge) ?></span>
            <?php endforeach; ?>
        </div>

        <div class="host-image-container">
            <img src="<?= $photo ?>" alt="Foto de <?= htmlspecialchars($host['full_name']) ?>" class="host-image"
                 onerror="this.src='assets/images/HostSemFoto.png'">
        </div>

        <div class="host-info">
            <h2 class="host-name"><?= htmlspecialchars($host['full_name']) ?></h2>
            
            <?php if ($region): ?>
            <div class="host-region context-presencial">
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($region) ?>
            </div>
            <?php endif; ?>

            <p class="host-bio context-online"><?= htmlspecialchars($host['online_description'] ?? $host['bio'] ?? '') ?></p>
            <p class="host-bio context-presencial"><?= htmlspecialchars($host['inperson_description'] ?? $host['bio'] ?? '') ?></p>
            <p class="host-bio context-tecnica"><?= htmlspecialchars($host['technical_description'] ?? $host['bio'] ?? '') ?></p>

            <div class="host-tags context-tecnica" style="margin-top:15px;">
                <?php foreach ($skills as $s): ?>
                    <span class="tag"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="host-contact">
                <?php if (!empty($whatsapp)): ?>
                    <a href="<?= (strpos($whatsapp, 'http') === 0) ? htmlspecialchars($whatsapp) : 'https://wa.me/' . preg_replace('/\D/', '', $whatsapp) ?>" target="_blank" class="contact-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                <?php endif; ?>
                <?php if (!empty($email)): ?>
                    <a href="mailto:<?= htmlspecialchars($email) ?>" class="contact-btn" title="Email"><i class="fas fa-envelope"></i></a>
                <?php endif; ?>
                <?php if (!empty($instagram)): ?>
                    <a href="<?= (strpos($instagram, 'http') === 0) ? htmlspecialchars($instagram) : 'https://instagram.com/' . ltrim($instagram, '@') ?>" target="_blank" class="contact-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
                <?php endif; ?>
                <?php if (!empty($linkedin)): ?>
                    <a href="<?= htmlspecialchars($linkedin) ?>" target="_blank" class="contact-btn" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
