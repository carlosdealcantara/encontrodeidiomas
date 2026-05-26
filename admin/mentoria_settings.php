<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Lógica de Salvar as alterações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Salvar o Rodapé Global PIX
    if (isset($_POST['pix_footer'])) {
        $pix_footer = $_POST['pix_footer'];
        $stmtPix = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('mentoria_pix_footer', :val1) ON DUPLICATE KEY UPDATE setting_value = :val2");
        $stmtPix->execute(['val1' => $pix_footer, 'val2' => $pix_footer]);
    }

    if (isset($_POST['msgs'])) {
        $stmt = $conn->prepare("UPDATE mentoria_mensagens SET texto = :texto, dias_antes = :dias, ativo = :ativo WHERE id = :id");
        
        foreach ($_POST['msgs'] as $id => $dados) {
            $ativo = isset($dados['ativo']) ? 1 : 0;
            $stmt->execute([
                'texto' => $dados['texto'],
                'dias'  => (int)$dados['dias'],
                'ativo' => $ativo,
                'id'    => (int)$id
            ]);
        }
    }
    
    header('Location: mentoria_settings.php?msg=Configurações salvas com sucesso!');
    exit;
}

// Busca todas as mensagens cadastradas
$stmt = $conn->query("SELECT * FROM mentoria_mensagens ORDER BY dias_antes DESC");
$mensagens = $stmt->fetchAll();

// Busca o rodapé atual
$pix_footer_atual = getSetting('mentoria_pix_footer', "🔑 Chave PIX para renovação:\n01811018157\nFavorecido: Carlos Alberto de Alcântara Júnior");

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Mensagens | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --card-bg: #1e293b;
            --input-bg: #0f172a;
            --success: #10b981;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header-title h2 { font-size: 1.8rem; font-weight: 700; }
        .btn-back { color: var(--text-dim); text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-back:hover { color: white; }

        .alert { padding: 15px 25px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 25px; }

        .info-box { background: rgba(56, 189, 248, 0.1); border-left: 4px solid var(--accent-blue); padding: 15px 20px; border-radius: 8px; margin-bottom: 30px; }
        .info-box p { font-size: 0.95rem; line-height: 1.5; color: var(--text-main); margin-bottom: 8px; }
        .info-box code { background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; color: var(--accent-blue); font-weight: bold; }

        .cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        
        .msg-card { background: var(--card-bg); padding: 25px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); position: relative; }
        .msg-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .msg-title { font-weight: 700; font-size: 1.1rem; color: var(--white); }
        
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
        label { font-size: 0.9rem; color: var(--text-dim); font-weight: 600; }
        input[type="number"], textarea { 
            background: var(--input-bg); 
            border: 1px solid rgba(255,255,255,0.1); 
            color: white; 
            padding: 12px 15px; 
            border-radius: 10px; 
            font-size: 0.95rem;
            outline: none;
            transition: 0.3s;
            resize: vertical;
        }
        input[type="number"]:focus, textarea:focus { border-color: var(--accent-red); }

        /* Estilização de Checkbox (Toggle) */
        .toggle-container { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .toggle-container input { display: none; }
        .toggle-slider { width: 44px; height: 24px; background: rgba(255,255,255,0.1); border-radius: 34px; position: relative; transition: 0.3s; }
        .toggle-slider::before { content: ""; position: absolute; width: 18px; height: 18px; background: white; border-radius: 50%; left: 3px; top: 3px; transition: 0.3s; }
        .toggle-container input:checked + .toggle-slider { background: var(--success); }
        .toggle-container input:checked + .toggle-slider::before { transform: translateX(20px); }
        .toggle-label { font-size: 0.9rem; font-weight: 600; color: var(--text-dim); }
        .toggle-container input:checked ~ .toggle-label { color: var(--success); }

        .floating-bar { 
            position: sticky; 
            bottom: 30px; 
            background: var(--sidebar-bg); 
            padding: 20px 30px; 
            border-radius: 15px; 
            display: flex; 
            justify-content: flex-end; 
            margin-top: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-submit { 
            background: var(--accent-red); 
            color: white; 
            border: none; 
            padding: 12px 35px; 
            border-radius: 10px; 
            font-size: 1rem; 
            font-weight: 700; 
            cursor: pointer; 
            transition: 0.3s; 
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }
        
        @media (max-width: 1100px) { .cards-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div class="header-title">
                <h2>Configurar Mensagens do WhatsApp</h2>
            </div>
            <a href="mentoria.php" class="btn-back"><i class="fas fa-arrow-left"></i> Voltar para Alunos</a>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <p><i class="fas fa-info-circle"></i> <strong>Variáveis Dinâmicas:</strong> Você pode usar a palavra <code>{nome}</code> dentro do texto da mensagem. Quando o robô enviar, ele trocará automaticamente pelo nome do aluno cadastrado.</p>
            <p><i class="fas fa-clock"></i> <strong>Dias Antes do Vencimento:</strong> Digite <code>0</code> para enviar no dia exato. Digite <code>3</code> para enviar três dias antes. Digite <code>-1</code> para enviar no dia seguinte ao vencimento (atrasado).</p>
        </div>

        <form method="POST" action="">
            <!-- RODAPÉ GLOBAL PIX -->
            <div class="msg-card" style="margin-bottom: 30px; border-color: var(--accent-blue);">
                <div class="msg-header">
                    <div class="msg-title" style="color: var(--accent-blue);"><i class="fas fa-money-check-alt"></i> Rodapé Padrão de Cobrança (PIX)</div>
                </div>
                <p style="font-size: 0.9rem; color: var(--text-dim); margin-bottom: 15px;">Este texto será adicionado automaticamente no final de TODAS as mensagens que o robô enviar, para que você não precise copiar e colar o PIX em cada uma delas.</p>
                <div class="form-group">
                    <textarea name="pix_footer" rows="4" required><?= htmlspecialchars($pix_footer_atual) ?></textarea>
                </div>
            </div>

            <div class="cards-grid">
                <?php foreach ($mensagens as $msg): ?>
                <div class="msg-card">
                    <div class="msg-header">
                        <div class="msg-title"><?= htmlspecialchars($msg['cenario']) ?></div>
                        <label class="toggle-container">
                            <input type="checkbox" name="msgs[<?= $msg['id'] ?>][ativo]" <?= $msg['ativo'] ? 'checked' : '' ?>>
                            <div class="toggle-slider"></div>
                            <span class="toggle-label"><?= $msg['ativo'] ? 'Ativado' : 'Desativado' ?></span>
                        </label>
                    </div>

                    <div class="form-group" style="width: 150px;">
                        <label>Dias para o Vencimento</label>
                        <input type="number" name="msgs[<?= $msg['id'] ?>][dias]" value="<?= $msg['dias_antes'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Texto da Mensagem (WhatsApp)</label>
                        <textarea name="msgs[<?= $msg['id'] ?>][texto]" rows="6" required><?= htmlspecialchars($msg['texto']) ?></textarea>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="floating-bar">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Salvar Todas as Configurações
                </button>
            </div>
        </form>
    </main>
    
    <script>
        // Atualiza o texto do label (Ativado/Desativado) dinamicamente ao clicar no toggle
        document.querySelectorAll('.toggle-container input').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const label = this.parentElement.querySelector('.toggle-label');
                if(this.checked) {
                    label.textContent = 'Ativado';
                    label.style.color = 'var(--success)';
                } else {
                    label.textContent = 'Desativado';
                    label.style.color = 'var(--text-dim)';
                }
            });
        });
    </script>
</body>
</html>
