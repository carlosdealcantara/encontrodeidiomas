<?php
session_start();
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

// Prevenir cache agressivo da Hostinger/LiteSpeed
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$msg = null;
$error = null;

// The active tab for redirecting back correctly
$active_tab = $_POST['tab'] ?? $_GET['tab'] ?? 'pagamentos';

// --- LOGIC: PAGAMENTOS E MENSALIDADES ---
if (isset($_GET['toggle_pagamento']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $newStatus = $_GET['toggle_pagamento'] === 'Pago' ? 'Pendente' : 'Pago';
    $stmt = $conn->prepare("UPDATE mentoria_alunos SET status_pagamento = :status WHERE id = :id");
    $stmt->execute(['status' => $newStatus, 'id' => $id]);
    header('Location: mentoria.php?tab=pagamentos&msg=' . urlencode('Status de pagamento atualizado com sucesso'));
    exit;
}

// Salvar mensagens de faturamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cobranca'])) {
    if (isset($_POST['pix_footer'])) {
        updateSetting('mentoria_pix_footer', $_POST['pix_footer']);
    }
    
    // Assegura que a coluna ativo_telegram existe
    try {
        $conn->exec("ALTER TABLE mentoria_mensagens ADD COLUMN ativo_telegram TINYINT(1) DEFAULT 1");
    } catch (Exception $e) {}

    if (isset($_POST['msgs']) && is_array($_POST['msgs'])) {
        $stmtUpdateMsg = $conn->prepare("UPDATE mentoria_mensagens SET dias_antes = ?, texto = ?, ativo = ?, ativo_telegram = ? WHERE id = ?");
        foreach ($_POST['msgs'] as $msg_id => $data) {
            $dias = (int)$data['dias'];
            $texto = $data['texto'];
            $ativo = isset($data['ativo']) ? 1 : 0;
            $ativo_telegram = isset($data['ativo_telegram']) ? 1 : 0;
            $stmtUpdateMsg->execute([$dias, $texto, $ativo, $ativo_telegram, $msg_id]);
        }
    }
    $msg = "Mensagens de faturamento salvas com sucesso!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_telegram_relay'])) {
    $novoEstado = isset($_POST['telegram_cobranca_ativo']) ? 1 : 0;
    updateSetting('telegram_cobranca_ativo', (string)$novoEstado);
    $msg = "Configurações do Telegram Relay salvas com sucesso!";
}

$stmt = $conn->query("SELECT * FROM mentoria_alunos ORDER BY CASE WHEN status_aluno = 'Ativo' THEN 1 ELSE 2 END ASC, proximo_vencimento ASC");
$alunos = $stmt->fetchAll();

// Pega os templates de cobrança para a aba pagamentos
$stmtMsgs = $conn->query("SELECT * FROM mentoria_mensagens ORDER BY dias_antes DESC");
$mensagens_cobranca = $stmtMsgs->fetchAll();
$pix_footer_atual = getSetting('mentoria_pix_footer', "🔑 Chave PIX: 01811018157\nCarlos");
$ltv_vitalicios = getSetting('ltv_vitalicios', '5000');

