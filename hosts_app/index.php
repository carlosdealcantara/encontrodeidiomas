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
<body>
    <div class="container">
        <div class="card">
            <div class="logo">Ei</div>
            <h1>Painel dos Hosts</h1>
            
            <?php if (!$logged_in): ?>
                <p>Digite a senha fornecida pelo administrador para acessar o gerador de mensagens.</p>
                <?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>
                <form method="POST">
                    <input type="password" name="password" placeholder="Senha de Acesso" required>
                    <button type="submit" class="btn">Entrar</button>
                </form>
            <?php else: ?>
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
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="?logout=1" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem;">Sair do Painel</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($logged_in): ?>
    <script>
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
