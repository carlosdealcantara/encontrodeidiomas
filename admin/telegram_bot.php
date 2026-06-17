<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$msg = null;
$api_error = null;

// Lógica de Atualizar Templates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_templates'])) {
    try {
        $stmt = $conn->prepare("UPDATE telegram_bot_templates SET texto = :texto WHERE tipo = :tipo");
        foreach (['inicio', '5min', '10min', '20min'] as $tipo) {
            if (isset($_POST["template_$tipo"])) {
                $stmt->execute(['texto' => $_POST["template_$tipo"], 'tipo' => $tipo]);
            }
        }
        $msg = "Templates salvos com sucesso!";
    } catch (PDOException $e) {
        $api_error = "Erro ao salvar templates: " . $e->getMessage();
    }
}

// Lógica de Enviar Mensagem de Teste
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_message'])) {
    $token = getenv('TELEGRAM_BOT_TOKEN');
    $chat_id = getenv('TELEGRAM_CHAT_ID');
    
    if (empty($token) || empty($chat_id)) {
        $api_error = "Erro: TELEGRAM_BOT_TOKEN ou TELEGRAM_CHAT_ID não estão configurados no .env!";
    } else {
        $test_msg = "🤖 *TESTE DE CONEXÃO*\nO Bot do Encontro de Idiomas está online e conectado a este grupo!";
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $chat_id,
            'text' => $test_msg,
            'parse_mode' => 'Markdown'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $res = json_decode($result, true);
        if ($res && $res['ok']) {
            $msg = "Mensagem de teste enviada com sucesso para o Telegram!";
        } else {
            $api_error = "Falha ao enviar mensagem. Retorno da API: " . htmlspecialchars($result);
        }
    }
}

// Buscar status global (token configurado)
$token_configured = !empty(getenv('TELEGRAM_BOT_TOKEN'));
$chat_configured = !empty(getenv('TELEGRAM_CHAT_ID'));
$bot_status_html = ($token_configured && $chat_configured) 
    ? "<span style='color:var(--success);'><i class='fas fa-check-circle'></i> Bot Configurado e Conectado</span>"
    : "<span style='color:var(--danger);'><i class='fas fa-exclamation-triangle'></i> Token ou Chat ID pendentes no .env</span>";