// --- LOGIC: MENSAGENS (Automações do Clube) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_groups'])) {
    $res = sendBaileysRequest('/groups', null, 'GET');
    if ($res['success'] && is_array($res['data'])) {
        $cache_file = __DIR__ . '/groups_cache.json';
        file_put_contents($cache_file, json_encode($res['data'], JSON_UNESCAPED_UNICODE));
        $msg = "Lista de grupos sincronizada com sucesso do WhatsApp! (" . count($res['data']) . " grupos encontrados)";
    } else {
        $error = "Erro ao buscar grupos do Node.js: " . ($res['error'] ?? 'Desconhecido');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $newConfig = [
        'admin_jid' => trim($_POST['admin_jid']),
        'groups' => [
            'our_classes'   => ['jid' => trim($_POST['jid_our_classes']),   'automations' => ['lembrete_aula']],
            'desafio'       => ['jid' => trim($_POST['jid_desafio']),        'automations' => ['auto_kick', 'aviso']],
            'the_lounge'    => ['jid' => trim($_POST['jid_the_lounge']),     'automations' => ['welcome', 'ranking_geral']],
            'pronunciation' => ['jid' => trim($_POST['jid_pronunciation'] ?? ''), 'automations' => ['ranking']],
            'music'         => ['jid' => trim($_POST['jid_music'] ?? ''),        'automations' => ['ranking']],
            'vocabulary'    => ['jid' => trim($_POST['jid_vocabulary'] ?? ''),   'automations' => ['ranking']],
            'games'         => ['jid' => trim($_POST['jid_games'] ?? ''),        'automations' => ['ranking']]
        ],
        'templates' => [
            'welcome' => trim($_POST['tpl_welcome']),
            'birthday' => trim($_POST['tpl_birthday'] ?? ''),
            'lembrete_aula' => trim($_POST['tpl_lembrete']),
            'aviso_desafio' => trim($_POST['tpl_aviso_desafio']),
            'kick_desafio' => trim($_POST['tpl_kick_desafio']),
            'ranking_social' => trim($_POST['tpl_ranking_social'] ?? ''),
            'ranking_student' => trim($_POST['tpl_ranking_student'] ?? ''),
            'ranking_legend' => trim($_POST['tpl_ranking_legend'] ?? ''),
            'ranking_messenger' => trim($_POST['tpl_ranking_messenger'] ?? ''),
            'ranking_reactor' => trim($_POST['tpl_ranking_reactor'] ?? ''),
            'class_aviso' => trim($_POST['tpl_class_aviso'] ?? ''),
            'class_cancel' => trim($_POST['tpl_class_cancel'] ?? ''),
            'class_kickoff' => trim($_POST['tpl_class_kickoff'] ?? ''),
            'practice_aviso' => trim($_POST['tpl_practice_aviso'] ?? ''),
            'practice_cancel' => trim($_POST['tpl_practice_cancel'] ?? ''),
            'practice_kickoff' => trim($_POST['tpl_practice_kickoff'] ?? ''),
            'daily_summary_header' => trim($_POST['tpl_daily_summary_header'] ?? ''),
            'attend_confirm' => trim($_POST['tpl_attend_confirm'] ?? ''),
            'attend_late_good' => trim($_POST['tpl_attend_late_good'] ?? ''),
            'attend_late_bad' => trim($_POST['tpl_attend_late_bad'] ?? ''),
            'unattend_confirm' => trim($_POST['tpl_unattend_confirm'] ?? ''),
            'unattend_cancelled_now' => trim($_POST['tpl_unattend_cancelled_now'] ?? ''),
            'class_status' => trim($_POST['tpl_class_status'] ?? ''),
            'streak_confirm' => trim($_POST['tpl_streak_confirm'] ?? ''),
            'streak_milestone' => trim($_POST['tpl_streak_milestone'] ?? ''),
            'streak_leaderboard' => trim($_POST['tpl_streak_leaderboard'] ?? '')
        ]
    ];
    
    $res = sendBaileysRequest('/mentoria-config', $newConfig, 'POST');
    if ($res['success']) {
        // Salva settings de regras de negócio no banco (não vão pro config.json do Baileys)
        updateSetting('ltv_vitalicios', trim($_POST['ltv_vitalicios'] ?? '5000'));
        $msg = "Configurações e mensagens salvas com sucesso no servidor Baileys!";
    } else {
        $error = "Erro ao salvar no Node.js: " . ($res['error'] ?? 'Desconhecido');
    }
}

$config = getMentoriaConfig();
$admin_jid = $config['admin_jid'] ?? '556192666148@s.whatsapp.net';
$jid_our_classes  = $config['groups']['our_classes']['jid']   ?? '';
$jid_desafio      = $config['groups']['desafio']['jid']       ?? '';
$jid_the_lounge   = $config['groups']['the_lounge']['jid']    ?? '';
$jid_pronunciation= $config['groups']['pronunciation']['jid'] ?? '';
$jid_music        = $config['groups']['music']['jid']         ?? '';
$jid_vocabulary   = $config['groups']['vocabulary']['jid']    ?? '';
$jid_games        = $config['groups']['games']['jid']         ?? '';

