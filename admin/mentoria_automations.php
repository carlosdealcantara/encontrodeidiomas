<?php
session_start();
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$msg = null;
$error = null;

// Sincronizar Grupos
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

// Salvar Configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $newConfig = [
        'admin_jid' => trim($_POST['admin_jid']),
        'groups' => [
            'our_meetups' => ['jid' => trim($_POST['jid_our_meetups']), 'automations' => ['lembrete_aula']],
            'desafio' => ['jid' => trim($_POST['jid_desafio']), 'automations' => ['auto_kick', 'aviso']],
            'the_lounge' => ['jid' => trim($_POST['jid_the_lounge']), 'automations' => ['welcome', 'ranking_geral']]
        ],
        'templates' => [
            'welcome' => trim($_POST['tpl_welcome']),
            'lembrete_aula' => trim($_POST['tpl_lembrete']),
            'aviso_desafio' => trim($_POST['tpl_aviso_desafio']),
            'kick_desafio' => trim($_POST['tpl_kick_desafio']),
            'ranking_diario' => trim($_POST['tpl_ranking']),
            'meetup_aviso' => trim($_POST['tpl_meetup_aviso'] ?? ''),
            'meetup_cancel' => trim($_POST['tpl_meetup_cancel'] ?? ''),
            'meetup_kickoff' => trim($_POST['tpl_meetup_kickoff'] ?? '')
        ]
    ];
    
    $res = sendBaileysRequest('/mentoria-config', $newConfig, 'POST');
    if ($res['success']) {
        $msg = "Configurações e mensagens salvas com sucesso no servidor Baileys!";
    } else {
        $error = "Erro ao salvar no Node.js: " . ($res['error'] ?? 'Desconhecido');
    }
}

// Carregar Configurações Atuais
$config = getMentoriaConfig();

// Default values se não existir
$admin_jid = $config['admin_jid'] ?? '556192666148@s.whatsapp.net';
$jid_our_meetups = $config['groups']['our_meetups']['jid'] ?? '';
$jid_desafio = $config['groups']['desafio']['jid'] ?? '';
$jid_the_lounge = $config['groups']['the_lounge']['jid'] ?? '';

$tpl_welcome = $config['templates']['welcome'] ?? "Hey, @{name}! 👋\nWelcome to *The Lounge*! 🎉\nIntroduce yourself to the group!";
$tpl_lembrete = $config['templates']['lembrete_aula'] ?? "📚 *Daily Class Reminder*\nDon't forget to book today's class on Calendly!";
$tpl_aviso_desafio = $config['templates']['aviso_desafio'] ?? "⚠️ *Challenge Alert!*\nYou have until midnight to post your activity!";
$tpl_kick_desafio = $config['templates']['kick_desafio'] ?? "⚠️ @{name} has been removed for missing the daily activity.";
$tpl_ranking = $config['templates']['ranking_diario'] ?? "🏆 *Ranking do Dia* ({date})\n\n{ranking_list}";
$tpl_meetup_aviso = $config['templates']['meetup_aviso'] ?? "📅 *English Meetup Today!*\n\nWe have a session scheduled for *{horario} BRT*.\nIf you want to participate, please reply with `!attend`.\n\n⏳ You must confirm your attendance before *{deadline} BRT*.";
$tpl_meetup_cancel = $config['templates']['meetup_cancel'] ?? "❌ *Meetup Cancelled*\n\nUnfortunately, we didn't get any confirmations for the {horario} session today. Registrations are now closed and the class is cancelled. See you next time! 👋";
$tpl_meetup_kickoff = $config['templates']['meetup_kickoff'] ?? "🎉 *The Meetup is starting NOW!*\n\nJoin the room here: {link}\n\nHave a great session! 🗣️";

// Ler cache de grupos do Baileys para o datalist
$cache_file = __DIR__ . '/groups_cache.json';
$available_groups = [];
if (file_exists($cache_file)) {
    $res1 = file_get_contents($cache_file);
    $res1 = preg_replace('/^[\xef\xbb\xbf]+/', '', $res1);
    $dec1 = json_decode($res1, true);
    if (is_array($dec1)) {
        $available_groups = $dec1;
        usort($available_groups, function($a, $b) {
            return strcasecmp($a['subject'] ?? '', $b['subject'] ?? '');
        });
    }
}

