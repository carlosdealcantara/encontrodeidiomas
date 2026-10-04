<?php
session_start();
require_once '../config.php';

$conn = connectDB();
$senha_correta = getSetting('hosts_app_password', 'meetup2026');
$semana_atual = date('o-\WW'); // Ex: "2026-W24" — reseta automaticamente toda segunda

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $senha_correta) {
        $_SESSION['hosts_auth_v2'] = true;
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

$logged_in = $_SESSION['hosts_auth_v2'] ?? false;

// --- Salvar replay (PRG pattern) ---
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_replay') {
    require_once dirname(__DIR__) . '/includes/whatsapp_helper.php';

    $lang_data = json_decode($_POST['idioma_replay'] ?? '', true);
    if ($lang_data) {
        $lang_id = (int)$lang_data['id'];
        $numero = trim($_POST['replay_numero'] ?? '');
        if (is_numeric($numero)) {
            $numero = str_pad($numero, 2, '0', STR_PAD_LEFT);
        }
        $link   = sanitizeOdyseeUrl(trim($_POST['replay_link'] ?? ''));
        $titulo = trim($_POST['replay_titulo'] ?? '');
        $parte = (int)($_POST['replay_parte'] ?? 1);

        // Não sobrescreve o link se o host não enviou um — o campo pode ter sido
        // preenchido automaticamente pelo robô Odysee e não aparece mais no formulário.
        if (!empty($link)) {
            $stmt = $conn->prepare("
                INSERT INTO meetup_replays (language_id, semana, parte, numero, link, titulo)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE numero = VALUES(numero), link = VALUES(link), titulo = VALUES(titulo)
            ");
            $stmt->execute([$lang_id, $semana_atual, $parte, $numero, $link, $titulo]);
        } else {
            // Link veio vazio: preserva o link existente no banco (não o apaga)
            $stmt = $conn->prepare("
                INSERT INTO meetup_replays (language_id, semana, parte, numero, link, titulo)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE numero = VALUES(numero), titulo = VALUES(titulo)
            ");
            $stmt->execute([$lang_id, $semana_atual, $parte, $numero, $link, $titulo]);
        }

        // AUTOMATIZAÇÃO ODYSEE:
        // Apenas assume o controle (pending) se o host não enviou o link manualmente.
        // Assim, podemos testar gradualmente: quem mandar o link faz manual, quem deixar em branco aciona o robô.
        try {
            if (empty($link) && !empty($titulo)) {
                $stmtQ = $conn->prepare("UPDATE odysee_publish_queue SET titulo_final = ?, status = 'pending', retry_count = 0 WHERE language_id = ? AND status IN ('waiting_host', 'error', 'pending') AND (replay_parte = ? OR replay_parte IS NULL)");
                $stmtQ->execute([$titulo, $lang_id, $parte]);
            } else if (!empty($link)) {
                // Se o host enviou o link, marca como 'done' (ou ignora) para que o robô não duplique.
                $stmtQ = $conn->prepare("UPDATE odysee_publish_queue SET status = 'done' WHERE language_id = ? AND status IN ('waiting_host', 'error', 'pending') AND (replay_parte = ? OR replay_parte IS NULL)");
                $stmtQ->execute([$lang_id, $parte]);
            }
        } catch (PDOException $e) {
            // Falha silenciosa: não bloqueia o envio da notificação ao grupo dos hosts
            error_log("[portal_hosts] Erro ao atualizar odysee_publish_queue: " . $e->getMessage());
        }

        // Chama a função centralizada para gerar e notificar a atualização
        require_once dirname(__DIR__) . '/includes/hosts_notification.php';
        notificarAtualizacaoHosts($conn, $lang_id, $semana_atual, "atualizou dados", $parte);
        
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
            SELECT l.id, l.name, l.name_en, l.flag_emoji, l.instagram_link, l.greeting, 
                   (SELECT meet_link FROM meetings WHERE language_id = l.id AND active = 1 ORDER BY day_of_week ASC, time_hour ASC LIMIT 1) as meet_link
            FROM languages l
            JOIN (
                SELECT language_id, MIN(day_of_week) as first_day, MIN(time_hour) as first_hour 
                FROM meetings 
                WHERE active = 1 
                GROUP BY language_id
            ) m ON l.id = m.language_id
            ORDER BY m.first_day ASC, m.first_hour ASC, l.name ASC
        ");
        $idiomas_disponiveis = $stmt->fetchAll();

        // Sessões por idioma: detecta multi-sessão (ex: Francês com 2 encontros semanais)
        // meetings.day_of_week = sessão base (sempre presente), meeting_sessions = sessões extras.
        $sessionsPerLang = [];
        try {
            $stmtSL = $conn->query("
                SELECT sub.language_id, sub.day_of_week, sub.time_hour,
                       ROW_NUMBER() OVER (PARTITION BY sub.language_id ORDER BY sub.day_of_week ASC, sub.time_hour ASC) as session_num
                FROM (
                    SELECT language_id, day_of_week, time_hour FROM meetings WHERE active = 1
                    UNION ALL
                    SELECT m2.language_id, ms2.day_of_week, ms2.time_hour
                    FROM meetings m2
                    JOIN meeting_sessions ms2 ON ms2.meeting_id = m2.id AND ms2.active = 1
                    WHERE m2.active = 1
                      AND NOT (ms2.day_of_week = m2.day_of_week AND ms2.time_hour = m2.time_hour)
                ) sub
                ORDER BY sub.language_id ASC, sub.day_of_week ASC, sub.time_hour ASC
            ");
            foreach ($stmtSL->fetchAll() as $srow) {
                $sessionsPerLang[(int)$srow['language_id']][] = [
                    'num'  => (int)$srow['session_num'],
                    'day'  => (int)$srow['day_of_week'],
                    'hour' => (int)$srow['time_hour'],
                ];
            }
        } catch (Exception $e) {
            // Sem meeting_sessions: trata tudo como 1 sessão
        }

        $stmtT = $conn->query("SELECT template_texto FROM meetup_whatsapp_templates WHERE minutos_antes = 0 AND ativo = 1 LIMIT 1");
        $template_db = $stmtT->fetchColumn() ?: "Template padrão não configurado.";

        // Dados desta semana para todos os idiomas
        $stmtS = $conn->prepare("SELECT language_id, parte, numero, link, titulo FROM meetup_replays WHERE semana = ?");
        $stmtS->execute([$semana_atual]);
        foreach ($stmtS->fetchAll() as $row) {
            if (!isset($dados_semana[$row['language_id']])) {
                $dados_semana[$row['language_id']] = [];
            }
            $dados_semana[$row['language_id']][$row['parte']] = $row;
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Noto+Color+Emoji&display=swap" rel="stylesheet">
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

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', 'Noto Color Emoji', sans-serif; }
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

        /* Multi-sessão */
        .session-label { font-weight: 700; font-size: 0.88rem; color: var(--text-dim); padding: 6px 0 10px; display: none; }
        .session-label.visible { display: block; }
        .session-divider { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 22px 0 16px; }
        .split-link-wrapper { margin-top: 14px; }
        .split-link { color: var(--text-dim); font-size: 0.75rem; text-decoration: none; opacity: 0.6; }
        .split-link:hover { opacity: 1; color: var(--text-main); }
        .split-info { background: rgba(245,158,11,0.07); border: 1px solid rgba(245,158,11,0.2); padding: 10px 12px; border-radius: 8px; margin-top: 8px; font-size: 0.8rem; color: var(--text-dim); line-height: 1.5; }
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

                <!-- Seleção de idioma — fora dos formulários, direciona os blocos abaixo -->
                <div class="form-group">
                    <label>Seu Idioma</label>
                    <select id="idiomaReplaySelect" onchange="carregarDadosSemana()">
                        <option value="">-- Selecione seu idioma --</option>
                        <?php foreach ($idiomas_disponiveis as $l):
                            $saved        = $dados_semana[$l['id']] ?? null;
                            $langSessions = $sessionsPerLang[$l['id']] ?? [];
                            $totalSess    = max(1, count($langSessions));
                            $allComplete  = false;
                            if ($saved) {
                                $allDone = true;
                                for ($sp = 1; $sp <= $totalSess; $sp++) {
                                    if (!isset($saved[$sp]) || empty($saved[$sp]['numero']) || empty($saved[$sp]['titulo'])) {
                                        $allDone = false; break;
                                    }
                                }
                                $allComplete = $allDone;
                            }
                        ?>
                        <option value='<?= htmlspecialchars(json_encode(["id" => $l['id'], "nome" => $l['name'], "emoji" => $l['flag_emoji']]), ENT_QUOTES, "UTF-8") ?>'
                                data-saved='<?= htmlspecialchars(json_encode($saved), ENT_QUOTES, "UTF-8") ?>'
                                data-sessions='<?= htmlspecialchars(json_encode(array_values($langSessions)), ENT_QUOTES, "UTF-8") ?>'
                                <?= ($prefill && $prefill['lang_id'] == $l['id']) ? 'selected' : '' ?>>
                            <?= $l['flag_emoji'] ?> <?= htmlspecialchars($l['name']) ?>
                            <?= $saved ? ($allComplete ? ' (Pronto ✅)' : ' (Incompleto ⏳)') : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Formulário: 1º Encontro -->
                <form method="POST" id="formReplay1" style="display:none;">
                    <input type="hidden" name="action" value="save_replay">
                    <input type="hidden" name="idioma_replay" id="idioma_replay_f1" value="">
                    <input type="hidden" name="replay_parte" value="1">

                    <div class="session-label" id="sessionLabel1"></div>

                    <div class="form-group">
                        <label>Nº (Máx. Participantes Simultâneos)</label>
                        <input type="text" name="replay_numero" id="replay_numero_1" placeholder="Ex: 12">
                    </div>
                    <div class="form-group">
                        <label>Título (Clickbait Honesto)</label>
                        <input type="text" name="replay_titulo" id="replay_titulo_1"
                               placeholder='Ex: "Ela disse que aprendeu isso em 40 minutos!"' required>
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Salvar e Notificar Grupo</button>
                    <div class="split-link-wrapper">
                        <a href="#" class="split-link" onclick="toggleSplitInfo(1); return false;">
                            <i class="fas fa-cut"></i> A gravação foi dividida em partes no mesmo dia?
                        </a>
                        <div class="split-info" id="splitInfo1" style="display:none;">
                            Se este encontro teve 2 gravações separadas no mesmo dia, entre em contato com o administrador para registrar a segunda parte manualmente.
                        </div>
                    </div>
                </form>

                <!-- Formulário: 2º Encontro (apenas para idiomas com 2 sessões semanais) -->
                <form method="POST" id="formReplay2" style="display:none;">
                    <input type="hidden" name="action" value="save_replay">
                    <input type="hidden" name="idioma_replay" id="idioma_replay_f2" value="">
                    <input type="hidden" name="replay_parte" value="2">

                    <hr class="session-divider">
                    <div class="session-label visible" id="sessionLabel2"></div>

                    <div class="form-group">
                        <label>Nº (Máx. Participantes Simultâneos)</label>
                        <input type="text" name="replay_numero" id="replay_numero_2" placeholder="Ex: 12">
                    </div>
                    <div class="form-group">
                        <label>Título (Clickbait Honesto)</label>
                        <input type="text" name="replay_titulo" id="replay_titulo_2"
                               placeholder='Ex: "Ela disse que aprendeu isso em 40 minutos!"' required>
                    </div>
                    <button type="submit" class="btn" style="background: rgba(56,189,248,0.85);">
                        <i class="fas fa-paper-plane"></i> Salvar 2º Encontro
                    </button>
                    <div class="split-link-wrapper">
                        <a href="#" class="split-link" onclick="toggleSplitInfo(2); return false;">
                            <i class="fas fa-cut"></i> A gravação foi dividida em partes no mesmo dia?
                        </a>
                        <div class="split-info" id="splitInfo2" style="display:none;">
                            Se este encontro teve 2 gravações separadas no mesmo dia, entre em contato com o administrador para registrar a segunda parte manualmente.
                        </div>
                    </div>
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
                            "name_en"   => !empty($l['name_en']) ? $l['name_en'] : $l['name'],
                            "emoji"     => $l['flag_emoji'],
                            "emojis"    => str_repeat($l['flag_emoji'], 5),
                            "saudacao"  => $l['greeting'] ?: 'Welcome!',
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

    // Nomes dos dias da semana
    const DAY_NAMES = {1:'Segunda-feira', 2:'Terça-feira', 3:'Quarta-feira', 4:'Quinta-feira', 5:'Sexta-feira', 6:'Sábado', 7:'Domingo'};

    // Carrega dados salvos e exibe 1 ou 2 formulários conforme as sessões do idioma
    function carregarDadosSemana() {
        const select = document.getElementById('idiomaReplaySelect');
        const opt    = select.options[select.selectedIndex];
        const form1  = document.getElementById('formReplay1');
        const form2  = document.getElementById('formReplay2');

        if (!select.value) {
            form1.style.display = 'none';
            form2.style.display = 'none';
            return;
        }

        const idiomaJson = opt.value;
        let savedAllParts = {};
        let sessions = [];
        try { savedAllParts = JSON.parse(opt.dataset.saved || 'null') || {}; } catch(e) { savedAllParts = {}; }
        try { sessions = JSON.parse(opt.dataset.sessions || '[]') || []; } catch(e) { sessions = []; }

        // --- Formulário 1 (sempre visível após selecionar idioma) ---
        document.getElementById('idioma_replay_f1').value    = idiomaJson;
        document.getElementById('replay_numero_1').value     = savedAllParts[1]?.numero || '';
        document.getElementById('replay_titulo_1').value     = savedAllParts[1]?.titulo || '';
        const lbl1 = document.getElementById('sessionLabel1');
        if (sessions.length >= 2) {
            lbl1.textContent = '📅 1º Encontro — ' + (DAY_NAMES[sessions[0]?.day] || '');
            lbl1.classList.add('visible');
        } else {
            lbl1.textContent = '';
            lbl1.classList.remove('visible');
        }
        form1.style.display = 'block';

        // --- Formulário 2 (apenas para idiomas com 2 sessões semanais) ---
        if (sessions.length >= 2) {
            document.getElementById('idioma_replay_f2').value = idiomaJson;
            document.getElementById('replay_numero_2').value  = savedAllParts[2]?.numero || '';
            document.getElementById('replay_titulo_2').value  = savedAllParts[2]?.titulo || '';
            const lbl2 = document.getElementById('sessionLabel2');
            lbl2.textContent = '📅 2º Encontro — ' + (DAY_NAMES[sessions[1]?.day] || '');
            form2.style.display = 'block';
        } else {
            form2.style.display = 'none';
        }
    }

    function toggleSplitInfo(n) {
        const el = document.getElementById('splitInfo' + n);
        el.style.display = (el.style.display === 'none') ? 'block' : 'none';
    }


    // Mensagem de início do encontro
    const templateOriginal = `<?= $template_db ?>`;
    function gerarMensagem() {
        const select = document.getElementById('idiomaSelect');
        const box = document.getElementById('messageBox');
        const btnCopy = document.getElementById('btnCopy');
        if (!select.value) { box.style.display = 'none'; btnCopy.style.display = 'none'; return; }
        const data = JSON.parse(select.value);

        // Se for inglês, o nome já é English/Inglês. Para outros, usa o nome em inglês no escopo global.
        const nomeIdioma = data.name_en || data.nome;
        const meetLinkLimpo = (data.meet_link || 'Link não definido').replace(/^https?:\/\//, '');

        let texto = templateOriginal
            .replace(/{SITE_LINK}/g, 'viaEi.com/en/online')
            .replace(/{IDIOMA}/g, nomeIdioma.toUpperCase())
            .replace(/{idioma}/g, nomeIdioma)
            .replace(/{EMOJI_FLAG}/g, data.emoji)
            .replace(/{EMOJI_FLAGS}/g, data.emojis)
            .replace(/{EMOJI_REPETIDO_5X}/g, data.emojis)
            .replace(/{SAUDACAO}/g, data.saudacao || 'Welcome!')
            .replace(/{BOAS_VINDAS_NATIVAS}/g, '')
            .replace(/{IDIOMA_BASE}/g, '🗣️ 🇺🇸 EN')
            .replace(/{HOST_LINK}/g, 'viaEi.com/equipe/')
            .replace(/{MEET_LINK}/g, meetLinkLimpo)
            .replace(/{INSTAGRAM_LINK}/g, data.instagram || '');

        // Resolução para Global: remove {BR}...{/BR} e preserva o conteúdo de {GLOBAL}...{/GLOBAL}
        texto = texto.replace(/\{BR\}[\s\S]*?\{\/BR\}/g, '');
        texto = texto.replace(/\{GLOBAL\}([\s\S]*?)\{\/GLOBAL\}/g, '$1');
        // Normaliza excesso de quebras de linha residuais
        texto = texto.replace(/\n{3,}/g, '\n\n').trim();

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
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('idiomaReplaySelect').value) {
            carregarDadosSemana();
        }
    });
</script>
<?php endif; ?>
</body>
</html>
