<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$msg_success = null;
$semana_atual = date('o-\WW');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_all') {
    if (!empty($_POST['replays'])) {
        foreach ($_POST['replays'] as $lang_id => $partes) {
            foreach ($partes as $parte => $data) {
                $lang_id = (int)$lang_id;
                $parte = (int)$parte;
                $numero = trim($data['numero'] ?? '');
                if (is_numeric($numero)) {
                    $numero = str_pad($numero, 2, '0', STR_PAD_LEFT);
                }
                $link = trim($data['link'] ?? '');
                $titulo = trim($data['titulo'] ?? '');
                
                $stmt = $conn->prepare("
                    INSERT INTO meetup_replays (language_id, semana, parte, numero, link, titulo)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE numero = VALUES(numero), link = VALUES(link), titulo = VALUES(titulo)
                ");
                $stmt->execute([$lang_id, $semana_atual, $parte, $numero, $link, $titulo]);
            }
        }
        $msg_success = "Dados atualizados com sucesso!";
        header('Location: wpp_resumo_semanal.php?msg=Dados+salvos!');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_template') {
    $new_template = trim($_POST['template_text']);
    $item_template_text = trim($_POST['item_template_text']);
    
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, category, label, type, setting_value)
        VALUES ('weekly_summary_template', 'WhatsApp', 'Template do Resumo Semanal', 'textarea', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$new_template]);
    
    $stmt2 = $conn->prepare("
        INSERT INTO settings (setting_key, category, label, type, setting_value)
        VALUES ('weekly_summary_item_template', 'WhatsApp', 'Template da Linha do Resumo', 'text', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt2->execute([$item_template_text]);
    
    header('Location: wpp_resumo_semanal.php?msg=Template+salvo!');
    exit;
}

if (isset($_GET['toggle_ignore_lang'])) {
    $lang_id = (int)$_GET['toggle_ignore_lang'];
    $stmt = $conn->prepare("UPDATE languages SET ignore_next_video = NOT ignore_next_video WHERE id = ?");
    $stmt->execute([$lang_id]);
    header('Location: wpp_resumo_semanal.php?msg=Status+de+vídeo+atualizado!');
    exit;
}

// Fetch all languages with their replays for the CURRENT WEEK only, ordered by their first meeting in the week
$stmt = $conn->prepare("
    SELECT l.id as language_id, l.name, l.flag_emoji, l.ignore_next_video, r.parte, r.numero, r.link, r.titulo 
    FROM languages l 
    LEFT JOIN meetup_replays r ON l.id = r.language_id AND r.semana = ?
    LEFT JOIN (
        SELECT language_id, MIN(day_of_week) as first_day, MIN(time_hour) as first_hour 
        FROM meetings 
        WHERE active = 1 
        GROUP BY language_id
    ) m ON l.id = m.language_id
    WHERE l.active = 1 
    ORDER BY COALESCE(m.first_day, 9) ASC, COALESCE(m.first_hour, 99) ASC, l.name ASC, r.parte ASC
");
$stmt->execute([$semana_atual]);
$replays = $stmt->fetchAll();

// Success message from redirect
if (isset($_GET['msg'])) $msg_success = htmlspecialchars($_GET['msg']);

$default_item_template = "{flag} {num} {link} {title}";
$item_template = getSetting('weekly_summary_item_template', $default_item_template);

// Generate Replays List String
$replays_list_clean = "";
foreach ($replays as $r) {
    if (empty($r['numero']) && empty($r['link']) && empty($r['titulo'])) {
        continue; // Pula idiomas que ainda não tiveram preenchimento nesta semana
    }
    $num = !empty($r['numero']) ? str_pad($r['numero'], 2, '0', STR_PAD_LEFT) : "Nº";
    $lnk = !empty($r['link']) ? str_replace(['https://', 'http://'], '', $r['link']) : "Link";
    $tit = !empty($r['titulo']) ? $r['titulo'] : "Título";
    
    $linha = str_replace(
        ['{flag}', '{num}', '{link}', '{title}'],
        [$r['flag_emoji'], $num, $lnk, $tit],
        $item_template
    ) . "\n";
    $replays_list_clean .= $linha;
}

$default_template = "*Replays!* https://encontrodeidiomas.com.br\n\n{REPLAYS_LIST}\n*Nº: Max simultaneous participants{BR} | Máximo de participantes simultâneos{/BR}.*\n*🚀 Stay tuned for the next one!{BR} | Fique de olho para participar do próximo!{/BR}*";
$template = getSetting('weekly_summary_template', $default_template);

$full_text_clean = str_replace('{REPLAYS_LIST}', trim($replays_list_clean), $template);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerador de Resumo Semanal | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .header { margin-bottom: 30px; }
        
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        
        .card { background: var(--card-bg); padding: 25px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: var(--text-dim); font-size: 0.9rem; }
        
        input[type="text"] { width: 100%; padding: 8px 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; font-family: inherit; }
        textarea { width: 100%; padding: 15px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; font-family: inherit; min-height: 300px; resize: vertical; margin-bottom: 20px; }
        
        .btn { padding: 12px 24px; background: var(--accent-red); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn:hover { opacity: 0.9; }
        .btn-success { background: var(--success); }
        .btn-outline { background: transparent; border: 1px solid var(--accent-red); color: var(--accent-red); }
        
        .actions-bar { display: flex; gap: 15px; margin-top: 20px; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Sub-Nav -->
        <?php include __DIR__ . '/includes/whatsapp_subnav.php'; ?>

        <div class="header">
            <h1 style="font-size: 2rem; margin-bottom: 5px;">Gerador de Resumo Semanal</h1>
            <p style="color: var(--text-dim);">Acompanhe o preenchimento dos hosts e gere o broadcast final.</p>
        </div>

        <?php if ($msg_success): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?= $msg_success ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Dados Recebidos dos Hosts</h2>
            <p style="color: var(--text-dim); margin-bottom: 20px; font-size: 0.9rem;">
                Você pode revisar ou alterar manualmente antes de gerar a mensagem.
            </p>

            <form method="POST" id="formReplays">
                <input type="hidden" name="action" value="save_all">
                <table>
                    <thead>
                        <tr>
                            <th width="15%">Idioma</th>
                            <th width="10%">Nº</th>
                            <th width="35%">Link</th>
                            <th width="40%">Título</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($replays as $r): 
                                $parte = $r['parte'] ?? 1;
                        ?>
                        <tr>
                            <td>
                                <?= $r['flag_emoji'] ?> <?= htmlspecialchars($r['name']) ?> <?= $parte > 1 ? "(Extra)" : "" ?>
                                <a href="wpp_resumo_semanal.php?toggle_ignore_lang=<?= $r['language_id'] ?>" 
                                   class="btn" 
                                   style="padding: 2px 5px; font-size: 0.7rem; margin-left: 5px; transition: 0.3s; <?= $r['ignore_next_video'] ? 'background-color: var(--accent-red); color: white; border: 1px solid var(--accent-red);' : 'background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.4); color: rgba(255,255,255,0.7); opacity: 1;' ?>" 
                                   title="<?= $r['ignore_next_video'] ? 'Cancelar (não ignorar mais)' : 'Ignorar próximo vídeo que cair no drive' ?>"
                                   onmouseover="this.style.borderColor='white'; this.style.color='white';"
                                   onmouseout="this.style.borderColor='<?= $r['ignore_next_video'] ? 'var(--accent-red)' : 'rgba(255,255,255,0.4)' ?>'; this.style.color='<?= $r['ignore_next_video'] ? 'white' : 'rgba(255,255,255,0.7)' ?>';">
                                    <i class="fas fa-video-slash"></i>
                                </a>
                                <?php if ($r['ignore_next_video']): ?>
                                    <br><span style="font-size: 0.7rem; color: var(--accent-red); font-weight: bold;">(Ignorando próx.)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="text" name="replays[<?= $r['language_id'] ?>][<?= $parte ?>][numero]" value="<?= htmlspecialchars($r['numero'] ?? '') ?>" placeholder="Nº">
                            </td>
                            <td>
                                <input type="text" name="replays[<?= $r['language_id'] ?>][<?= $parte ?>][link]" value="<?= htmlspecialchars($r['link'] ?? '') ?>" placeholder="https://odysee.com/..." pattern="^https:\/\/odysee\.com\/@[^\/]+\/\d{4}_\d{2}_\d{2}$" title="O link deve ser do Odysee e terminar com a data no padrão /AAAA_MM_DD (ex: /2026_06_15)">
                            </td>
                            <td>
                                <input type="text" name="replays[<?= $r['language_id'] ?>][<?= $parte ?>][titulo]" value="<?= htmlspecialchars($r['titulo'] ?? '') ?>" placeholder="Título">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Salvar Alterações</button>
            </form>
        </div>

        <div class="card">
            <h2>Mensagem Gerada</h2>
            <p style="color: var(--text-dim); margin-bottom: 20px; font-size: 0.9rem;">
                Abaixo está a mensagem consolidada pronta para ser disparada.
            </p>
            
            <textarea id="finalMessage" readonly><?= htmlspecialchars($full_text_clean) ?></textarea>
            
            <div class="actions-bar">
                <button class="btn btn-outline" onclick="copiarTexto()"><i class="far fa-copy"></i> Copiar Texto</button>
                <button class="btn btn-outline" onclick="document.getElementById('templateModal').style.display='block'"><i class="fas fa-edit"></i> Editar Template</button>
                
                <form method="POST" action="wpp_broadcast.php">
                    <input type="hidden" name="prefill_message" id="prefillMessage" value="<?= htmlspecialchars($full_text_clean) ?>">
                    <input type="hidden" name="prefill_title" value="Replays da semana">
                    <button type="submit" class="btn btn-success"><i class="fas fa-rocket"></i> Levar para o Canhão de Disparo</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal for Template Editing -->
    <div id="templateModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
        <div class="card" style="width: 100%; max-width: 600px; margin: 40px auto; position: relative;">
            <button onclick="document.getElementById('templateModal').style.display='none'" style="position:absolute; top:20px; right:20px; background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
            <h2>Editar Layout da Mensagem</h2>
            <p style="color: var(--text-dim); margin-bottom: 20px; font-size: 0.9rem;">
                Configure a mensagem principal e a regra de formatação de cada linha da lista.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="save_template">
                
                <label style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 5px; display: block;">Template de cada Linha de Idioma (Use {flag}, {num}, {link}, {title}):</label>
                <input type="text" name="item_template_text" value="<?= htmlspecialchars($item_template) ?>" required style="margin-bottom: 20px;">
                
                <label style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 5px; display: block;">Template Geral da Mensagem (Use {REPLAYS_LIST}):</label>
                <textarea name="template_text" required><?= htmlspecialchars($template) ?></textarea>
                
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar Layout</button>
            </form>
        </div>
    </div>
    
    <script>
        // Check if modal should be shown initially (e.g. if we add a hash in future)
        if(window.location.hash === '#edit_template') document.getElementById('templateModal').style.display = 'flex';
        else document.getElementById('templateModal').style.display = 'none';

        // Override standard display:block for modal to use flex for centering
        const originalShow = document.getElementById('templateModal').style.display;
        Object.defineProperty(document.getElementById('templateModal').style, 'display', {
            set: function(val) {
                if (val === 'block') this.cssText += 'display: flex !important;';
                else this.cssText += 'display: none !important;';
            }
        });
        function copiarTexto() {
            const textarea = document.getElementById('finalMessage');
            textarea.select();
            document.execCommand('copy');
            alert('Texto copiado para a área de transferência!');
        }
    </script>
</body>
</html>



