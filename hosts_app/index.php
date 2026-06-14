<?php
session_start();
require_once '../config.php';

$conn = connectDB();
$senha_correta = getSetting('hosts_app_password', 'meetup2026');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $senha_correta) {
        $_SESSION['hosts_logged_in'] = true;
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

// Busca idiomas que possuem encontros ativos
$idiomas_disponiveis = [];
$template_db = "";
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
        
        // Busca o template padrao (Hora Exata)
        $stmtT = $conn->query("SELECT template_texto FROM meetup_whatsapp_templates WHERE minutos_antes = 0 AND ativo = 1 LIMIT 1");
        $template_db = $stmtT->fetchColumn();
        if (!$template_db) {
            $template_db = "Template padrão não configurado.";
        }
    } catch (PDOException $e) {
        $error = "Sistema em manutenção. Tabelas não encontradas no banco de dados.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel dos Hosts | Encontro de Idiomas</title>
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        
        .container { width: 100%; max-width: 500px; }
        
        .card { background: var(--card-bg); padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.05); }
        .logo { width: 50px; height: 50px; background: var(--accent-red); border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 20px; }
        
        h1 { text-align: center; font-size: 1.5rem; margin-bottom: 20px; }
        p { text-align: center; color: var(--text-dim); margin-bottom: 30px; }
        
        input[type="password"], select { width: 100%; padding: 15px; background: var(--primary-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 10px; margin-bottom: 20px; font-size: 1rem; }
        
        .btn { width: 100%; padding: 15px; background: var(--accent-red); color: white; border: none; border-radius: 10px; font-weight: bold; font-size: 1rem; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn:hover { opacity: 0.9; }
        .btn-copy { background: #38bdf8; margin-top: 20px; }
        
        .error { color: var(--accent-red); text-align: center; margin-bottom: 20px; }
        
        .message-box { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin-top: 20px; white-space: pre-wrap; font-size: 0.95rem; line-height: 1.5; display: none; }
    </style>
</head>
    <?php if ($logged_in && isset($_POST['action']) && $_POST['action'] === 'save_replay'):
        require_once dirname(__DIR__) . '/includes/whatsapp_helper.php';
        
        $lang_data = json_decode($_POST['idioma_replay'], true);
        if ($lang_data) {
            $lang_id = (int)$lang_data['id'];
            $numero = trim($_POST['replay_numero']);
            $link = trim($_POST['replay_link']);
            $titulo = trim($_POST['replay_titulo']);
            
            $stmt = $conn->prepare("
                INSERT INTO meetup_replays (language_id, numero, link, titulo)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE numero = VALUES(numero), link = VALUES(link), titulo = VALUES(titulo)
            ");
            $stmt->execute([$lang_id, $numero, $link, $titulo]);
            $msg_success = "Replay salvo com sucesso!";

            // Gerar a mensagem completa e enviar para o grupo dos hosts
            $stmtAll = $conn->query("
                SELECT l.flag_emoji, r.numero, r.link, r.titulo 
                FROM languages l 
                LEFT JOIN meetup_replays r ON l.id = r.language_id 
                WHERE l.active = 1 
                ORDER BY l.name ASC
            ");
            
            $full_text = "Replays! https://encontrodeidiomas.com.br\n\n";
            while ($row = $stmtAll->fetch()) {
                $num = !empty($row['numero']) ? $row['numero'] : "Nº";
                $lnk = !empty($row['link']) ? $row['link'] : "Link";
                $tit = !empty($row['titulo']) ? $row['titulo'] : "Título";
                $full_text .= "{$row['flag_emoji']} ▪️ {$num} ▪️ {$lnk} ▪️ {$tit}\n";
            }
            $full_text .= "\nNo.: Máximo de participantes simultâneos / Max simultaneous participants.\n🚀 Stay tuned for the next one! Fique de olho para participar do próximo!";

            // Notifica grupo dos hosts
            enviarWhatsApp('120363164732845564@g.us', "🔄 *Nova Atualização de Replays!*\nO idioma {$lang_data['nome']} enviou seus dados.\n\nPrévia da mensagem final:\n\n" . $full_text, 'hosts_app');
        }
    endif; ?>
    
    <style>
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { flex: 1; padding: 12px; background: var(--primary-bg); color: var(--text-dim); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; cursor: pointer; text-align: center; font-weight: 600; }
        .tab-btn.active { background: var(--accent-red); color: white; border-color: var(--accent-red); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text-dim); font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 12px; background: var(--primary-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; font-family: inherit; }
    </style>
    
    <div class="container">
        <div class="card">
            <div class="logo">Ei</div>
            <h1>Painel dos Hosts</h1>
            
            <?php if (!$logged_in): ?>
                <p>Digite a senha fornecida pelo administrador para acessar o painel.</p>
                <?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>
                <form method="POST">
                    <input type="password" name="password" placeholder="Senha de Acesso" required>
                    <button type="submit" class="btn">Entrar</button>
                </form>
            <?php else: ?>
                
                <?php if (!empty($msg_success)): ?><div style="color:var(--success); text-align:center; margin-bottom:15px; font-weight:bold;"><?= $msg_success ?></div><?php endif; ?>

                <div class="tabs">
                    <div class="tab-btn active" onclick="switchTab('inicio')">Mensagem de Início</div>
                    <div class="tab-btn" onclick="switchTab('replay')">Enviar Replay Semanal</div>
                </div>

                <div id="tab-inicio" class="tab-content active">
                    <p>Selecione seu idioma para gerar a mensagem de início do encontro.</p>
                    
                    <select id="idiomaSelect" onchange="gerarMensagem()">
                        <option value="">-- Escolha o Idioma --</option>
                        <?php foreach ($idiomas_disponiveis as $l): ?>
                            <option value='<?= json_encode([
                                "nome" => $l['name'],
                                "emoji" => $l['flag_emoji'],
                                "emojis" => str_repeat($l['flag_emoji'], 5),
                                "saudacao" => $l['greeting'],
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

                <div id="tab-replay" class="tab-content">
                    <p>Preencha os dados do encontro desta semana para gerar o resumo de Replays.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_replay">
                        
                        <div class="form-group">
                            <label>Seu Idioma</label>
                            <select name="idioma_replay" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach ($idiomas_disponiveis as $l): ?>
                                    <option value='<?= json_encode(["id" => $l['id'], "nome" => $l['name']]) ?>'><?= $l['flag_emoji'] ?> <?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Nº de Participantes</label>
                            <input type="text" name="replay_numero" placeholder="Ex: 05">
                        </div>
                        
                        <div class="form-group">
                            <label>Link da Gravação (Odysee/YouTube)</label>
                            <input type="text" name="replay_link" placeholder="Ex: https://odysee.com/...">
                        </div>
                        
                        <div class="form-group">
                            <label>Título / Tema do Encontro</label>
                            <input type="text" name="replay_titulo" placeholder="Ex: O que fizemos nas férias">
                        </div>
                        
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Enviar Dados</button>
                    </form>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="?logout=1" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem;">Sair do Painel</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($logged_in): ?>
    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            if (tab === 'inicio') {
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
                document.getElementById('tab-inicio').classList.add('active');
            } else {
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
                document.getElementById('tab-replay').classList.add('active');
            }
        }

        const templateOriginal = `<?= $template_db ?>`;
        
        function gerarMensagem() {
            const select = document.getElementById('idiomaSelect');
            const box = document.getElementById('messageBox');
            const btnCopy = document.getElementById('btnCopy');
            
            if (!select.value) {
                box.style.display = 'none';
                btnCopy.style.display = 'none';
                return;
            }
            
            const data = JSON.parse(select.value);
            
            let texto = templateOriginal;
            texto = texto.replace(/{IDIOMA}/g, data.nome.toUpperCase());
            texto = texto.replace(/{idioma}/g, data.nome);
            texto = texto.replace(/{EMOJI_FLAG}/g, data.emoji);
            texto = texto.replace(/{EMOJI_FLAGS}/g, data.emojis);
            texto = texto.replace(/{SAUDACAO}/g, data.saudacao);
            texto = texto.replace(/{MEET_LINK}/g, data.meet_link || 'Link não definido');
            texto = texto.replace(/{INSTAGRAM_LINK}/g, data.instagram || 'Sem instagram');
            
            box.textContent = texto;
            box.style.display = 'block';
            btnCopy.style.display = 'flex';
        }
        
        function copiarMensagem() {
            const texto = document.getElementById('messageBox').textContent;
            navigator.clipboard.writeText(texto).then(() => {
                const btn = document.getElementById('btnCopy');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                btn.style.background = 'var(--success)';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '#38bdf8';
                }, 2000);
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>
