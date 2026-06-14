<?php
session_start();
require_once '../config.php';

$conn = connectDB();
$senha_correta = getSetting('hosts_app_password', 'meetup2026');
$semana_atual = date('o-\WW'); // Ex: "2026-W24" — reseta automaticamente toda segunda

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $senha_correta) {
        $_SESSION['hosts_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = "Senha incorreta!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$logged_in = $_SESSION['hosts_logged_in'] ?? false;

// --- Salvar replay (PRG pattern) ---
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_replay') {
    require_once dirname(__DIR__) . '/includes/whatsapp_helper.php';

    $lang_data = json_decode($_POST['idioma_replay'] ?? '', true);
    if ($lang_data) {
        $lang_id = (int)$lang_data['id'];
        $numero = trim($_POST['replay_numero'] ?? '');
        $link   = sanitizeOdyseeUrl(trim($_POST['replay_link'] ?? ''));
        $titulo = trim($_POST['replay_titulo'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO meetup_replays (language_id, semana, numero, link, titulo)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE numero = VALUES(numero), link = VALUES(link), titulo = VALUES(titulo)
        ");
        $stmt->execute([$lang_id, $semana_atual, $numero, $link, $titulo]);

        // Gera prévia consolidada da semana e notifica o grupo dos hosts
        $stmtAll = $conn->query("
            SELECT l.flag_emoji, r.numero, r.link, r.titulo 
            FROM languages l 
            LEFT JOIN meetup_replays r ON l.id = r.language_id AND r.semana = '$semana_atual'
            WHERE l.active = 1 
            ORDER BY l.name ASC
        ");
        $full_text = "*Replays!* https://encontrodeidiomas.com.br\n\n";
        while ($row = $stmtAll->fetch()) {
            $num = !empty($row['numero']) ? $row['numero'] : "Nº";
            $lnk = !empty($row['link'])   ? $row['link']   : "Link";
            $tit = !empty($row['titulo']) ? $row['titulo'] : "Título";
            $full_text .= "{$row['flag_emoji']} ▪️ {$num} ▪️ {$lnk} ▪️ {$tit}\n";
        }
        $footer = getSetting('weekly_summary_footer', "*Nº: Máximo de participantes simultâneos | Max simultaneous participants.\n🚀 Stay tuned for the next one! | Fique de olho para participar do próximo!*");
        $full_text .= "\n" . $footer;

        $lang_emoji = $lang_data['emoji'] ?? '';
        enviarWhatsApp('120363164732845564@g.us',
            "🔄 *Mensagem Semanal Atualizada!*\nO idioma {$lang_emoji} *{$lang_data['nome']}* enviou os dados desta semana.\n\nPrévia da mensagem final:\n\n" . $full_text,
            'hosts_app'
        );

        header('Location: index.php?saved=1&lang_id=' . $lang_id);
        exit;
    }
}

// --- Buscar dados ---
$idiomas_disponiveis = [];
$template_db = "";
$dados_semana = []; // Dados já salvos nesta semana, indexados por language_id
$prefill = null;    // Dados para pré-preencher após redirect

if ($logged_in) {
    try {
        $stmt = $conn->query("
            SELECT DISTINCT l.id, l.name, l.flag_emoji, l.instagram_link, l.greeting, m.meet_link
            FROM languages l
            JOIN meetings m ON l.id = m.language_id
            WHERE m.active = 1
            ORDER BY l.name ASC
        ");
        $idiomas_disponiveis = $stmt->fetchAll();

        $stmtT = $conn->query("SELECT template_texto FROM meetup_whatsapp_templates WHERE minutos_antes = 0 AND ativo = 1 LIMIT 1");
        $template_db = $stmtT->fetchColumn() ?: "Template padrão não configurado.";

        // Dados desta semana para todos os idiomas
        $stmtS = $conn->prepare("SELECT language_id, numero, link, titulo FROM meetup_replays WHERE semana = ?");
        $stmtS->execute([$semana_atual]);
        foreach ($stmtS->fetchAll() as $row) {
            $dados_semana[$row['language_id']] = $row;
        }

        // Pré-preencher se voltou via redirect após salvar
        if (isset($_GET['saved'], $_GET['lang_id'])) {
            $lid = (int)$_GET['lang_id'];
            $prefill = $dados_semana[$lid] ?? null;
            $prefill['lang_id'] = $lid;
        }

    } catch (PDOException $e) {
        $error = "Sistema em manutenção. Tente novamente mais tarde.";
    }
}

// --- Helper backend: limpa URLs do Odysee ---
function sanitizeOdyseeUrl(string $url): string {
    // Remove ":código" após cada segmento de path (ex: :0, :2, :a)
    return preg_replace('/(https:\/\/odysee\.com\/[^?#]*?)(?::([a-zA-Z0-9]+))/u', '$1', $url);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal dos Hosts | Encontro de Idiomas</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --card-bg: #1e293b;
            --accent-red: #e31d1c;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --success: #10b981;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 30px 20px; }
        
        .container { width: 100%; max-width: 520px; }
        .card { background: var(--card-bg); padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.05); }
        .logo { width: 50px; height: 50px; background: var(--accent-red); border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 15px; }
        h1 { text-align: center; font-size: 1.4rem; margin-bottom: 5px; }
        .subtitle { text-align: center; color: var(--text-dim); margin-bottom: 25px; font-size: 0.85rem; }

        input[type="password"], select { width: 100%; padding: 13px; background: var(--primary-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 10px; margin-bottom: 15px; font-size: 0.95rem; font-family: inherit; }
        
        .btn { width: 100%; padding: 14px; background: var(--accent-red); color: white; border: none; border-radius: 10px; font-weight: bold; font-size: 0.95rem; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn:hover { opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.07); color: var(--text-dim); border: 1px solid rgba(255,255,255,0.1); margin-top: 10px; }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); color: var(--text-main); }
        .btn-copy { background: #38bdf8; margin-top: 15px; }

        .error   { color: var(--accent-red); text-align: center; margin-bottom: 15px; font-size: 0.9rem; }
        .success { color: var(--success); text-align: center; margin-bottom: 15px; font-weight: bold; font-size: 0.9rem; }

        /* Tabs */
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .tab-btn { flex: 1; padding: 10px 8px; background: var(--primary-bg); color: var(--text-dim); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; cursor: pointer; text-align: center; font-weight: 600; font-size: 0.82rem; transition: 0.2s; }
        .tab-btn.active { background: var(--accent-red); color: white; border-color: var(--accent-red); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Form fields */
        .form-group { margin-bottom: 14px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text-dim); font-size: 0.85rem; font-weight: 600; }
        .form-group input[type="text"] { width: 100%; padding: 12px; background: var(--primary-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; font-family: inherit; font-size: 0.9rem; transition: border-color 0.2s; }
        .form-group input[type="text"]:focus { outline: none; border-color: var(--accent-red); }
        .field-hint { color: var(--text-dim); font-size: 0.75rem; margin-top: 4px; }
        .field-cleaned { color: var(--success); font-size: 0.75rem; margin-top: 4px; display: none; }
        
        /* Saved indicator */
        .saved-badge { display: inline-block; background: rgba(16,185,129,0.15); color: var(--success); border: 1px solid rgba(16,185,129,0.3); border-radius: 6px; padding: 3px 8px; font-size: 0.75rem; margin-left: 6px; }
        
        .message-box { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); padding: 18px; border-radius: 10px; margin-top: 15px; white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6; display: none; }
        
        .separator { border: none; border-top: 1px solid rgba(255,255,255,0.05); margin: 20px 0; }
        .logout-link { text-align: center; margin-top: 20px; }
        .logout-link a { color: var(--text-dim); text-decoration: none; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="logo">Ei</div>
        <h1>Portal dos Hosts</h1>

        <?php if (!$logged_in): ?>
            <p class="subtitle">Digite a senha fornecida pelo administrador.</p>
            <?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Senha de acesso" required>
                <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Entrar</button>
            </form>

        <?php else: ?>
            <?php if (isset($_GET['saved'])): ?>
                <div class="success"><i class="fas fa-check-circle"></i> Replay salvo e notificação enviada ao grupo!</div>
            <?php endif; ?>
            <?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>

            <div class="tabs">
                <div class="tab-btn <?= !isset($_GET['saved']) ? '' : '' ?> active" id="tab-btn-replay" onclick="switchTab('replay')">
                    <i class="fas fa-video"></i> Replay Semanal
                </div>
                <div class="tab-btn" id="tab-btn-inicio" onclick="switchTab('inicio')">
                    <i class="fas fa-play-circle"></i> Mensagem de Início
                </div>
            </div>

            <!-- === ABA PRINCIPAL: Replay Semanal === -->
            <div id="tab-replay" class="tab-content active">
                <p class="subtitle">Preencha os dados do encontro desta semana. Você pode voltar para editar antes do disparo de domingo.</p>
                <form method="POST" id="formReplay">
                    <input type="hidden" name="action" value="save_replay">

                    <div class="form-group">
                        <label>Seu Idioma</label>
                        <select name="idioma_replay" id="idiomaReplaySelect" onchange="carregarDadosSemana()" required>
                            <option value="">-- Selecione seu idioma --</option>
                            <?php foreach ($idiomas_disponiveis as $l):
                                $saved = $dados_semana[$l['id']] ?? null;
                            ?>
                                <option value='<?= json_encode(["id" => $l['id'], "nome" => $l['name'], "emoji" => $l['flag_emoji']]) ?>'
                                        data-saved='<?= json_encode($saved) ?>'
                                        <?= ($prefill && $prefill['lang_id'] === $l['id']) ? 'selected' : '' ?>>
                                    <?= $l['flag_emoji'] ?> <?= htmlspecialchars($l['name']) ?>
                                    <?php if ($saved): ?><span> ✓</span><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nº (Máx. Participantes Simultâneos)</label>
                        <input type="text" name="replay_numero" id="replay_numero"
                               value="<?= htmlspecialchars($prefill['numero'] ?? '') ?>"
                               placeholder="Ex: 12">
                    </div>

                    <div class="form-group">
                        <label>Link da Gravação (Odysee)</label>
                        <input type="text" name="replay_link" id="replay_link"
                               value="<?= htmlspecialchars($prefill['link'] ?? '') ?>"
                               placeholder="https://odysee.com/@EncontroDeIdiomas..."
                               onblur="limparLinkOdysee(this)">
                        <div class="field-cleaned" id="link-cleaned">✓ Link simplificado automaticamente</div>
                    </div>

                    <div class="form-group">
                        <label>Título (Clickbait Honesto)</label>
                        <input type="text" name="replay_titulo" id="replay_titulo"
                               value="<?= htmlspecialchars($prefill['titulo'] ?? '') ?>"
                               placeholder='Ex: "Ela disse que aprendeu isso em 40 minutos!"'>
                    </div>

                    <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Salvar e Notificar Grupo</button>
                </form>
            </div>

            <!-- === ABA SECUNDÁRIA: Mensagem de Início === -->
            <div id="tab-inicio" class="tab-content">
                <p class="subtitle">Gere a mensagem para o início do seu encontro ao vivo.</p>
                <select id="idiomaSelect" onchange="gerarMensagem()">
                    <option value="">-- Escolha o Idioma --</option>
                    <?php foreach ($idiomas_disponiveis as $l): ?>
                        <option value='<?= json_encode([
                            "nome"      => $l['name'],
                            "emoji"     => $l['flag_emoji'],
                            "emojis"    => str_repeat($l['flag_emoji'], 5),
                            "saudacao"  => $l['greeting'],
                            "meet_link" => $l['meet_link'],
                            "instagram" => $l['instagram_link']
                        ]) ?>'><?= $l['flag_emoji'] ?> <?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="messageBox" class="message-box"></div>
                <button id="btnCopy" class="btn btn-copy" style="display:none;" onclick="copiarMensagem()">
                    <i class="far fa-copy"></i> Copiar Mensagem
                </button>
            </div>

            <hr class="separator">
            <div class="logout-link">
                <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Sair do Painel</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($logged_in): ?>
<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        document.getElementById('tab-btn-' + tab).classList.add('active');
    }

    // Carrega dados já salvos desta semana quando muda o idioma
    function carregarDadosSemana() {
        const select = document.getElementById('idiomaReplaySelect');
        const opt = select.options[select.selectedIndex];
        const saved = JSON.parse(opt.dataset.saved || 'null');

        document.getElementById('replay_numero').value = saved?.numero || '';
        document.getElementById('replay_link').value   = saved?.link   || '';
        document.getElementById('replay_titulo').value = saved?.titulo || '';
        document.getElementById('link-cleaned').style.display = 'none';
    }

    // Limpa o link do Odysee removendo :código após cada segmento
    function limparLinkOdysee(input) {
        const original = input.value;
        // Remove :codigo de cada segmento (ex: :0, :2, :a) em URLs Odysee
        const limpo = original.replace(/(https:\/\/odysee\.com\/[^?# ]*?):[a-zA-Z0-9]+/g, '$1')
                               .replace(/(https:\/\/odysee\.com\/[^?# ]*?):[a-zA-Z0-9]+/g, '$1'); // 2nd pass
        if (limpo !== original) {
            input.value = limpo;
            document.getElementById('link-cleaned').style.display = 'block';
        }
    }

    // Mensagem de início do encontro
    const templateOriginal = `<?= $template_db ?>`;
    function gerarMensagem() {
        const select = document.getElementById('idiomaSelect');
        const box = document.getElementById('messageBox');
        const btnCopy = document.getElementById('btnCopy');
        if (!select.value) { box.style.display = 'none'; btnCopy.style.display = 'none'; return; }
        const data = JSON.parse(select.value);
        let texto = templateOriginal
            .replace(/{IDIOMA}/g, data.nome.toUpperCase())
            .replace(/{idioma}/g, data.nome)
            .replace(/{EMOJI_FLAG}/g, data.emoji)
            .replace(/{EMOJI_FLAGS}/g, data.emojis)
            .replace(/{SAUDACAO}/g, data.saudacao)
            .replace(/{MEET_LINK}/g, data.meet_link || 'Link não definido')
            .replace(/{INSTAGRAM_LINK}/g, data.instagram || '');
        box.textContent = texto;
        box.style.display = 'block';
        btnCopy.style.display = 'flex';
    }
    function copiarMensagem() {
        const texto = document.getElementById('messageBox').textContent;
        navigator.clipboard.writeText(texto).then(() => {
            const btn = document.getElementById('btnCopy');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
            btn.style.background = 'var(--success)';
            setTimeout(() => { btn.innerHTML = orig; btn.style.background = '#38bdf8'; }, 2000);
        });
    }
</script>
<?php endif; ?>
</body>
</html>
