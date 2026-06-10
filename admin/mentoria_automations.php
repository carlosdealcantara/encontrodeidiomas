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
            'ranking_diario' => trim($_POST['tpl_ranking'])
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Automações Mentoria | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        <style>
            /* Select2 Dark Theme Overrides */
            .select2-container--default .select2-selection--single {
                background-color: var(--input-bg);
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 8px;
                height: 45px;
                color: white;
            }
            .select2-container--default .select2-selection--single .select2-selection__rendered {
                color: white;
                line-height: 45px;
                padding-left: 12px;
            }
            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 43px;
            }
            .select2-dropdown {
                background-color: var(--card-bg);
                border: 1px solid rgba(255,255,255,0.1);
                color: white;
            }
            .select2-container--default .select2-search--dropdown .select2-search__field {
                background-color: var(--input-bg);
                border: 1px solid rgba(255,255,255,0.1);
                color: white;
                border-radius: 4px;
            }
            .select2-container--default .select2-results__option[aria-selected=true] {
                background-color: rgba(255,255,255,0.1);
            }
            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: var(--accent-red);
            }
            .select2-results__option {
                padding: 10px 12px;
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
        </header>

        <?php if ($msg): ?><div class="alert"><i class="fas fa-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php
        // Helper function to render <select>
        function renderGroupSelect($name, $currentValue, $groups) {
            $html = '<select name="' . $name . '" class="select2-groups" style="width: 100%;">';
            $html .= '<option value="">Selecione um grupo (ou digite para buscar)</option>';
            $found = false;
            foreach ($groups as $g) {
                $id = htmlspecialchars($g['id']);
                $subj = htmlspecialchars($g['subject'] ?? 'Sem Nome');
                $sel = ($id === $currentValue) ? 'selected' : '';
                if ($sel) $found = true;
                $html .= "<option value=\"$id\" $sel>$subj</option>";
            }
            // If the current value is not in the list (e.g. custom/old JID), add it
            if ($currentValue && !$found) {
                $val = htmlspecialchars($currentValue);
                $html .= "<option value=\"$val\" selected>$val (Personalizado)</option>";
            }
            $html .= '</select>';
            return $html;
        }
        ?>

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
            </div>

            <button type="submit" name="save_config" class="btn btn-primary" style="font-size: 1.1rem; padding: 15px 30px;">
                <i class="fas fa-save"></i> Salvar Configurações no Robô
            </button>
        </form>
    </main>

    <!-- jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-groups').select2({
                placeholder: "Busque pelo nome do grupo...",
                allowClear: true,
                language: {
                    noResults: function() {
                        return "Nenhum grupo encontrado";
                    }
                }
            });
        });
    </script>
</body>
</html>
