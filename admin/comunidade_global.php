<?php
session_start();
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$msg = null;
$error = null;

// --- LÓGICA DE BOAS-VINDAS (CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_toggles'])) {
        $conn->query("UPDATE meetup_whatsapp_groups SET welcome_enabled = 0 WHERE comunidade = 'global'");
        if (!empty($_POST['welcome_enabled'])) {
            $stmt = $conn->prepare("UPDATE meetup_whatsapp_groups SET welcome_enabled = 1 WHERE group_id = ?");
            foreach ($_POST['welcome_enabled'] as $jid => $val) {
                $stmt->execute([$jid]);
            }
        }
        $msg = "Configurações de grupos salvas com sucesso.";
    }
    
    if (isset($_POST['add_intro'])) {
        $stmt = $conn->prepare("INSERT INTO community_welcome_intros (text_target, text_en) VALUES (?, ?)");
        $stmt->execute([trim($_POST['text_target']), trim($_POST['text_en'])]);
        $msg = "Saudação adicionada com sucesso.";
    }
    if (isset($_POST['edit_intro'])) {
        $stmt = $conn->prepare("UPDATE community_welcome_intros SET text_target = ?, text_en = ? WHERE id = ?");
        $stmt->execute([trim($_POST['text_target']), trim($_POST['text_en']), (int)$_POST['id']]);
        $msg = "Saudação atualizada com sucesso.";
    }
    if (isset($_POST['toggle_intro'])) {
        $stmt = $conn->prepare("UPDATE community_welcome_intros SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $msg = "Status da saudação alterado.";
    }
    
    if (isset($_POST['add_question'])) {
        $stmt = $conn->prepare("INSERT INTO community_welcome_questions (text_target, text_en) VALUES (?, ?)");
        $stmt->execute([trim($_POST['text_target']), trim($_POST['text_en'])]);
        $msg = "Pergunta adicionada com sucesso.";
    }
    if (isset($_POST['edit_question'])) {
        $stmt = $conn->prepare("UPDATE community_welcome_questions SET text_target = ?, text_en = ? WHERE id = ?");
        $stmt->execute([trim($_POST['text_target']), trim($_POST['text_en']), (int)$_POST['id']]);
        $msg = "Pergunta atualizada com sucesso.";
    }
    if (isset($_POST['toggle_question'])) {
        $stmt = $conn->prepare("UPDATE community_welcome_questions SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $msg = "Status da pergunta alterado.";
    }

    if (isset($_POST['save_translation'])) {
        $stmt = $conn->prepare("INSERT INTO community_welcome_translations (entity_type, entity_id, lang_code, text) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE text = ?");
        $stmt->execute([$_POST['entity_type'], (int)$_POST['entity_id'], $_POST['lang_code'], trim($_POST['text']), trim($_POST['text'])]);
        $msg = "Tradução salva com sucesso.";
    }
}

// Lógica de Sincronização Manual (Push config to Baileys)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_global_config'])) {
    // 1. Pega config atual da mentoria para não perder o resto
    $config = getCommunityConfig();
    
    // 2. Busca grupos globais no banco
    try {
        $stmtGlob = $conn->query("SELECT group_id as jid, nome, welcome_enabled FROM meetup_whatsapp_groups WHERE comunidade = 'global' AND ativo = 1");
        $globalGroups = $stmtGlob->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($globalGroups as $gg) {
            $key = 'global_' . preg_replace('/[^a-z0-9]/', '', strtolower($gg['nome']));
            $config['groups'][$key] = [
                'jid' => $gg['jid'],
                'name' => $gg['nome'],
                'is_community_group' => true,
                'ranking_enabled' => true,
                'welcome_enabled' => (bool)$gg['welcome_enabled']
            ];
        }

        if (isset($_POST['tpl_messenger'])) {
            $config['templates']['community_ranking_messenger'] = trim($_POST['tpl_messenger']);
        }
        if (isset($_POST['tpl_reactor'])) {
            $config['templates']['community_ranking_reactor'] = trim($_POST['tpl_reactor']);
        }
        if (isset($_POST['tpl_welcome_general'])) {
            $config['templates']['community_welcome_general'] = trim($_POST['tpl_welcome_general']);
        }
        
        // 3. Envia para o Baileys
        $res = sendBaileysRequest('/community-config', $config, 'POST');
        if ($res['success']) {
            $msg = "As configurações e grupos foram sincronizados com sucesso no servidor do robô!";
        } else {
            $error = "Erro ao sincronizar com o robô: " . ($res['error'] ?? 'Desconhecido');
        }
    } catch (Exception $e) {
        $error = "Erro ao buscar grupos no banco de dados.";
    }
}