$tpl_welcome = $config['templates']['welcome'] ?? "Hey, @{name}! 👋\nWelcome to *The Lounge*! 🎉\nIntroduce yourself to the group!";
$tpl_birthday = $config['templates']['birthday'] ?? "🎂 *Happy Birthday, {nome}!* 🎉\n\nToday is a special day — one of our amazing Mentorship members is celebrating their birthday! 🥳\n\nWe hope this new year of life brings you lots of growth, joy, and of course... fluency! 🌟\n\nDrop a 🎂 or send a birthday message to make {nome}'s day even more special! 💬 @{numero}";
$tpl_lembrete = $config['templates']['lembrete_aula'] ?? "📚 *Daily Class Reminder*\nDon't forget to book today's class on Calendly!";
$tpl_aviso_desafio = $config['templates']['aviso_desafio'] ?? "⚠️ *Challenge Alert!*\nYou have until midnight to post your activity!";
$tpl_kick_desafio = $config['templates']['kick_desafio'] ?? "⚠️ @{name} has been removed for missing the daily activity.";
$tpl_ranking_student   = $config['templates']['ranking_student'] ?? "📅 {date}\n\n⭐ *STUDENT OF THE DAY*\n\n{student_of_the_day}\n\n*Other students:*\n{other_students}\n\n📖 *Legend:*\n{legend}";
$tpl_ranking_legend    = $config['templates']['ranking_legend'] ?? "🖥️ Attended Class (20 pts)\n🗣️ Reading out loud (5 pts)\n📚 Challenge (5 pts)\n🎶 Music Lab (4 pts)\n🧩 Games (2 pts)\n👏 Session commitment (2 pts)\n📒 New word! (1 pt)";
$tpl_ranking_messenger = $config['templates']['ranking_messenger'] ?? "📅 {date}\n\n💬 *TOP MESSENGER*\n_Who sent the most messages today?_\n\n{top_messenger_list}";
$tpl_ranking_reactor   = $config['templates']['ranking_reactor'] ?? "📅 {date}\n\n❤️ *TOP REACTOR*\n_Who gave the most reactions today?_\n\n{top_reactor_list}";
$tpl_class_aviso = $config['templates']['class_aviso'] ?? "👨‍🏫 *Teacher Class — {date}*

We have a class with the teacher scheduled for *{horario}*.
If you want to participate, please reply with `!attend`.

⏳ Deadline to confirm: *{deadline}*.";
$tpl_class_cancel = $config['templates']['class_cancel'] ?? "❌ *Class Cancelled*

Unfortunately, we didn't get any confirmations for the {horario} class today. See you next time! 👋";
$tpl_class_kickoff = $config['templates']['class_kickoff'] ?? "👨‍🏫 *Teacher Class is starting NOW!*

Join the room here: {link}

Have a great session! 💪";

$tpl_practice_aviso = $config['templates']['practice_aviso'] ?? "🗣️ *Students Practice — {date}*

A students-only conversation session is scheduled for *{horario}*.
_No teacher — just you practicing together!_

If you want to join, reply with `!attend`.

⏳ Deadline to confirm: *{deadline}*. _(Minimum 2 students required)_";
$tpl_practice_cancel = $config['templates']['practice_cancel'] ?? "❌ *Practice Session Cancelled*

Unfortunately, we didn't reach the minimum of 2 students for the {horario} practice session today. See you next time! 👋";
$tpl_practice_kickoff = $config['templates']['practice_kickoff'] ?? "🗣️ *Practice Session is starting NOW!*

Join the room here: {link}

Have a great conversation! 🚀";

$tpl_daily_summary_header = $config['templates']['daily_summary_header'] ?? "✅ Attendance confirmed for @{name}!

📅 *Today’s Schedule — {date}*
{sessionsBlock}";

$tpl_attend_confirm = $tpl_daily_summary_header;
$tpl_attend_late_good = $config['templates']['attend_late_good'] ?? "⏰ The deadline to confirm attendance has passed, @{name}.\n\n✅ *Good news:* The class is confirmed and will happen anyway!{listText}";
$tpl_attend_late_bad = $config['templates']['attend_late_bad'] ?? "⏰ The deadline to confirm attendance has passed, @{name}.\n\n❌ *Bad news:* The class was already cancelled due to lack of attendees.";
$tpl_unattend_confirm = $config['templates']['unattend_confirm'] ?? "🗑️ Registration cancelled for @{name}.{listText}";
$tpl_unattend_cancelled_now = $config['templates']['unattend_cancelled_now'] ?? "🚨 *CLASS CANCELLED*\n\nSince there are no more students confirmed, today's class is now cancelled.";
$tpl_class_status = $config['templates']['class_status'] ?? "📋 *Class Status — {class_info}*\n\n*Confirmed Attendees:*\n{attendees}\n\nDeadline to confirm: {deadline_info}";
$tpl_streak_confirm = $config['templates']['streak_confirm'] ?? "✅ Image computed, @{name}! You are on a {streak}-day streak! 🔥";
$tpl_streak_milestone = $config['templates']['streak_milestone'] ?? "🎉 CONGRATULATIONS! @{name} just hit a {streak}-day streak! Legend! 🏆";
$tpl_streak_leaderboard = $config['templates']['streak_leaderboard'] ?? "🏆 *All-Time Streak Records*\n\n{allTimeList}\n🔥 *Active Streaks Today*\n\n{activeList}";

