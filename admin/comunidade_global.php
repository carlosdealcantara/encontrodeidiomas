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

// --- AÇÕES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Toggles por grupo
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

    // INTROS
    if (isset($_POST['add_intro'])) {
        $stmt = $conn->prepare("INSERT INTO community_welcome_intros (text_target, text_en) VALUES (?, ?)");
        $stmt->execute([trim($_POST['text_target']), trim($_POST['text_en'])]);
        $msg = "Saudação adicionada.";
    }
    if (isset($_POST['edit_intro'])) {
        $stmt = $conn->prepare("UPDATE community_welcome_intros SET text_target = ?, text_en = ? WHERE id = ?");
        $stmt->execute([trim($_POST['text_target']), trim($_POST['text_en']), (int)$_POST['id']]);
        $msg = "Saudação atualizada.";
    }
    if (isset($_POST['toggle_intro'])) {
        $stmt = $conn->prepare("UPDATE community_welcome_intros SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $msg = "Status da saudação alterado.";
    }
    if (isset($_POST['delete_intro'])) {
        $id = (int)$_POST['id'];
        $conn->prepare("DELETE FROM community_welcome_translations WHERE entity_type = 'intro' AND entity_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM community_welcome_intros WHERE id = ?")->execute([$id]);
        $msg = "Saudação excluída.";
    }

    // PERGUNTAS
    if (isset($_POST['add_question'])) {
        $stmt = $conn->prepare("INSERT INTO community_welcome_questions (text_target, text_en) VALUES (?, ?)");
        $stmt->execute([trim($_POST['text_target']), trim($_POST['text_en'])]);
        $msg = "Pergunta adicionada.";
    }
    if (isset($_POST['edit_question'])) {
        $stmt = $conn->prepare("UPDATE community_welcome_questions SET text_target = ?, text_en = ? WHERE id = ?");
        $stmt->execute([trim($_POST['text_target']), trim($_POST['text_en']), (int)$_POST['id']]);
        $msg = "Pergunta atualizada.";
    }
    if (isset($_POST['toggle_question'])) {
        $stmt = $conn->prepare("UPDATE community_welcome_questions SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $msg = "Status da pergunta alterado.";
    }
    if (isset($_POST['delete_question'])) {
        $id = (int)$_POST['id'];
        $conn->prepare("DELETE FROM community_welcome_translations WHERE entity_type = 'question' AND entity_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM community_welcome_questions WHERE id = ?")->execute([$id]);
        $msg = "Pergunta excluída.";
    }

    // TRADUÇÃO
    if (isset($_POST['save_translation'])) {
        $stmt = $conn->prepare("INSERT INTO community_welcome_translations (entity_type, entity_id, lang_code, text) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE text = ?");
        $text = trim($_POST['text']);
        $stmt->execute([$_POST['entity_type'], (int)$_POST['entity_id'], $_POST['lang_code'], $text, $text]);
        $msg = "Tradução salva.";
    }

    // SINCRONIZAR CONFIG COM ROBÔ
    if (isset($_POST['sync_global_config'])) {
        try {
            $config = getCommunityConfig();
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
            if (isset($_POST['tpl_messenger'])) $config['templates']['community_ranking_messenger'] = trim($_POST['tpl_messenger']);
            if (isset($_POST['tpl_reactor'])) $config['templates']['community_ranking_reactor'] = trim($_POST['tpl_reactor']);
            if (isset($_POST['tpl_welcome_general'])) $config['templates']['community_welcome_general'] = trim($_POST['tpl_welcome_general']);
            $config['admin_jid'] = "556192666148@s.whatsapp.net";
            $res = sendBaileysRequest('/community-config', $config, 'POST');
            if ($res['success']) {
                $msg = "Configurações sincronizadas com o robô!";
            } else {
                $error = "Erro ao sincronizar: " . ($res['error'] ?? 'Desconhecido');
            }
        } catch (Exception $e) {
            $error = "Erro: " . $e->getMessage();
        }
    }
}

// Lê dados
$config = getCommunityConfig();
$tpl_messenger      = $config['templates']['community_ranking_messenger'] ?? "📊 *DAILY RANKING — {group_name}*\n📅 _{date}_\n━━━━━━━━━━━━━━━━━━━━━━\n\n💬 *TOP TALKERS*\n_Who sent the most messages today?_\n\n{msg_ranking_list}\n\n━━━━━━━━━━━━━━━━━━━━━━\n✨ _Keep the conversation going! Tomorrow's ranking starts now._ 🚀";
$tpl_reactor        = $config['templates']['community_ranking_reactor'] ?? "❤️ *REACTION STARS — {group_name}*\n📅 _{date}_\n━━━━━━━━━━━━━━━━━━━━━━\n\n_Who spread the most love today?_\n\n{react_ranking_list}\n\n━━━━━━━━━━━━━━━━━━━━━━\n_React to others and climb the ranking! 🙌_";
$tpl_welcome_general = $config['templates']['community_welcome_general'] ?? "{intro_text}\n\nWe'd love to get to know you! Tell us:\n\n{questions_text}";

$stmtGroups = $conn->query("SELECT group_id as jid, nome, lang_code, welcome_enabled FROM meetup_whatsapp_groups WHERE comunidade = 'global' AND ativo = 1 ORDER BY nome ASC");
$allGlobalGroups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

$stmtIntros = $conn->query("SELECT * FROM community_welcome_intros ORDER BY id ASC");
$introsList = $stmtIntros->fetchAll(PDO::FETCH_ASSOC);

$stmtQs = $conn->query("SELECT * FROM community_welcome_questions ORDER BY id ASC");
$qsList = $stmtQs->fetchAll(PDO::FETCH_ASSOC);

$translationsRaw = $conn->query("SELECT * FROM community_welcome_translations")->fetchAll(PDO::FETCH_ASSOC);
$translations = [];
foreach ($translationsRaw as $tr) {
    $translations[$tr['entity_type']][$tr['entity_id']][$tr['lang_code']] = $tr['text'];
}

$activeLangs = $conn->query("SELECT DISTINCT lang_code FROM meetup_whatsapp_groups WHERE comunidade = 'global' AND ativo = 1 AND lang_code != 'en'")->fetchAll(PDO::FETCH_COLUMN);

// Nomes legíveis dos idiomas
$langNames = [
    'es' => 'Español', 'pt' => 'Português', 'de' => 'Deutsch',
    'ru' => 'Русский', 'ja' => '日本語', 'zh' => '中文',
    'id' => 'Bahasa', 'it' => 'Italiano',
];

$title = 'Global - Admin';
$current_page = 'comunidade_global.php';
include 'includes/header.php';
?>

<style>
    :root {
        --tab-active: #38bdf8;
        --tab-bg: transparent;
        --sub-active: #a78bfa;
        --danger: #ef4444;
        --success: #10b981;
        --warn: #f59e0b;
        --card-radius: 14px;
    }
    .page-title { color: #fff; font-size: 26px; font-weight: 700; margin-bottom: 22px; }
    .card { background: var(--card-bg); padding: 24px; border-radius: var(--card-radius); border: 1px solid rgba(255,255,255,0.06); margin-bottom: 22px; }
    .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .alert-success { background: rgba(16,185,129,.1); color: var(--success); border: 1px solid rgba(16,185,129,.25); }
    .alert-danger  { background: rgba(239,68,68,.1);  color: var(--danger);  border: 1px solid rgba(239,68,68,.25); }
    .alert-warning { background: rgba(245,158,11,.1); color: var(--warn);   border: 1px solid rgba(245,158,11,.25); }

    /* === PRIMARY TABS === */
    .tabs { display: flex; gap: 6px; margin-bottom: 24px; border-bottom: 2px solid #2a2a2a; padding-bottom: 0; }
    .tab-btn {
        background: transparent; color: #888; border: none;
        padding: 12px 22px; cursor: pointer; font-size: 15px; font-weight: 500;
        border-bottom: 3px solid transparent; margin-bottom: -2px;
        display: flex; align-items: center; gap: 8px; transition: color .2s;
    }
    .tab-btn:hover { color: #ccc; }
    .tab-btn.active { color: var(--tab-active); border-bottom-color: var(--tab-active); font-weight: 700; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* === SUB TABS (dentro de boas-vindas) === */
    .sub-tabs { display: flex; gap: 6px; margin-bottom: 20px; }
    .sub-tab-btn {
        background: #1e1e1e; color: #999; border: 1px solid #333;
        padding: 9px 18px; cursor: pointer; font-size: 14px; border-radius: 8px;
        display: flex; align-items: center; gap: 7px; transition: all .2s;
    }
    .sub-tab-btn:hover { background: #252525; color: #ccc; }
    .sub-tab-btn.active { background: rgba(167,139,250,.15); color: var(--sub-active); border-color: var(--sub-active); font-weight: 600; }
    .sub-tab-content { display: none; }
    .sub-tab-content.active { display: block; }

    /* === BOTÕES === */
    .btn { border: none; border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 7px; transition: filter .15s; }
    .btn:hover { filter: brightness(1.15); }
    .btn-primary { background: var(--tab-active); color: #fff; padding: 10px 20px; font-size: 14px; }
    .btn-success { background: var(--success); color: #fff; }
    .btn-danger  { background: var(--danger);  color: #fff; }
    .btn-sm      { padding: 6px 12px; font-size: 13px; }
    .btn-xs      { padding: 4px 9px; font-size: 12px; border-radius: 5px; }
    .btn-ghost   { background: #333; color: #ccc; }
    .btn-ghost:hover { background: #444; }

    /* === TABELA === */
    .tbl { width: 100%; border-collapse: collapse; }
    .tbl th { text-align: left; padding: 10px 12px; border-bottom: 2px solid #333; color: #999; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
    .tbl td { padding: 12px 12px; border-bottom: 1px solid #222; color: #ddd; font-size: 14px; vertical-align: top; }
    .tbl tr:last-child td { border-bottom: none; }
    .tbl tr:hover td { background: rgba(255,255,255,.02); }

    /* === FORM ADD === */
    .form-add { background: rgba(56,189,248,.05); padding: 18px; border-radius: 10px; margin-bottom: 18px; border: 1px dashed #38bdf844; }
    .form-add label { font-size: 12px; color: #888; display: block; margin-bottom: 4px; }
    .form-add textarea, .form-add input[type=text] {
        width: 100%; padding: 9px 12px; background: #181818; border: 1px solid #3a3a3a;
        color: #fff; border-radius: 6px; font-size: 13px; resize: vertical;
    }

    /* === TRADUÇÕES === */
    .trans-section { margin-top: 14px; padding: 12px 14px; background: rgba(0,0,0,.2); border-radius: 8px; border-left: 3px solid #333; }
    .trans-section-title { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
    .trans-row { display: flex; gap: 8px; align-items: center; margin-bottom: 7px; }
    .trans-lang-badge { width: 80px; flex-shrink: 0; }
    .lang-badge { display: inline-block; padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: 700; background: #1a2a3a; color: #38bdf8; border: 1px solid #1d4e77; }
    .lang-badge-name { font-size: 10px; color: #666; margin-top: 1px; }
    .trans-row input { flex: 1; padding: 7px 10px; background: #111; border: 1px solid #2a2a2a; color: #fff; border-radius: 5px; font-size: 13px; }
    .trans-row input:focus { border-color: #38bdf8; outline: none; }
    .trans-row input.has-value { border-color: #1d4e77; background: #0d1a26; }

    /* === SWITCH === */
    .switch { position: relative; display: inline-block; width: 44px; height: 22px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #555; transition: .3s; border-radius: 22px; }
    .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background: #fff; transition: .3s; border-radius: 50%; }
    input:checked + .slider { background: var(--success); }
    input:checked + .slider:before { transform: translateX(22px); }

    /* === MISC === */
    .badge-on  { background: rgba(16,185,129,.15); color: var(--success); border: 1px solid rgba(16,185,129,.3); padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; }
    .badge-off { background: rgba(239,68,68,.12);  color: var(--danger);  border: 1px solid rgba(239,68,68,.3);  padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; }
    .item-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 16px; margin-bottom: 14px; }
    .item-card:hover { border-color: #3a3a3a; }
    .item-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #222; border-radius: 50%; font-size: 13px; font-weight: 700; color: #aaa; flex-shrink: 0; }
    .edit-textarea { width: 100%; padding: 9px 12px; background: #111; border: 1px solid #333; color: #fff; border-radius: 6px; font-size: 14px; resize: vertical; line-height: 1.5; }
    .edit-textarea:focus { border-color: #38bdf8; outline: none; }
    .coverage-badge { font-size: 12px; padding: 4px 10px; border-radius: 20px; font-weight: 600; }
    .coverage-full { background: rgba(16,185,129,.15); color: var(--success); border: 1px solid rgba(16,185,129,.3); }
    .coverage-partial { background: rgba(245,158,11,.12); color: var(--warn); border: 1px solid rgba(245,158,11,.3); }
</style>

<?php include 'includes/sidebar.php'; ?>

<main style="flex:1; padding:30px; overflow-y:auto;">
    <h1 class="page-title"><i class="fas fa-globe" style="color:#38bdf8; margin-right:10px;"></i> Global</h1>

    <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- TABS PRINCIPAIS -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('tab-config', this)"><i class="fas fa-chart-bar"></i> Rankings & Config</button>
        <button class="tab-btn" onclick="switchTab('tab-welcome', this)"><i class="fas fa-door-open"></i> Boas-Vindas</button>
    </div>

    <!-- ===================== TAB: BOAS-VINDAS ===================== -->
    <div id="tab-welcome" class="tab-content">
        <!-- Sub-tabs -->
        <div class="sub-tabs">
            <button class="sub-tab-btn active" onclick="switchSubTab('sub-toggles', this)"><i class="fas fa-toggle-on"></i> Ativação por Grupo</button>
            <button class="sub-tab-btn" onclick="switchSubTab('sub-intros', this)"><i class="fas fa-comment-dots"></i> Pool de Saudações</button>
            <button class="sub-tab-btn" onclick="switchSubTab('sub-questions', this)"><i class="fas fa-question-circle"></i> Pool de Perguntas</button>
        </div>

        <!-- SUB: Ativação -->
        <div id="sub-toggles" class="sub-tab-content active">
            <div class="card">
                <h3 style="margin:0 0 6px; color:#fff; font-size:18px;"><i class="fas fa-toggle-on" style="color:var(--sub-active); margin-right:8px;"></i> Ativação por Grupo</h3>
                <p style="color:#777; font-size:13px; margin:0 0 20px;">Selecione em quais grupos o bot deve enviar a mensagem de boas-vindas automaticamente.</p>
                <form method="POST">
                    <table class="tbl">
                        <tr>
                            <th>Grupo</th>
                            <th>Idioma</th>
                            <th style="text-align:center;">Boas-Vindas Ativo</th>
                        </tr>
                        <?php foreach ($allGlobalGroups as $g): ?>
                        <tr>
                            <td style="font-weight:500;"><?= htmlspecialchars($g['nome']) ?></td>
                            <td>
                                <span style="background:#1a2a3a; color:#38bdf8; padding:3px 9px; border-radius:5px; font-size:12px; font-weight:700; text-transform:uppercase;">
                                    <?= htmlspecialchars($g['lang_code']) ?>
                                </span>
                                <span style="color:#555; font-size:12px; margin-left:6px;"><?= $langNames[$g['lang_code']] ?? '' ?></span>
                            </td>
                            <td style="text-align:center;">
                                <label class="switch">
                                    <input type="checkbox" name="welcome_enabled[<?= $g['jid'] ?>]" value="1" <?= $g['welcome_enabled'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <button type="submit" name="save_toggles" class="btn btn-primary btn-sm" style="margin-top:18px;">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                </form>
            </div>
        </div>

        <!-- SUB: Pool de Saudações -->
        <div id="sub-intros" class="sub-tab-content">
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px;">
                    <div>
                        <h3 style="margin:0 0 5px; color:#fff; font-size:18px;"><i class="fas fa-comment-dots" style="color:var(--sub-active); margin-right:8px;"></i> Pool de Saudações</h3>
                        <p style="color:#777; font-size:13px; margin:0;">Uma dessas frases será sorteada para abrir a mensagem. Use <code style="background:#222; padding:2px 6px; border-radius:4px; font-size:12px;">{mentions}</code> onde os nomes aparecem.</p>
                    </div>
                </div>

                <!-- Form Adicionar -->
                <div class="form-add" style="margin-bottom:22px;">
                    <p style="margin:0 0 12px; font-size:13px; color:#aaa; font-weight:600;"><i class="fas fa-plus-circle" style="color:var(--success);"></i> Adicionar nova saudação</p>
                    <form method="POST">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:12px;">
                            <div>
                                <label>📝 Texto Base (referência sua)</label>
                                <textarea name="text_target" rows="3" required placeholder="Ex: Oba, gente nova! 🥳 Nossas boas-vindas, {mentions}!"></textarea>
                            </div>
                            <div>
                                <label>🇺🇸 Inglês (Original/Fallback)</label>
                                <textarea name="text_en" rows="3" required placeholder="Ex: Look who just joined! 🥳 Welcome, {mentions}!"></textarea>
                            </div>
                        </div>
                        <button type="submit" name="add_intro" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Adicionar Saudação</button>
                    </form>
                </div>

                <!-- Lista de intros -->
                <?php 
                $totalLangs = count($activeLangs);
                foreach ($introsList as $idx => $i): 
                    $coveredLangs = 0;
                    foreach ($activeLangs as $lc) {
                        if (!empty($translations['intro'][$i['id']][$lc])) $coveredLangs++;
                    }
                    $isFull = $coveredLangs >= $totalLangs;
                ?>
                <div class="item-card">
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <div class="item-number"><?= $idx + 1 ?></div>
                        <div style="flex:1;">
                            <!-- Header da intro -->
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                    <button type="submit" name="toggle_intro" class="btn btn-xs <?= $i['ativo'] ? 'btn-success' : 'btn-danger btn-ghost' ?>">
                                        <?= $i['ativo'] ? '<i class="fas fa-eye"></i> Ativa' : '<i class="fas fa-eye-slash"></i> Inativa' ?>
                                    </button>
                                </form>
                                <span class="coverage-badge <?= $isFull ? 'coverage-full' : 'coverage-partial' ?>">
                                    <i class="fas fa-language"></i> <?= $coveredLangs ?>/<?= $totalLangs ?> idiomas traduzidos
                                </span>
                                <form method="POST" style="margin:0; margin-left:auto;" onsubmit="return confirm('Excluir esta saudação e todas as suas traduções? Esta ação não pode ser desfeita.')">
                                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                    <button type="submit" name="delete_intro" class="btn btn-xs btn-danger"><i class="fas fa-trash-alt"></i> Excluir</button>
                                </form>
                            </div>

                            <!-- Edição dos textos base -->
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:10px;">
                                    <div>
                                        <label style="font-size:11px; color:#666; display:block; margin-bottom:4px;">📝 Texto Base</label>
                                        <textarea name="text_target" class="edit-textarea" rows="3" required><?= htmlspecialchars($i['text_target']) ?></textarea>
                                    </div>
                                    <div>
                                        <label style="font-size:11px; color:#666; display:block; margin-bottom:4px;">🇺🇸 Inglês (Original/Fallback)</label>
                                        <textarea name="text_en" class="edit-textarea" rows="3" required><?= htmlspecialchars($i['text_en']) ?></textarea>
                                    </div>
                                </div>
                                <button type="submit" name="edit_intro" class="btn btn-ghost btn-xs"><i class="fas fa-save"></i> Salvar alterações nos textos acima</button>
                            </form>

                            <!-- Traduções por idioma -->
                            <?php if (!empty($activeLangs)): ?>
                            <div class="trans-section">
                                <div class="trans-section-title"><i class="fas fa-language"></i> Traduções para idiomas ativos</div>
                                <?php foreach ($activeLangs as $lc): 
                                    $t_text = $translations['intro'][$i['id']][$lc] ?? '';
                                    $hasVal = !empty($t_text);
                                ?>
                                <form method="POST" class="trans-row">
                                    <input type="hidden" name="entity_type" value="intro">
                                    <input type="hidden" name="entity_id" value="<?= $i['id'] ?>">
                                    <input type="hidden" name="lang_code" value="<?= $lc ?>">
                                    <div class="trans-lang-badge">
                                        <div class="lang-badge"><?= strtoupper($lc) ?></div>
                                        <div class="lang-badge-name"><?= $langNames[$lc] ?? $lc ?></div>
                                    </div>
                                    <input type="text" name="text" value="<?= htmlspecialchars($t_text) ?>" 
                                           placeholder="Sem tradução (usará inglês como fallback)..." 
                                           class="<?= $hasVal ? 'has-value' : '' ?>">
                                    <button type="submit" name="save_translation" class="btn btn-ghost btn-xs"><i class="fas fa-save"></i> Salvar</button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SUB: Pool de Perguntas -->
        <div id="sub-questions" class="sub-tab-content">
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px;">
                    <div>
                        <h3 style="margin:0 0 5px; color:#fff; font-size:18px;"><i class="fas fa-question-circle" style="color:var(--sub-active); margin-right:8px;"></i> Pool de Perguntas</h3>
                        <p style="color:#777; font-size:13px; margin:0;">3 perguntas são sorteadas aleatoriamente a cada boas-vindas. A ordem também varia entre envios.</p>
                    </div>
                </div>

                <!-- Form Adicionar -->
                <div class="form-add" style="margin-bottom:22px;">
                    <p style="margin:0 0 12px; font-size:13px; color:#aaa; font-weight:600;"><i class="fas fa-plus-circle" style="color:var(--success);"></i> Adicionar nova pergunta</p>
                    <form method="POST">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:12px;">
                            <div>
                                <label>📝 Texto Base (referência sua)</label>
                                <textarea name="text_target" rows="2" required placeholder="Ex: Qual é o seu hobby favorito?"></textarea>
                            </div>
                            <div>
                                <label>🇺🇸 Inglês (Original/Fallback)</label>
                                <textarea name="text_en" rows="2" required placeholder="Ex: What's your favorite hobby?"></textarea>
                            </div>
                        </div>
                        <button type="submit" name="add_question" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Adicionar Pergunta</button>
                    </form>
                </div>

                <!-- Lista de perguntas -->
                <?php foreach ($qsList as $idx => $q): 
                    $coveredLangs = 0;
                    foreach ($activeLangs as $lc) {
                        if (!empty($translations['question'][$q['id']][$lc])) $coveredLangs++;
                    }
                    $isFull = $coveredLangs >= $totalLangs;
                ?>
                <div class="item-card">
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <div class="item-number"><?= $idx + 1 ?></div>
                        <div style="flex:1;">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                    <button type="submit" name="toggle_question" class="btn btn-xs <?= $q['ativo'] ? 'btn-success' : 'btn-danger btn-ghost' ?>">
                                        <?= $q['ativo'] ? '<i class="fas fa-eye"></i> Ativa' : '<i class="fas fa-eye-slash"></i> Inativa' ?>
                                    </button>
                                </form>
                                <span class="coverage-badge <?= $isFull ? 'coverage-full' : 'coverage-partial' ?>">
                                    <i class="fas fa-language"></i> <?= $coveredLangs ?>/<?= $totalLangs ?> idiomas traduzidos
                                </span>
                                <form method="POST" style="margin:0; margin-left:auto;" onsubmit="return confirm('Excluir esta pergunta e todas as suas traduções?')">
                                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                    <button type="submit" name="delete_question" class="btn btn-xs btn-danger"><i class="fas fa-trash-alt"></i> Excluir</button>
                                </form>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:10px;">
                                    <div>
                                        <label style="font-size:11px; color:#666; display:block; margin-bottom:4px;">📝 Texto Base</label>
                                        <textarea name="text_target" class="edit-textarea" rows="2" required><?= htmlspecialchars($q['text_target']) ?></textarea>
                                    </div>
                                    <div>
                                        <label style="font-size:11px; color:#666; display:block; margin-bottom:4px;">🇺🇸 Inglês (Original/Fallback)</label>
                                        <textarea name="text_en" class="edit-textarea" rows="2" required><?= htmlspecialchars($q['text_en']) ?></textarea>
                                    </div>
                                </div>
                                <button type="submit" name="edit_question" class="btn btn-ghost btn-xs"><i class="fas fa-save"></i> Salvar alterações nos textos acima</button>
                            </form>

                            <?php if (!empty($activeLangs)): ?>
                            <div class="trans-section">
                                <div class="trans-section-title"><i class="fas fa-language"></i> Traduções para idiomas ativos</div>
                                <?php foreach ($activeLangs as $lc): 
                                    $t_text = $translations['question'][$q['id']][$lc] ?? '';
                                    $hasVal = !empty($t_text);
                                ?>
                                <form method="POST" class="trans-row">
                                    <input type="hidden" name="entity_type" value="question">
                                    <input type="hidden" name="entity_id" value="<?= $q['id'] ?>">
                                    <input type="hidden" name="lang_code" value="<?= $lc ?>">
                                    <div class="trans-lang-badge">
                                        <div class="lang-badge"><?= strtoupper($lc) ?></div>
                                        <div class="lang-badge-name"><?= $langNames[$lc] ?? $lc ?></div>
                                    </div>
                                    <input type="text" name="text" value="<?= htmlspecialchars($t_text) ?>"
                                           placeholder="Sem tradução (usará inglês como fallback)..."
                                           class="<?= $hasVal ? 'has-value' : '' ?>">
                                    <button type="submit" name="save_translation" class="btn btn-ghost btn-xs"><i class="fas fa-save"></i> Salvar</button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ===================== TAB: RANKINGS & CONFIG ===================== -->
    <div id="tab-config" class="tab-content active">
        <!-- Monitoramento -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div>
                    <h3 style="margin:0; color:#fff; font-size:18px;"><i class="fas fa-chart-bar" style="color:#38bdf8; margin-right:8px;"></i> Atividade de Hoje</h3>
                    <p style="color:#777; margin:4px 0 0; font-size:13px;"><?= date('d/m/Y') ?></p>
                </div>
                <a href="comunidade_global.php" class="btn btn-success btn-sm"><i class="fas fa-sync-alt"></i> Atualizar</a>
            </div>

            <?php
            $hoje = date('Y-m-d');
            $activityToday = fetchCommunityActivity($hoje);
            if (empty($activityToday)) {
                echo "<div class='alert alert-warning'>Nenhuma atividade registrada ainda para hoje.</div>";
            } else {
                $globalGroupNames = [];
                foreach (($config['groups'] ?? []) as $g) {
                    if (!empty($g['jid']) && !empty($g['is_community_group'])) {
                        $globalGroupNames[$g['jid']] = $g['name'] ?? 'Grupo';
                    }
                }
                echo "<div style='display:flex; flex-wrap:wrap; gap:18px;'>";
                $hasActivity = false;
                foreach ($activityToday as $groupJid => $members) {
                    if (!isset($globalGroupNames[$groupJid]) || empty($members)) continue;
                    $hasActivity = true;
                    uasort($members, fn($a,$b) =>
                        (($b['messages']??0)+($b['reactions_given']??0)+($b['images_sent']??0)+($b['audios_sent']??0)) <=>
                        (($a['messages']??0)+($a['reactions_given']??0)+($a['images_sent']??0)+($a['audios_sent']??0))
                    );
                    echo "<div style='background:#181818; padding:16px; border-radius:10px; border:1px solid #2a2a2a; flex:1 1 280px;'>";
                    echo "<h4 style='margin:0 0 12px; color:#38bdf8; font-size:15px;'>" . htmlspecialchars($globalGroupNames[$groupJid]) . "</h4>";
                    echo "<table class='tbl'><tr><th>Participante</th><th style='text-align:center;'>💬</th><th style='text-align:center;'>❤️</th></tr>";
                    foreach ($members as $jid => $data) {
                        $msgs = ($data['messages']??0)+($data['images_sent']??0)+($data['audios_sent']??0);
                        $reacts = $data['reactions_given']??0;
                        echo "<tr><td>" . htmlspecialchars($data['name']??'?') . "</td>";
                        echo "<td style='text-align:center; color:var(--success); font-weight:700;'>$msgs</td>";
                        echo "<td style='text-align:center; color:#f43f5e; font-weight:700;'>$reacts</td></tr>";
                    }
                    echo "</table></div>";
                }
                if (!$hasActivity) echo "<div class='alert alert-warning' style='width:100%;'>Sem atividade nos grupos globais hoje.</div>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- Templates -->
        <div class="card">
            <h3 style="margin:0 0 6px; color:#fff; font-size:18px;"><i class="fas fa-cogs" style="color:#38bdf8; margin-right:8px;"></i> Templates e Sincronização</h3>
            <p style="color:#777; font-size:13px; margin:0 0 20px;">Configure os templates e sincronize as definições com o robô.</p>
            <form method="POST">
                <div style="margin-bottom:18px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">🎉 Template Estrutural das Boas-Vindas</label>
                    <textarea name="tpl_welcome_general" style="width:100%; min-height:90px; background:#111; color:#fff; border:1px solid #333; border-radius:8px; padding:12px; font-family:monospace; font-size:13px;"><?= htmlspecialchars($tpl_welcome_general) ?></textarea>
                    <small style="color:#555; font-size:12px;">Variáveis: <code>&#123;intro_text&#125;</code> e <code>&#123;questions_text&#125;</code></small>
                </div>
                <div style="margin-bottom:18px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">📊 Template: Ranking de Mensagens</label>
                    <textarea name="tpl_messenger" style="width:100%; min-height:140px; background:#111; color:#fff; border:1px solid #333; border-radius:8px; padding:12px; font-family:monospace; font-size:13px;"><?= htmlspecialchars($tpl_messenger) ?></textarea>
                    <small style="color:#555; font-size:12px;">Variáveis: <code>&#123;group_name&#125;</code> · <code>&#123;date&#125;</code> · <code>&#123;msg_ranking_list&#125;</code></small>
                </div>
                <div style="margin-bottom:18px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px;">❤️ Template: Ranking de Reações</label>
                    <textarea name="tpl_reactor" style="width:100%; min-height:140px; background:#111; color:#fff; border:1px solid #333; border-radius:8px; padding:12px; font-family:monospace; font-size:13px;"><?= htmlspecialchars($tpl_reactor) ?></textarea>
                    <small style="color:#555; font-size:12px;">Variáveis: <code>&#123;group_name&#125;</code> · <code>&#123;date&#125;</code> · <code>&#123;react_ranking_list&#125;</code></small>
                </div>
                <button type="submit" name="sync_global_config" class="btn btn-primary"><i class="fas fa-satellite-dish"></i> Salvar Templates e Sincronizar Robô</button>
            </form>
        </div>
    </div>
</main>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
    localStorage.setItem('gbl_tab', tabId);
}

function switchSubTab(tabId, btn) {
    document.querySelectorAll('.sub-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.sub-tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
    localStorage.setItem('gbl_sub', tabId);
}

// Restaurar estado
(function() {
    const t = localStorage.getItem('gbl_tab');
    if (t) {
        const btn = document.querySelector(`.tab-btn[onclick*="${t}"]`);
        if (btn) switchTab(t, btn);
    }
    const s = localStorage.getItem('gbl_sub');
    if (s) {
        const btn = document.querySelector(`.sub-tab-btn[onclick*="${s}"]`);
        if (btn) switchSubTab(s, btn);
    }
})();
</script>
</body>
</html>
