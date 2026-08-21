<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/whatsapp_helper.php';

function notificarAtualizacaoHosts($conn, $lang_id, $semana_atual, $acao_desc = "atualizou dados") {
    // ============================================================
    // RATE-LIMIT: evita disparos duplicados em sequência rápida.
    // Múltiplos gatilhos (worker, reshorten, manual_resolve, portal)
    // podem chamar esta função em segundos de diferença.
    // Só envia se a última notificação para ESTE idioma foi há mais
    // de 5 minutos. Usa a tabela settings como mutex leve.
    // ============================================================
    $rateKey = 'hosts_notif_last_' . (int)$lang_id;
    $RATE_LIMIT_SECONDS = 300; // 5 minutos

    try {
        $stmtRate = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmtRate->execute([$rateKey]);
        $lastSentRow = $stmtRate->fetch(PDO::FETCH_ASSOC);
        $lastSent = $lastSentRow ? (int)$lastSentRow['setting_value'] : 0;

        if ((time() - $lastSent) < $RATE_LIMIT_SECONDS) {
            // Menos de 5 minutos desde o último envio — ignora silenciosamente
            error_log("[hosts_notification] Rate-limit ativo para lang_id={$lang_id}. Última notif há " . (time() - $lastSent) . "s. Pulando.");
            return;
        }

        // Atualiza o timestamp ANTES de enviar (previne race condition)
        $stmtUpd = $conn->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmtUpd->execute([$rateKey, (string)time()]);
    } catch (Exception $e) {
        // Falha silenciosa: se a tabela settings falhar, envia mesmo assim
        error_log("[hosts_notification] Erro no rate-limit: " . $e->getMessage());
    }

    // Busca dados do idioma
    $stmtLang = $conn->prepare("SELECT name, flag_emoji FROM languages WHERE id = ?");
    $stmtLang->execute([$lang_id]);
    $langData = $stmtLang->fetch(PDO::FETCH_ASSOC);
    if (!$langData) return;
    $lang_nome = $langData['name'];
    $lang_emoji = $langData['flag_emoji'];

    if ($acao_desc === "atualizou dados") {
        // Mensagem inicial de atualização pelo portal
        $stmtThisLang = $conn->prepare("SELECT titulo FROM meetup_replays WHERE language_id = ? AND semana = ?");
        $stmtThisLang->execute([$lang_id, $semana_atual]);
        $rowThisLang = $stmtThisLang->fetch(PDO::FETCH_ASSOC);
        $titulo_idioma = $rowThisLang && !empty($rowThisLang['titulo']) ? $rowThisLang['titulo'] : "Título";

        $default_group_template = "🎬 *Replay:* {bandeira} {titulo}\n\n🔗 {link}";
        $group_template = getSetting('odysee_whatsapp_template', $default_group_template);
        
        // Remove barras escapadas caso existam no banco
        $group_template = str_replace('\n', "\n", $group_template);

        $preview_group_message = str_replace(
            ['{bandeira}', '{titulo}', '{link}'],
            [$lang_emoji, $titulo_idioma, '[Link será gerado]'],
            $group_template
        );

        $portal_url = "https://viaEi.com/portal_hosts/";

        $mensagem = "🔄 *Mensagem Semanal Atualizada!*\n"
                  . "O idioma {$lang_emoji} *{$lang_nome}* {$acao_desc} desta semana.\n\n"
                  . "🔗 *Acesse o Portal dos Hosts:* {$portal_url}\n\n"
                  . "O pipeline de postagem do vídeo no Odysee foi ativado e, em breve, o vídeo estará postado. A mensagem que o host tem para mandar nos grupos será enviada aqui primeiro, seguida pelo resumo semanal já completo.\n\n"
                  . "Prévia da mensagem para o seu grupo:\n\n"
                  . $preview_group_message;

        enviarWhatsApp('120363164732845564@g.us', $mensagem, 'hosts_app');
        return;
    }

    // Caso contrário (ação final do bot), envia a prévia consolidada da semana
    $stmtAll = $conn->prepare("
        SELECT l.id, l.name, l.flag_emoji, r.numero, r.link, r.titulo 
        FROM languages l 
        LEFT JOIN meetup_replays r ON l.id = r.language_id AND r.semana = ?
        LEFT JOIN (
            SELECT language_id, MIN(day_of_week) as first_day, MIN(time_hour) as first_hour 
            FROM meetings 
            WHERE active = 1 
            GROUP BY language_id
        ) m ON l.id = m.language_id
        WHERE l.active = 1 
        ORDER BY COALESCE(m.first_day, 9) ASC, COALESCE(m.first_hour, 99) ASC, l.name ASC
    ");
    $stmtAll->execute([$semana_atual]);
    
    $replays_list = "";
    while ($row = $stmtAll->fetch()) {
        if (empty($row['numero']) && empty($row['link']) && empty($row['titulo'])) {
            continue; // Pula idiomas que ainda não tiveram preenchimento nesta semana
        }
        $num = !empty($row['numero']) ? str_pad($row['numero'], 2, '0', STR_PAD_LEFT) : "Nº";
        $lnk = !empty($row['link'])   ? str_replace(['https://', 'http://'], '', $row['link']) : "Link";
        $tit = !empty($row['titulo']) ? $row['titulo'] : "Título";
        $replays_list .= "{$row['flag_emoji']} ▪️ {$num} ▪️ {$lnk} ▪️ {$tit}\n";
    }
    
    $default_template = "*Replays!* viaEi.com\n\n{REPLAYS_LIST}\n*Nº: Máximo de participantes simultâneos | Max simultaneous participants.*\n*🚀 Stay tuned for the next one! | Fique de olho para participar do próximo!*";
    $template = getSetting('weekly_summary_template', $default_template);
    // Remove barras escapadas caso existam no banco
    $template = str_replace('\n', "\n", $template);
    $full_text = str_replace('{REPLAYS_LIST}', trim($replays_list), $template);

    // URL do portal sempre em viaEi.com (domínio atual)
    $portal_url = "https://viaEi.com/portal_hosts/";

    enviarWhatsApp('120363164732845564@g.us',
        "🔄 *Mensagem Semanal Atualizada!*\nO idioma {$lang_emoji} *{$lang_nome}* {$acao_desc} desta semana.\n\n🔗 *Acesse o Portal dos Hosts:* {$portal_url}\n\nPrévia da mensagem final:\n\n" . $full_text,
        'hosts_app'
    );
}