// Pegar config atual para exibição
$config = getCommunityConfig();

$tpl_messenger = $config['templates']['community_ranking_messenger'] ?? "📊 *DAILY RANKING — {group_name}*\n📅 _{date}_\n━━━━━━━━━━━━━━━━━━━━━━\n\n💬 *TOP TALKERS*\n_Who sent the most messages today?_\n\n{msg_ranking_list}\n\n━━━━━━━━━━━━━━━━━━━━━━\n✨ _Keep the conversation going! Tomorrow's ranking starts now._ 🚀";
$tpl_reactor = $config['templates']['community_ranking_reactor'] ?? "❤️ *REACTION STARS — {group_name}*\n📅 _{date}_\n━━━━━━━━━━━━━━━━━━━━━━\n\n_Who spread the most love today?_\n\n{react_ranking_list}\n\n━━━━━━━━━━━━━━━━━━━━━━\n_React to others and climb the ranking! 🙌_";
$tpl_welcome_general = $config['templates']['community_welcome_general'] ?? "{intro_text}\n\nWe'd love to get to know you! Tell us:\n\n{questions_text}";

$title = 'Global - Admin';
$current_page = 'comunidade_global.php';
include 'includes/header.php';

// Busca dados para a feature de boas-vindas
$stmtGroups = $conn->query("SELECT group_id as jid, nome, lang_code, welcome_enabled FROM meetup_whatsapp_groups WHERE comunidade = 'global' AND ativo = 1 ORDER BY nome ASC");
$allGlobalGroups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

$stmtIntros = $conn->query("SELECT * FROM community_welcome_intros ORDER BY id ASC");
$introsList = $stmtIntros->fetchAll(PDO::FETCH_ASSOC);

$stmtQs = $conn->query("SELECT * FROM community_welcome_questions ORDER BY id ASC");
$qsList = $stmtQs->fetchAll(PDO::FETCH_ASSOC);

$stmtTrans = $conn->query("SELECT * FROM community_welcome_translations");
$translationsRaw = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);
$translations = []; // [entity_type][entity_id][lang_code] = text
foreach($translationsRaw as $tr) {
    $translations[$tr['entity_type']][$tr['entity_id']][$tr['lang_code']] = $tr['text'];
}