$cache_file = __DIR__ . '/groups_cache.json';
$available_groups = [];
if (file_exists($cache_file)) {
    $res1 = file_get_contents($cache_file);
    $res1 = preg_replace('/^[\xef\xbb\xbf]+/', '', $res1);
    $dec1 = json_decode($res1, true);
    if (is_array($dec1) && !empty($dec1)) {
        $available_groups = $dec1;
        usort($available_groups, function($a, $b) {
            return strcasecmp($a['subject'] ?? '', $b['subject'] ?? '');
        });
    }
}

// Fallback: se o cache estiver vazio ou não existir, busca diretamente da API
if (empty($available_groups)) {
    $res = sendBaileysRequest('/groups', null, 'GET');
    if ($res['success'] && is_array($res['data']) && !empty($res['data'])) {
        $available_groups = $res['data'];
        usort($available_groups, function($a, $b) {
            return strcasecmp($a['subject'] ?? '', $b['subject'] ?? '');
        });
        file_put_contents($cache_file, json_encode($res['data'], JSON_UNESCAPED_UNICODE));
    }
}
function renderGroupSelect($name, $currentValue, $groups) {
    $html = '<select name="' . $name . '" class="select2-groups" style="width: 100%;">';
    $html .= '<option value="">Selecione um grupo...</option>';
    $found = false;
    foreach ($groups as $g) {
        $id = htmlspecialchars($g['id']);
        $subj = htmlspecialchars($g['subject'] ?? 'Sem Nome');
        $sel = (trim(strtolower($id)) === trim(strtolower($currentValue))) ? 'selected' : '';
        if ($sel) $found = true;
        $html .= "<option value=\"$id\" $sel>$subj  |  $id</option>";
    }
    if ($currentValue && !$found) {
        $val = htmlspecialchars($currentValue);
        $html .= "<option value=\"$val\" selected>$val (Personalizado)</option>";
    }
    $html .= '</select>';
    return $html;
}

// --- LOGIC: AGENDA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_schedule'])) {
    $action = $_POST['action_schedule'];
    if ($action === 'add' || $action === 'edit') {
        $day_of_week = (int)$_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $meet_link = trim($_POST['meet_link']);
        $session_type = $_POST['session_type'] ?? 'teacher_class';
        $group_jid = $config['groups']['our_classes']['jid'] ?? '';
        
        if (empty($group_jid)) {
            $error = "Por favor, configure primeiro o grupo Our Classes na aba de Mensagens.";
        } else {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO class_schedule (group_jid, day_of_week, start_time, meet_link, session_type) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$group_jid, $day_of_week, $start_time, $meet_link, $session_type]);
                $msg = "Horário adicionado com sucesso!";
            } else {
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("UPDATE class_schedule SET day_of_week=?, start_time=?, meet_link=?, session_type=? WHERE id=?");
                $stmt->execute([$day_of_week, $start_time, $meet_link, $session_type, $id]);
                $msg = "Horário atualizado com sucesso!";
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $status = (int)$_POST['status'];
        $stmt = $conn->prepare("UPDATE class_schedule SET is_active=? WHERE id=?");
        $stmt->execute([$status, $id]);
        $msg = "Status do horário atualizado.";
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM class_schedule WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Horário removido com sucesso.";
    }
}