// Helper function to render <select>
function renderGroupSelect($name, $currentValue, $groups) {
    $html = '<select name="' . $name . '" class="select2-groups" style="width: 100%;">';
    $html .= '<option value="">Selecione um grupo...</option>';
    $found = false;
    foreach ($groups as $g) {
        $id = htmlspecialchars($g['id']);
        $subj = htmlspecialchars($g['subject'] ?? 'Sem Nome');
        $sel = ($id === $currentValue) ? 'selected' : '';
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Automações Mentoria | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --card-bg: #1e293b;
            --input-bg: #0f172a;
            --success: #10b981;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.error { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border-color: rgba(227, 29, 28, 0.2); }
        .form-card { background: var(--card-bg); padding: 25px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; color: var(--text-dim); font-weight: 600; }
        input[type="text"], textarea { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; }
        textarea { resize: vertical; min-height: 100px; }
        .help-text { font-size: 0.85rem; color: var(--text-dim); margin-top: 5px; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; color: white; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--accent-red); }
        .btn-primary:hover { opacity: 0.9; }
        .section-title { font-size: 1.2rem; color: #38bdf8; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }

        /* ── Select2 Dark Theme ───────────────────────────────── */
        .select2-container { width: 100% !important; }
        .select2-container--default .select2-selection--single {
            background-color: var(--input-bg);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            height: 46px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--text-main);
            line-height: 46px;
            padding-left: 14px;
            padding-right: 30px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px;
            right: 8px;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: var(--text-dim);
        }
        /* Dropdown panel — attached to body so no overflow:hidden can clip it */
        .select2-dropdown {
            background-color: #1e293b;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            box-shadow: 0 16px 40px rgba(0,0,0,0.6);
            color: var(--text-main);
            z-index: 99999;
        }
        .select2-search--dropdown {
            padding: 10px 12px;
            background-color: #16213a;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .select2-search--dropdown::before {
            content: "\f002";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            margin-left: 10px;
            margin-top: 10px;
            color: var(--text-dim);
            pointer-events: none;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            background-color: var(--input-bg);
            border: 1px solid rgba(255,255,255,0.2);
            color: var(--text-main);
            border-radius: 6px;
            padding: 8px 12px 8px 34px;
            height: 38px;
            font-size: 0.9rem;
            width: 100%;
            outline: none;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field::placeholder {
            color: var(--text-dim);
        }
        .select2-results__options { max-height: 250px; overflow-y: auto; }
        .select2-results__option { padding: 10px 14px; color: var(--text-main); font-size: 0.88rem; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: var(--accent-red) !important;
            color: white !important;
        }
        .select2-container--default .select2-results__option[aria-selected="true"] {
            background-color: rgba(255,255,255,0.1) !important;
            color: var(--text-main) !important;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div>
                <h2>Automações da Mentoria</h2>
                <p style="color: var(--text-dim);">Configure as mensagens e mapeamento de grupos do Hub Bidirecional</p>
            </div>
            <div>
                <form method="POST" style="display: inline-block;">
                    <button type="submit" name="sync_groups" class="btn" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-sync-alt"></i> Sincronizar Grupos do WhatsApp
                    </button>
                </form>
            </div>
        </header>

        <?php if ($msg): ?><div class="alert"><i class="fas fa-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            
            <div class="form-card">
                <h3 class="section-title"><i class="fas fa-cog"></i> Configuração Geral</h3>
                <div class="form-group">
                    <label>Seu WhatsApp JID (Admin)</label>
                    <input type="text" name="admin_jid" value="<?= htmlspecialchars($admin_jid) ?>" required>
                    <p class="help-text">Este número é excluído do ranking e do sistema de expulsão do desafio.</p>
                </div>
                
                <h3 class="section-title" style="margin-top: 30px;"><i class="fas fa-users"></i> Mapeamento de Grupos</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Our Meetups (JID)</label>
                        <?= renderGroupSelect('jid_our_meetups', $jid_our_meetups, $available_groups) ?>
                    </div>
                    <div class="form-group">
                        <label>The Lounge (JID)</label>
                        <?= renderGroupSelect('jid_the_lounge', $jid_the_lounge, $available_groups) ?>
                    </div>
                    <div class="form-group">
                        <label>Desafio Diário (JID)</label>
                        <?= renderGroupSelect('jid_desafio', $jid_desafio, $available_groups) ?>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <h3 class="section-title"><i class="fas fa-comment-dots"></i> Textos das Automações</h3>
                
                <div class="form-group">
                    <label>Boas-vindas (The Lounge)</label>
                    <textarea name="tpl_welcome"><?= htmlspecialchars($tpl_welcome) ?></textarea>
                    <p class="help-text">Use <code>{name}</code> para o nome ou <code>@{name}</code> para marcar a pessoa.</p>
                </div>
                
                <div class="form-group">
                    <label>Lembrete Diário de Aula (Our Meetups - Seg a Qui às 8h)</label>
                    <textarea name="tpl_lembrete"><?= htmlspecialchars($tpl_lembrete) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Aviso Prévio Desafio (Desafio Diário - Todos os dias às 21h)</label>
                    <textarea name="tpl_aviso_desafio"><?= htmlspecialchars($tpl_aviso_desafio) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Mensagem de Expulsão (Desafio Diário - Todos os dias à meia-noite)</label>
                    <textarea name="tpl_kick_desafio"><?= htmlspecialchars($tpl_kick_desafio) ?></textarea>
                    <p class="help-text">Use <code>@{name}</code> para mencionar quem está sendo removido.</p>
                </div>

                <div class="form-group">
                    <label>Ranking Diário (The Lounge - Todos os dias à meia-noite)</label>
                    <textarea name="tpl_ranking"><?= htmlspecialchars($tpl_ranking) ?></textarea>
                    <p class="help-text">Use <code>{date}</code> para a data e <code>{ranking_list}</code> para injetar a lista top 5.</p>
                </div>

                <div class="form-group">
                    <label>Aviso Matinal Our Meetups (10:00)</label>
                    <textarea name="tpl_meetup_aviso"><?= htmlspecialchars($tpl_meetup_aviso) ?></textarea>
                    <p class="help-text">Variáveis úteis: não há variáveis pré-definidas no cron atual, a não ser que customize no mentoria_aula_aviso_cron.php.</p>
                </div>

                <div class="form-group">
                    <label>Cancelamento Our Meetups (1h antes, se 0 presenças)</label>
                    <textarea name="tpl_meetup_cancel"><?= htmlspecialchars($tpl_meetup_cancel) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Início da Aula Our Meetups (Kick-off na hora exata)</label>
                    <textarea name="tpl_meetup_kickoff"><?= htmlspecialchars($tpl_meetup_kickoff) ?></textarea>
                </div>
            </div>

            <button type="submit" name="save_config" class="btn btn-primary" style="font-size: 1.1rem; padding: 15px 30px;">
                <i class="fas fa-save"></i> Salvar Configurações no Robô
            </button>
        </form>

        <!-- Testador de Automações -->
        <h2 class="section-title" style="margin-top: 50px;"><i class="fas fa-vial"></i> Testador de Automações Manuais</h2>
        <div class="form-card">
            <p style="color: var(--text-dim); margin-bottom: 20px;">Execute os cron jobs manualmente. <strong>Testar Normal</strong> simula a execução comum (com travas de horário e duplicidade). <strong>Forçar Imediato</strong> ignora todas as regras e dispara a ação na hora.</p>
            
            <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                <!-- Aviso Matinal -->
                <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Aviso Matinal Our Meetups (Meia-noite)</h4>
                        <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Abre as inscrições da próxima aula válida do dia.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn" style="background:#334155;" onclick="testarCron('mentoria_aula_aviso_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                        <button type="button" class="btn" style="background:#ea580c;" onclick="testarCron('mentoria_aula_aviso_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
                    </div>
                </div>

                <!-- Encerramento Quorum -->
                <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Encerramento / Quórum (1h antes da aula)</h4>
                        <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Cancela a aula se houver 0 presenças confirmadas.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn" style="background:#334155;" onclick="testarCron('mentoria_aula_quorum_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                        <button type="button" class="btn" style="background:#ea580c;" onclick="testarCron('mentoria_aula_quorum_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
                    </div>
                </div>

                <!-- Kickoff -->
                <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Kick-off da Aula (Na hora exata)</h4>
                        <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Dispara o link do Google Meet para a turma.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn" style="background:#334155;" onclick="testarCron('mentoria_aula_kickoff_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                        <button type="button" class="btn" style="background:#ea580c;" onclick="testarCron('mentoria_aula_kickoff_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
                    </div>
                </div>

                <!-- Aviso Desafio -->
                <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Aviso Final do Desafio (21:00)</h4>
                        <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Lembrete no The Lounge para enviarem as gravações.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn" style="background:#334155;" onclick="testarCron('mentoria_desafio_aviso_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                        <button type="button" class="btn" style="background:#ea580c;" onclick="testarCron('mentoria_desafio_aviso_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
                    </div>
                </div>

                <!-- Kick Desafio -->
                <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Expulsão do Desafio (Meia-noite)</h4>
                        <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Remove quem não enviou os áudios e tira as vidas.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn" style="background:#334155;" onclick="testarCron('mentoria_desafio_kick_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                        <button type="button" class="btn" style="background:#ea580c;" onclick="testarCron('mentoria_desafio_kick_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
                    </div>
                </div>

                <!-- Ranking Diário -->
                <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Publicação do Ranking (Meia-noite)</h4>
                        <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Calcula a pontuação de ontem e posta no Lounge.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn" style="background:#334155;" onclick="testarCron('mentoria_ranking_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                        <button type="button" class="btn" style="background:#ea580c;" onclick="testarCron('mentoria_ranking_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
                    </div>
                </div>

            </div>
            
            <div id="test-console" style="display:none; background:#0f172a; border: 1px solid #334155; padding: 15px; margin-top: 25px; border-radius: 8px; font-family: monospace; color: #10b981; max-height: 300px; overflow-y: auto; white-space: pre-wrap;">
                Aguardando execução...
            </div>
        </div>


    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-groups').select2({
                placeholder: "Busque pelo nome do grupo...",
                allowClear: true,
                // Crítico: renderiza o dropdown no body, fora de qualquer overflow:hidden
                dropdownParent: $(document.body),
                language: {
                    noResults: function() { return "Nenhum grupo encontrado"; }
                }
            });

            // Foco automático ao abrir
            $(document).on('select2:open', function() {
                setTimeout(function() {
                    const field = document.querySelector('.select2-container--open .select2-search__field');
                    if (field) {
                        field.focus();
                        field.placeholder = 'Digite para filtrar...';
                    }
                }, 50);
            });
        });

        async function testarCron(scriptUrl, forcar) {
            const painel = document.getElementById('test-console');
            painel.style.display = 'block';
            painel.style.color = '#e2e8f0';
            painel.innerHTML = `⏳ Executando <span style="color:#38bdf8">${scriptUrl}</span>... Aguarde.`;

            let url = `../bot_whatsapp/${scriptUrl}?token=83x9aZ2pLQw1`;
            if (forcar) {
                url += '&test_now=1&force=1&test_hoje=1';
            } else {
                url += '&test_now=1'; // Manda o sinal de teste, mas sem forçar logs/bypass pesado
            }

            try {
                const response = await fetch(url);
                const text = await response.text();
                
                painel.innerHTML = `<strong style="color: #10b981;">✅ Execução Concluída (HTTP ${response.status}):</strong><br><br>` + 
                                   (text ? text.replace(/</g, "&lt;").replace(/>/g, "&gt;") : '<i>[Nenhuma saída de texto retornada pelo script]</i>');
            } catch (err) {
                painel.innerHTML = `<strong style="color: #ef4444;">❌ Erro na Requisição:</strong><br>${err.message}`;
            }
        }
    </script>
</body>
</html>
