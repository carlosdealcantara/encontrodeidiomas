<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/whatsapp_helper.php';

function notificarAtualizacaoHosts($conn, $lang_id, $semana_atual, $acao_desc = "atualizou dados") {
    // Busca dados do idioma
    $stmtLang = $conn->prepare("SELECT name, flag_emoji FROM languages WHERE id = ?");
    $stmtLang->execute([$lang_id]);
    $langData = $stmtLang->fetch(PDO::FETCH_ASSOC);
    if (!$langData) return;
    $lang_nome = $langData['name'];
    $lang_emoji = $langData['flag_emoji'];

    // Gera prévia consolidada da semana e notifica o grupo dos hosts
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
        $lnk = !empty($row['link'])   ? $row['link']   : "Link";
        $tit = !empty($row['titulo']) ? $row['titulo'] : "Título";
        $replays_list .= "{$row['flag_emoji']} ▪️ {$num} ▪️ {$lnk} ▪️ {$tit}\n";
    }
    
    $default_template = "*Replays!* https://encontrodeidiomas.com.br\n\n{REPLAYS_LIST}\n*Nº: Máximo de participantes simultâneos | Max simultaneous participants.*\n*🚀 Stay tuned for the next one! | Fique de olho para participar do próximo!*";
    $template = getSetting('weekly_summary_template', $default_template);
    $full_text = str_replace('{REPLAYS_LIST}', trim($replays_list), $template);

    // Usa SITE_URL para o link do portal, exceto se estiver vazio, usa dev fallback
    $portal_url = defined('SITE_URL') ? SITE_URL . "/portal_hosts/" : "https://dev.encontrodeidiomas.com.br/portal_hosts/";

    enviarWhatsApp('120363164732845564@g.us',
        "🔄 *Mensagem Semanal Atualizada!*\nO idioma {$lang_emoji} *{$lang_nome}* {$acao_desc} desta semana.\n\n🔗 *Acesse o Portal dos Hosts:* {$portal_url}\n\nPrévia da mensagem final:\n\n" . $full_text,
        'hosts_app'
    );
}