// --- LOGIC: PILULAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pilula']) && $_POST['action_pilula'] === 'save') {
    $pilula_id = (int)$_POST['pilula_id'];
    $titulo = trim($_POST['titulo']);
    $texto_corpo = trim($_POST['texto_corpo']);
    $enquete_pergunta = trim($_POST['enquete_pergunta']);
    
    $enquete_opcoes_raw = trim($_POST['enquete_opcoes']);
    $enquete_opcoes_json = null;
    if (!empty($enquete_opcoes_raw)) {
        $opcoes = array_map('trim', explode(',', $enquete_opcoes_raw));
        $opcoes = array_filter($opcoes);
        if (!empty($opcoes)) {
            $enquete_opcoes_json = json_encode(array_values($opcoes), JSON_UNESCAPED_UNICODE);
        }
    }
    
    $ativo = isset($_POST['ativo']) && $_POST['ativo'] == '1' ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE pilulas_conteudo SET titulo=?, texto_corpo=?, enquete_pergunta=?, enquete_opcoes=?, ativo=? WHERE id=?");
    $stmt->execute([$titulo, $texto_corpo, $enquete_pergunta, $enquete_opcoes_json, $ativo, $pilula_id]);
    
    $msg = "Pílula salva com sucesso!";
}

$schedules = [];
try {
    $schedules = $conn->query("SELECT * FROM class_schedule ORDER BY day_of_week ASC, start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {} // Fail gracefully se a tabela não existir
$days = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];

if (isset($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hub da Mentoria | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        /* Base styles */
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --white: #ffffff;
            --card-bg: #1e293b;
            --input-bg: #0f172a;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.error { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border-color: rgba(227, 29, 28, 0.2); }

        /* Main Tabs */
        .main-tabs-nav { display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid rgba(255,255,255,0.05); flex-wrap: wrap; }
        .main-tab-btn { padding: 15px 30px; border: none; background: transparent; color: var(--text-dim); font-size: 1.1rem; font-weight: 600; cursor: pointer; position: relative; transition: 0.3s; white-space: nowrap; }
        .main-tab-btn:hover { color: var(--white); }
        .main-tab-btn.active { color: var(--accent-red); }
        .main-tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: var(--accent-red); }
        
        .main-tab-content { display: none; }
        .main-tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Select2 override */
        .select2-container--default .select2-selection--single { background-color: var(--input-bg); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; height: 46px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text-main); line-height: 46px; padding-left: 14px; }
        .select2-dropdown { background-color: #1e293b; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; color: var(--text-main); z-index: 99999; }
        .select2-search--dropdown .select2-search__field { background-color: var(--input-bg); color: var(--text-main); border: 1px solid rgba(255,255,255,0.2); }
        .select2-results__option { padding: 10px 14px; color: var(--text-main); font-size: 0.88rem; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: var(--accent-red) !important; color: white !important; }
        .select2-container--default .select2-results__option[aria-selected="true"] { background-color: rgba(255,255,255,0.1) !important; color: var(--text-main) !important; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 2.2rem; font-weight: 700; color: var(--white);">Hub da Mentoria</h1>
            <p style="color: var(--text-dim); font-size: 1.05rem;">Gestão centralizada de alunos, pagamentos, automações e agenda de aulas.</p>
        </div>

        <?php if ($msg): ?><div class="alert"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php
        // ⚠️ AVISO CRÍTICO: detectar grupos não configurados
        $groupsConfigured = array_filter([
            $jid_the_lounge, $jid_desafio, $jid_our_classes,
            $jid_pronunciation, $jid_music, $jid_vocabulary, $jid_games
        ], fn($v) => !empty(trim($v ?? '')));
        if (count($groupsConfigured) === 0): ?>
        <div style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.5); border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 14px;">
            <i class="fas fa-exclamation-triangle" style="color: #ef4444; font-size: 1.4rem; margin-top: 2px; flex-shrink: 0;"></i>
            <div>
                <strong style="color: #ef4444; font-size: 1.05rem;">⚠️ Nenhum grupo configurado — o bot está IGNORANDO todas as mensagens!</strong>
                <p style="color: #fca5a5; margin-top: 6px; font-size: 0.93rem;">
                    O bot precisa saber quais grupos monitorar para contabilizar mensagens, imagens e reações.
                    Acesse a aba <strong>Mensagens e Grupos</strong>, selecione os grupos nos campos abaixo e clique em <strong>Salvar Configurações</strong>.
                </p>
                <button onclick="document.querySelector('[onclick=\"switchMainTab(\'mensagens\')\"]').click()" style="margin-top: 10px; background: #ef4444; color: white; border: none; padding: 8px 18px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 0.9rem;">
                    <i class="fas fa-arrow-right"></i> Ir para Mensagens e Grupos
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="main-tabs-nav">
            <button class="main-tab-btn <?= $active_tab == 'pagamentos' ? 'active' : '' ?>" onclick="switchMainTab('pagamentos')"><i class="fas fa-users"></i> Alunos</button>
            <button class="main-tab-btn <?= $active_tab == 'cobrancas' ? 'active' : '' ?>" onclick="switchMainTab('cobrancas')"><i class="fas fa-money-bill-wave"></i> Mensalidades</button>
            <button class="main-tab-btn <?= $active_tab == 'mensagens' ? 'active' : '' ?>" onclick="switchMainTab('mensagens')"><i class="fas fa-robot"></i> Mensagens e Grupos</button>
            <button class="main-tab-btn <?= $active_tab == 'agenda' ? 'active' : '' ?>" onclick="switchMainTab('agenda')"><i class="fas fa-calendar-alt"></i> Agenda Classes</button>
            <button class="main-tab-btn <?= $active_tab == 'streaks' ? 'active' : '' ?>" onclick="switchMainTab('streaks')"><i class="fas fa-fire"></i> Streaks Desafio</button>
            <button class="main-tab-btn <?= $active_tab == 'score_editor' ? 'active' : '' ?>" onclick="switchMainTab('score_editor')"><i class="fas fa-trophy"></i> Ranking</button>
            <button class="main-tab-btn <?= $active_tab == 'odysee' ? 'active' : '' ?>" onclick="switchMainTab('odysee')"><i class="fas fa-video"></i> Odysee</button>
            <button class="main-tab-btn <?= $active_tab == 'telegram_cobranca' ? 'active' : '' ?>" onclick="switchMainTab('telegram_cobranca')"><i class="fab fa-telegram"></i> Avisos Telegram</button>
            <button class="main-tab-btn <?= $active_tab == 'pilulas' ? 'active' : '' ?>" onclick="switchMainTab('pilulas')"><i class="fas fa-pills"></i> Pílulas de Inglês</button>
        </div>

        <div id="tab_pagamentos" class="main-tab-content <?= $active_tab == 'pagamentos' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_pagamentos.php'; ?>
        </div>

        <div id="tab_cobrancas" class="main-tab-content <?= $active_tab == 'cobrancas' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_cobrancas.php'; ?>
        </div>

        <div id="tab_mensagens" class="main-tab-content <?= $active_tab == 'mensagens' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_mensagens.php'; ?>
        </div>

        <div id="tab_agenda" class="main-tab-content <?= $active_tab == 'agenda' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_agenda.php'; ?>
        </div>

        <div id="tab_streaks" class="main-tab-content <?= $active_tab == 'streaks' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_streaks.php'; ?>
        </div>

        <div id="tab_score_editor" class="main-tab-content <?= $active_tab == 'score_editor' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_score_editor.php'; ?>
        </div>

        <div id="tab_odysee" class="main-tab-content <?= $active_tab == 'odysee' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_odysee.php'; ?>
        </div>

        <div id="tab_telegram_cobranca" class="main-tab-content <?= $active_tab == 'telegram_cobranca' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_telegram_cobranca.php'; ?>
        </div>

        <div id="tab_pilulas" class="main-tab-content <?= $active_tab == 'pilulas' ? 'active' : '' ?>">
            <?php include 'mentoria_tabs/tab_pilulas.php'; ?>
        </div>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        function switchMainTab(tabId) {
            document.querySelectorAll('.main-tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.main-tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab_' + tabId).classList.add('active');
            event.currentTarget.classList.add('active');

            // Update URL query without reloading so refresh keeps state
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', url);
        }

        $(document).ready(function() {
            $('.select2-groups').select2({
                placeholder: "Busque pelo nome do grupo...",
                allowClear: true,
                dropdownParent: $(document.body),
                language: { noResults: function() { return "Nenhum grupo encontrado"; } }
            });
            $(document).on('select2:open', function() {
                setTimeout(function() {
                    const field = document.querySelector('.select2-container--open .select2-search__field');
                    if (field) field.focus();
                }, 50);
            });
        });
    </script>
</body>
</html>