$stmtLangs = $conn->query("SELECT DISTINCT lang_code FROM meetup_whatsapp_groups WHERE comunidade = 'global' AND ativo = 1 AND lang_code != 'en'");
$activeLangs = $stmtLangs->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    .page-title { color: #fff; font-size: 24px; font-weight: 600; margin-bottom: 20px; }
    .card { background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px; }
    .btn-primary { background: #38bdf8; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary:hover { background: #0284c7; }
    .btn-small { background: #444; color: #fff; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
    .btn-small:hover { background: #555; }
    .btn-danger { background: #ef4444; }
    .btn-danger:hover { background: #dc2626; }
    .btn-success { background: #10b981; }
    .btn-success:hover { background: #059669; }
    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
    .alert-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    .alert-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
    
    .table-dark { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .table-dark th { text-align: left; padding: 10px; border-bottom: 1px solid #444; color: #aaa; font-size: 13px; }
    .table-dark td { padding: 10px; border-bottom: 1px solid #2a2a2a; color: #fff; font-size: 14px; }
    
    .crud-form { background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #333; }
    .crud-form input, .crud-form textarea { width: 100%; padding: 8px; background: #222; border: 1px solid #444; color: #fff; border-radius: 4px; margin-top: 5px; margin-bottom: 10px; }

    /* Tabs */
    .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #333; }
    .tab-btn { background: transparent; color: #aaa; border: none; padding: 10px 20px; cursor: pointer; font-size: 15px; border-bottom: 2px solid transparent; }
    .tab-btn.active { color: #38bdf8; border-bottom: 2px solid #38bdf8; font-weight: 600; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .trans-box { background: rgba(0,0,0,0.15); padding: 10px; border-radius: 6px; margin-bottom: 10px; border: 1px solid #2a2a2a; }
    .trans-box label { font-size: 11px; text-transform: uppercase; color: #888; font-weight: bold; }
</style>

<?php include 'includes/sidebar.php'; ?>

<main style="flex:1; padding:30px; overflow-y:auto;">
    <h1 class="page-title"><i class="fas fa-globe" style="color: #38bdf8; margin-right: 10px;"></i> Global</h1>
    
    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('tab-config')"><i class="fas fa-cogs"></i> Configurações & Rankings</button>
        <button class="tab-btn" onclick="switchTab('tab-welcome')"><i class="fas fa-door-open"></i> Boas-Vindas</button>
    </div>

    <!-- TAB: BOAS VINDAS -->
    <div id="tab-welcome" class="tab-content">
        <div class="card">
            <h3 style="margin-top:0; color:#fff;">🎉 Mensagens de Boas-Vindas</h3>
            <p style="color: var(--text-dim); font-size: 14px;">O bot envia uma mensagem automaticamente para novos membros com uma saudação e 3 perguntas sorteadas.</p>
            
            <!-- Toggles por grupo -->
            <h4 style="color:#38bdf8; margin-top: 25px; border-bottom: 1px solid #333; padding-bottom: 5px;">Ativação por Grupo</h4>
            <form method="POST">
                <table class="table-dark">
                    <tr>
                        <th>Grupo</th>
                        <th>Idioma</th>
                        <th>Ativar Boas-Vindas</th>
                    </tr>
                    <?php foreach ($allGlobalGroups as $g): ?>
                    <tr>
                        <td><?= htmlspecialchars($g['nome']) ?></td>
                        <td><span style="background:#333; padding:2px 6px; border-radius:4px; font-size:12px; text-transform:uppercase;"><?= htmlspecialchars($g['lang_code']) ?></span></td>
                        <td>
                            <label class="switch" style="position:relative; display:inline-block; width:40px; height:20px;">
                                <input type="checkbox" name="welcome_enabled[<?= $g['jid'] ?>]" value="1" <?= $g['welcome_enabled'] ? 'checked' : '' ?> style="opacity:0; width:0; height:0;">
                                <span class="slider round" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#ccc; transition:.4s; border-radius:20px;"></span>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <button type="submit" name="save_toggles" class="btn-primary" style="margin-top:15px; font-size:13px; padding:6px 12px;"><i class="fas fa-save"></i> Salvar Toggles</button>
            </form>
        </div>

        <div class="card">
            <!-- Saudações Iniciais -->
            <h4 style="color:#38bdf8; margin-top: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;">Pool de Saudações (Intros)</h4>
            <p style="color: var(--text-dim); font-size: 13px;">Uma dessas saudações será sorteada. Use <code>{mentions}</code> onde os membros devem ser marcados. O 'Texto Base' é apenas para sua referência.</p>
            
            <form method="POST" class="crud-form">
                <div style="display:flex; gap:15px;">
                    <div style="flex:1;">
                        <label style="font-size:12px; color:#aaa;">Texto Base (Ref)</label>
                        <textarea name="text_target" rows="2" required placeholder="Ex: Olá {mentions}! Bem-vindos!"></textarea>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px; color:#aaa;">Fallback em Inglês (Para todos os grupos sem tradução)</label>
                        <textarea name="text_en" rows="2" required placeholder="Ex: Hello {mentions}! Welcome!"></textarea>
                    </div>
                </div>
                <button type="submit" name="add_intro" class="btn-primary btn-success" style="font-size:12px; padding:6px 10px;"><i class="fas fa-plus"></i> Adicionar Saudação</button>
            </form>

            <table class="table-dark">
                <?php foreach ($introsList as $i): ?>
                <tr>
                    <td style="width:50px; vertical-align: top; padding-top: 15px;">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="id" value="<?= $i['id'] ?>">
                            <button type="submit" name="toggle_intro" class="btn-small <?= $i['ativo'] ? 'btn-success' : 'btn-danger' ?>" title="Clique para alternar">
                                <?= $i['ativo'] ? 'ON' : 'OFF' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="margin:0; display:flex; gap:10px; align-items:flex-start;">
                            <input type="hidden" name="id" value="<?= $i['id'] ?>">
                            <div style="flex:1;">
                                <label style="font-size:10px; color:#888;">Texto Base</label>
                                <textarea name="text_target" style="width:100%; height:40px; padding:5px; background:transparent; border:1px solid #444; color:#fff;" required><?= htmlspecialchars($i['text_target']) ?></textarea>
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:10px; color:#888;">Inglês (Fallback)</label>
                                <textarea name="text_en" style="width:100%; height:40px; padding:5px; background:transparent; border:1px solid #444; color:#fff;" required><?= htmlspecialchars($i['text_en']) ?></textarea>
                            </div>
                            <button type="submit" name="edit_intro" class="btn-small" style="margin-top:18px;"><i class="fas fa-save"></i></button>
                        </form>
                        
                        <!-- Traduções -->
                        <div style="margin-top: 10px; padding-left: 10px; border-left: 2px solid #333;">
                            <div style="font-size:11px; color:#aaa; margin-bottom: 5px;"><i class="fas fa-language"></i> Traduções para Idiomas Ativos:</div>
                            <?php foreach ($activeLangs as $lc): 
                                $t_text = $translations['intro'][$i['id']][$lc] ?? '';
                            ?>
                                <form method="POST" class="trans-box" style="display:flex; gap:10px; align-items:center; margin:0 0 5px 0;">
                                    <input type="hidden" name="entity_type" value="intro">
                                    <input type="hidden" name="entity_id" value="<?= $i['id'] ?>">
                                    <input type="hidden" name="lang_code" value="<?= $lc ?>">
                                    <div style="width: 30px; font-weight:bold; color:#38bdf8;"><?= strtoupper($lc) ?></div>
                                    <input type="text" name="text" value="<?= htmlspecialchars($t_text) ?>" placeholder="Sem tradução (usará inglês)..." style="flex:1; padding:4px; background:#111; border:1px solid #333; color:#fff; font-size:12px;">
                                    <button type="submit" name="save_translation" class="btn-small" style="padding:4px 8px;"><i class="fas fa-save"></i></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <!-- Pool de Perguntas -->
            <h4 style="color:#38bdf8; margin-top: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;">Pool de Perguntas</h4>
            <p style="color: var(--text-dim); font-size: 13px;">Sortearemos 3 perguntas a cada envio. O 'Texto Base' é apenas para sua referência.</p>
            
            <form method="POST" class="crud-form">
                <div style="display:flex; gap:15px;">
                    <div style="flex:1;">
                        <label style="font-size:12px; color:#aaa;">Texto Base (Ref)</label>
                        <textarea name="text_target" rows="2" required></textarea>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px; color:#aaa;">Inglês (Fallback)</label>
                        <textarea name="text_en" rows="2" required></textarea>
                    </div>
                </div>
                <button type="submit" name="add_question" class="btn-primary btn-success" style="font-size:12px; padding:6px 10px;"><i class="fas fa-plus"></i> Adicionar Pergunta</button>
            </form>

            <table class="table-dark">
                <?php foreach ($qsList as $q): ?>
                <tr>
                    <td style="width:50px; vertical-align: top; padding-top: 15px;">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                            <button type="submit" name="toggle_question" class="btn-small <?= $q['ativo'] ? 'btn-success' : 'btn-danger' ?>" title="Clique para alternar">
                                <?= $q['ativo'] ? 'ON' : 'OFF' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="margin:0; display:flex; gap:10px; align-items:flex-start;">
                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                            <div style="flex:1;">
                                <label style="font-size:10px; color:#888;">Texto Base</label>
                                <textarea name="text_target" style="width:100%; height:40px; padding:5px; background:transparent; border:1px solid #444; color:#fff;" required><?= htmlspecialchars($q['text_target']) ?></textarea>
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:10px; color:#888;">Inglês (Fallback)</label>
                                <textarea name="text_en" style="width:100%; height:40px; padding:5px; background:transparent; border:1px solid #444; color:#fff;" required><?= htmlspecialchars($q['text_en']) ?></textarea>
                            </div>
                            <button type="submit" name="edit_question" class="btn-small" style="margin-top:18px;"><i class="fas fa-save"></i></button>
                        </form>
                        
                        <!-- Traduções -->
                        <div style="margin-top: 10px; padding-left: 10px; border-left: 2px solid #333;">
                            <div style="font-size:11px; color:#aaa; margin-bottom: 5px;"><i class="fas fa-language"></i> Traduções para Idiomas Ativos:</div>
                            <?php foreach ($activeLangs as $lc): 
                                $t_text = $translations['question'][$q['id']][$lc] ?? '';
                            ?>
                                <form method="POST" class="trans-box" style="display:flex; gap:10px; align-items:center; margin:0 0 5px 0;">
                                    <input type="hidden" name="entity_type" value="question">
                                    <input type="hidden" name="entity_id" value="<?= $q['id'] ?>">
                                    <input type="hidden" name="lang_code" value="<?= $lc ?>">
                                    <div style="width: 30px; font-weight:bold; color:#38bdf8;"><?= strtoupper($lc) ?></div>
                                    <input type="text" name="text" value="<?= htmlspecialchars($t_text) ?>" placeholder="Sem tradução (usará inglês)..." style="flex:1; padding:4px; background:#111; border:1px solid #333; color:#fff; font-size:12px;">
                                    <button type="submit" name="save_translation" class="btn-small" style="padding:4px 8px;"><i class="fas fa-save"></i></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- TAB: CONFIGURAÇÕES E RANKINGS -->
    <div id="tab-config" class="tab-content active">
        <!-- MONITORAMENTO DE ATIVIDADE -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div>
                    <h3 style="margin-top:0; margin-bottom:0; color:#fff;">Monitoramento de Atividade (Hoje)</h3>
                    <p style="color: var(--text-dim); margin-top:5px; margin-bottom:0; font-size: 14px;">Abaixo você pode acompanhar quantas mensagens e reações cada participante já enviou hoje (<b><?= date('d/m/Y') ?></b>) nos grupos globais.</p>
                </div>
                <a href="comunidade_global.php" class="btn-primary" style="font-size: 13px; padding: 8px 12px; background: #10b981;">
                    <i class="fas fa-sync-alt"></i> Atualizar Placar
                </a>
            </div>

            <?php
            $hoje = date('Y-m-d');
            $activityToday = fetchCommunityActivity($hoje);

            if (empty($activityToday)) {
                echo "<div class='alert alert-warning'>Nenhuma atividade registrada ainda para o dia de hoje ($hoje). Se o robô acabou de ser configurado, aguarde os alunos começarem a enviar mensagens.</div>";
            } else {
                $globalGroupNames = [];
                foreach (($config['groups'] ?? []) as $g) {
                    if (!empty($g['jid']) && !empty($g['is_community_group'])) {
                        $globalGroupNames[$g['jid']] = $g['name'] ?? 'Grupo Global Desconhecido';
                    }
                }

                echo "<div style='display:flex; flex-wrap:wrap; gap:20px; margin-top:20px;'>";
                $hasGlobalActivity = false;
                foreach ($activityToday as $groupJid => $members) {
                    if (!isset($globalGroupNames[$groupJid])) continue;
                    
                    $gName = $globalGroupNames[$groupJid];
                    if (empty($members)) continue;
                    $hasGlobalActivity = true;
                    
                    uasort($members, function($a, $b) {
                        $totalA = ($a['messages'] ?? 0) + ($a['reactions_given'] ?? 0) + ($a['images_sent'] ?? 0) + ($a['audios_sent'] ?? 0);
                        $totalB = ($b['messages'] ?? 0) + ($b['reactions_given'] ?? 0) + ($b['images_sent'] ?? 0) + ($b['audios_sent'] ?? 0);
                        return $totalB <=> $totalA;
                    });

                    echo "<div style='background:#1e1e1e; padding:15px; border-radius:8px; border:1px solid #333; flex: 1 1 300px;'>";
                    echo "<h3 style='margin-top:0; color:#38bdf8; font-size:16px;'><i class='fas fa-comments' style='margin-right:8px;'></i> {$gName}</h3>";
                    
                    echo "<table style='width:100%; border-collapse:collapse; margin-top:10px;'>";
                    echo "<tr style='border-bottom:1px solid #444; color:#aaa; font-size:12px;'>
                            <th style='text-align:left; padding:5px 0;'>Participante</th>
                            <th style='text-align:center; padding:5px 0;'>💬 Msgs</th>
                            <th style='text-align:center; padding:5px 0;'>❤️ Reacts</th>
                          </tr>";
                          
                    foreach ($members as $jid => $data) {
                        $nome = $data['name'] ?? 'Desconhecido';
                        $msgs = ($data['messages'] ?? 0) + ($data['images_sent'] ?? 0) + ($data['audios_sent'] ?? 0);
                        $reacts = $data['reactions_given'] ?? 0;
                        
                        $isAdminMarker = '';
                        $adminJidFallback = $config['admin_jid'] ?? '556192666148@s.whatsapp.net';
                        if (!empty($adminJidFallback) && strpos($jid, preg_replace('/:\d+@/', '@', $adminJidFallback)) !== false) {
                            $isAdminMarker = ' <span style="font-size:10px; background:#444; padding:2px 4px; border-radius:4px;">Admin</span>';
                        }

                        echo "<tr style='border-bottom:1px solid #2a2a2a;'>";
                        echo "<td style='padding:8px 0; font-size:14px;'>" . htmlspecialchars($nome) . $isAdminMarker . "</td>";
                        echo "<td style='padding:8px 0; font-size:14px; text-align:center; color:#10b981; font-weight:bold;'>{$msgs}</td>";
                        echo "<td style='padding:8px 0; font-size:14px; text-align:center; color:#f43f5e; font-weight:bold;'>{$reacts}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    echo "</div>";
                }
                if (!$hasGlobalActivity) {
                    echo "<div class='alert alert-warning' style='width: 100%;'>Nenhuma atividade registrada ainda nos grupos globais para hoje.</div>";
                }
                echo "</div>";
            }
            ?>
        </div>

        <!-- CONFIGURAÇÕES DE TEMPLATES -->
        <div class="card">
            <h3 style="margin-top:0; color:#fff;">Templates e Sincronização</h3>
            <p style="color: var(--text-dim); margin-top:5px; font-size: 14px;">Defina os templates globais usados pelo bot (Rankings e Estrutura de Boas-Vindas) e salve para forçar a sincronização.</p>
            
            <form method="POST" style="margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 5px; font-weight:600;">Template Geral de Boas-Vindas (Estrutura)</label>
                    <textarea name="tpl_welcome_general" style="width:100%; min-height:100px; background:var(--input-bg); color:#fff; border:1px solid #333; border-radius:6px; padding:10px; font-family:monospace;"><?= htmlspecialchars($tpl_welcome_general) ?></textarea>
                    <small style="color:var(--text-dim);">Variáveis obrigatórias: <code>{intro_text}</code> (saudação) e <code>{questions_text}</code> (lista de perguntas formatadas).</small>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 5px; font-weight:600;">Template: Ranking Top Mensagens</label>
                    <textarea name="tpl_messenger" style="width:100%; min-height:150px; background:var(--input-bg); color:#fff; border:1px solid #333; border-radius:6px; padding:10px; font-family:monospace;"><?= htmlspecialchars($tpl_messenger) ?></textarea>
                    <small style="color:var(--text-dim);">Variáveis: <code>{group_name}</code>, <code>{date}</code>, <code>{msg_ranking_list}</code></small>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 5px; font-weight:600;">Template: Ranking Top Reações</label>
                    <textarea name="tpl_reactor" style="width:100%; min-height:150px; background:var(--input-bg); color:#fff; border:1px solid #333; border-radius:6px; padding:10px; font-family:monospace;"><?= htmlspecialchars($tpl_reactor) ?></textarea>
                    <small style="color:var(--text-dim);">Variáveis: <code>{group_name}</code>, <code>{date}</code>, <code>{react_ranking_list}</code></small>
                </div>

                <button type="submit" name="sync_global_config" class="btn-primary" style="margin-top: 10px;">
                    <i class="fas fa-save"></i> Salvar Templates e Sincronizar Robô
                </button>
            </form>
        </div>
    </div>
</main>
<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
        localStorage.setItem('active_tab_global', tabId);
    }
    // Restaurar tab ativa
    const savedTab = localStorage.getItem('active_tab_global');
    if(savedTab) {
        document.querySelector(`.tab-btn[onclick="switchTab('${savedTab}')"]`)?.click();
    }
</script>
<style>
/* Estilo para os switches */
.switch input:checked + .slider { background-color: #10b981; }
.switch input:focus + .slider { box-shadow: 0 0 1px #10b981; }
.switch input:checked + .slider:before { transform: translateX(20px); }
.slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
</style>
</body>
</html>