// Buscar último disparo
try {
    $stmtLast = $conn->query("
        SELECT tl.enviado_em, tl.tipo, l.name as lang_name 
        FROM telegram_bot_logs tl 
        JOIN meetings m ON tl.meeting_id = m.id 
        JOIN languages l ON m.language_id = l.id 
        ORDER BY tl.enviado_em DESC LIMIT 1
    ");
    $last_log = $stmtLast->fetch();
} catch (Exception $e) { $last_log = null; }

// Buscar templates
$templates = [];
try {
    $stmtTpl = $conn->query("SELECT tipo, texto FROM telegram_bot_templates");
    foreach ($stmtTpl->fetchAll() as $row) {
        $templates[$row['tipo']] = $row['texto'];
    }
} catch (Exception $e) {}

// Buscar todos os meetings + status ativo no bot
try {
    $stmtMeetings = $conn->query("
        SELECT 
            m.id as meeting_id, m.day_of_week, m.time_hour, 
            l.name as language_name, l.flag_emoji,
            COALESCE(tbs.ativo, 0) as is_notifying
        FROM meetings m
        JOIN languages l ON m.language_id = l.id
        LEFT JOIN telegram_bot_slots tbs ON m.id = tbs.meeting_id
        WHERE m.active = 1
        ORDER BY l.name ASC, m.day_of_week ASC, m.time_hour ASC
    ");
    $meetings = $stmtMeetings->fetchAll();
    
    // Agrupar por idioma
    $meetings_by_lang = [];
    foreach ($meetings as $m) {
        $lang = $m['flag_emoji'] . ' ' . $m['language_name'];
        if (!isset($meetings_by_lang[$lang])) {
            $meetings_by_lang[$lang] = [];
        }
        $meetings_by_lang[$lang][] = $m;
    }
} catch (Exception $e) {
    $meetings_by_lang = [];
}

function getDayLabel($day) {
    $days = [1=>'Segunda', 2=>'Terça', 3=>'Quarta', 4=>'Quinta', 5=>'Sexta', 6=>'Sábado', 7=>'Domingo'];
    return $days[$day] ?? 'Desconhecido';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor do Telegram | Admin</title>
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
            --danger: #ef4444;
            --blue: #3b82f6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.error { background: rgba(239, 68, 68, 0.1); color: var(--danger); border-color: rgba(239, 68, 68, 0.2); }
        
        .card { background: var(--card-bg); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
        .card-header { font-size: 1.2rem; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; color: white; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; text-decoration: none; }
        .btn-primary { background: var(--blue); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-success { background: var(--success); }
        .btn-success:hover { opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        
        /* Toggles */
        .toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.1); transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(24px); }
        
        .meeting-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .meeting-item:last-child { border-bottom: none; }
        
        textarea { width: 100%; height: 120px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; padding: 15px; resize: vertical; margin-bottom: 15px; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .tab-btn { padding: 10px 20px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-dim); border-radius: 8px; cursor: pointer; }
        .tab-btn.active { background: var(--blue); color: white; border-color: var(--blue); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        
        .lang-group { margin-bottom: 30px; background: rgba(0,0,0,0.2); border-radius: 12px; overflow: hidden; }
        .lang-header { background: rgba(255,255,255,0.05); padding: 15px 20px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div>
                <h2><i class="fab fa-telegram" style="color: #2AABEE;"></i> Monitor do Telegram</h2>
                <p style="color: var(--text-dim);">Avisos de encontros ao vivo no seu Telegram pessoal</p>
            </div>
        </header>

        <?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($api_error): ?><div class="alert error"><?= htmlspecialchars($api_error) ?></div><?php endif; ?>

        <!-- SEÇÃO 1: Status -->
        <div class="card">
            <div class="card-header"><i class="fas fa-server"></i> Status da Conexão</div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <p style="margin-bottom: 10px; font-size: 1.1rem;"><?= $bot_status_html ?></p>
                    <p style="color: var(--text-dim); font-size: 0.9rem;">
                        Último disparo: 
                        <?php if ($last_log): ?>
                            <strong><?= date('d/m/Y H:i', strtotime($last_log['enviado_em'])) ?></strong> 
                            (<?= htmlspecialchars($last_log['lang_name']) ?> - <?= htmlspecialchars($last_log['tipo']) ?>)
                        <?php else: ?>
                            Nenhum registro ainda.
                        <?php endif; ?>
                    </p>
                </div>
                <form method="POST">
                    <button type="submit" name="test_message" class="btn btn-secondary">
                        <i class="fas fa-paper-plane"></i> Enviar Teste de Conexão
                    </button>
                </form>
            </div>
        </div>

        <!-- SEÇÃO 2: Templates -->
        <div class="card">
            <div class="card-header"><i class="fas fa-pen-alt"></i> Templates de Mensagem</div>
            <p style="color: var(--text-dim); margin-bottom: 15px; font-size: 0.9rem;">
                Variáveis mágicas: <code>{IDIOMA}</code>, <code>{EMOJI_FLAG}</code>, <code>{DIA}</code>, <code>{HORA}</code>, <code>{MEET_LINK}</code>
            </p>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('inicio')">🔴 Na Hora (0min)</button>
                <button class="tab-btn" onclick="switchTab('5min')">⏳ + 5 min</button>
                <button class="tab-btn" onclick="switchTab('10min')">📍 + 10 min</button>
                <button class="tab-btn" onclick="switchTab('20min')">🌟 + 20 min</button>
            </div>
            
            <form method="POST">
                <?php foreach (['inicio', '5min', '10min', '20min'] as $i => $tipo): ?>
                <div id="tab-<?= $tipo ?>" class="tab-pane <?= $i === 0 ? 'active' : '' ?>">
                    <textarea name="template_<?= $tipo ?>"><?= htmlspecialchars($templates[$tipo] ?? '') ?></textarea>
                </div>
                <?php endforeach; ?>
                
                <button type="submit" name="save_templates" class="btn btn-success">
                    <i class="fas fa-save"></i> Salvar Textos
                </button>
            </form>
        </div>

        <!-- SEÇÃO 3: Toggles de Encontros -->
        <div class="card">
            <div class="card-header"><i class="fas fa-bell"></i> Encontros Ativos no Monitor</div>
            <p style="color: var(--text-dim); margin-bottom: 20px;">Ligue a chave para receber os 4 avisos no seu celular quando o encontro estiver acontecendo.</p>
            
            <?php foreach ($meetings_by_lang as $lang_title => $lang_meetings): ?>
                <div class="lang-group">
                    <div class="lang-header">
                        <span><?= $lang_title ?></span>
                        <!-- Opcional: botão ativar todos -->
                    </div>
                    <?php foreach ($lang_meetings as $m): ?>
                        <div class="meeting-item">
                            <div>
                                <strong style="font-size: 1.1rem;"><?= getDayLabel($m['day_of_week']) ?> às <?= $m['time_hour'] ?>h</strong>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" onchange="toggleMeeting(<?= $m['meeting_id'] ?>, this.checked)" <?= $m['is_notifying'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </main>

    <script>
        // Sistema de abas simples
        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        // AJAX Toggle
        function toggleMeeting(meetingId, isChecked) {
            const formData = new FormData();
            formData.append('meeting_id', meetingId);
            formData.append('ativo', isChecked ? 1 : 0);

            fetch('ajax/telegram_toggle.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert('Erro ao salvar: ' + data.error);
                    // Reverte o toggle visualmente
                    event.target.checked = !isChecked;
                }
            })
            .catch(err => {
                alert('Falha na comunicação com o servidor.');
                event.target.checked = !isChecked;
            });
        }
    </script>
</body>
</html>
