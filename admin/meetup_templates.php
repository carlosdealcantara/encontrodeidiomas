<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_template'])) {
        $cenario = trim($_POST['cenario']);
        $minutos = (int)$_POST['minutos_antes'];
        $texto = trim($_POST['template_texto']);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        try {
            if (!empty($_POST['id'])) {
                $stmt = $conn->prepare("UPDATE meetup_whatsapp_templates SET cenario = ?, minutos_antes = ?, template_texto = ?, ativo = ? WHERE id = ?");
                $stmt->execute([$cenario, $minutos, $texto, $ativo, $_POST['id']]);
            } else {
                $stmt = $conn->prepare("INSERT INTO meetup_whatsapp_templates (cenario, minutos_antes, template_texto, ativo) VALUES (?, ?, ?, ?)");
                $stmt->execute([$cenario, $minutos, $texto, $ativo]);
            }
            header('Location: meetup_templates.php?msg=Template salvo com sucesso!');
            exit;
        } catch (PDOException $e) {
            $msg = "Erro ao salvar: " . $e->getMessage();
            $_GET['msg'] = $msg;
        }
    }
    
    if (isset($_POST['test_template'])) {
        $telefone = preg_replace('/\D/', '', $_POST['test_number']);
        if (strlen($telefone) < 10) {
            $_GET['msg'] = "Por favor, insira um número válido com DDD.";
        } else {
            // Adiciona código do Brasil se não tiver
            if (!str_starts_with($telefone, '55')) {
                $telefone = '55' . $telefone;
            }
            
            $textoBruto = $_POST['template_texto'];
            
            // Mock Data
            $textoFinal = str_replace('{IDIOMA}', 'INGLÊS (TESTE)', $textoBruto);
            $textoFinal = str_replace('{idioma}', 'Inglês', $textoFinal);
            $textoFinal = str_replace('{EMOJI_FLAG}', '🇺🇸', $textoFinal);
            $textoFinal = str_replace('{EMOJI_FLAGS}', '🇺🇸🇺🇸🇺🇸🇺🇸🇺🇸', $textoFinal);
            $textoFinal = str_replace('{SAUDACAO}', 'Hello!', $textoFinal);
            $textoFinal = str_replace('{MEET_LINK}', 'https://meet.google.com/abc-defg-hij', $textoFinal);
            $textoFinal = str_replace('{INSTAGRAM_LINK}', 'https://instagram.com/ingles.meetup', $textoFinal);
            
            // Mock Data para Resumo Diário
            $mockLista = "🇺🇸 English\n🇩🇪 Deutsch";
            $textoFinal = str_replace('{LISTA_ENCONTROS}', $mockLista, $textoFinal);
            
            require_once '../includes/whatsapp_helper.php';
            
            $result = enviarWhatsApp($telefone, $textoFinal, 'template_teste');
            $httpcode = $result['httpCode'];
            $response = json_encode($result);
            
            if ($httpcode >= 200 && $httpcode < 300) {
                $_GET['msg'] = "🚀 Mensagem de teste enviada com sucesso para $telefone!";
            } else {
                $_GET['msg'] = "❌ Erro ao enviar teste ($httpcode). Resposta: " . htmlspecialchars($response);
            }
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM meetup_whatsapp_templates WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header('Location: meetup_templates.php?msg=Template excluido');
        exit;
    } catch (PDOException $e) {
        $_GET['msg'] = "Erro ao excluir: " . $e->getMessage();
    }
}

$templates = [];
try {
    $templates = $conn->query("SELECT * FROM meetup_whatsapp_templates ORDER BY minutos_antes DESC")->fetchAll();
} catch (PDOException $e) {
    $_GET['msg'] = "Erro no banco: " . $e->getMessage() . ". As tabelas provavelmente ainda não foram criadas.";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates WhatsApp (Meetups) | Admin</title>
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
        
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 12px; margin-bottom: 20px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        
        .card { background: var(--card-bg); padding: 25px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; color: var(--text-dim); }
        input[type="text"], input[type="number"], textarea { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; font-family: 'Outfit', sans-serif; }
        textarea { resize: vertical; min-height: 200px; }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; color: white; display: inline-block; text-decoration: none; }
        .btn-primary { background: var(--accent-red); }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        
        .variables { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; }
        .var-chip { background: rgba(56, 189, 248, 0.1); color: #38bdf8; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; cursor: pointer; border: 1px solid rgba(56, 189, 248, 0.2); }
        .var-chip:hover { background: rgba(56, 189, 248, 0.2); }
        
        .template-list { margin-top: 30px; }
        .template-item { background: var(--input-bg); padding: 15px; border-radius: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255,255,255,0.05); }
        .template-status { font-size: 0.8rem; padding: 3px 8px; border-radius: 4px; }
        .status-on { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-off { background: rgba(255, 255, 255, 0.1); color: var(--text-dim); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- WhatsApp Sub-Nav -->
        <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
            <a href="meetup_groups.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'meetup_groups.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fab fa-whatsapp"></i> Configurar Grupos</a>
            <a href="meetup_templates.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'meetup_templates.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-comment-dots"></i> Templates de Mensagem</a>
            <a href="conectar_whatsapp.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'conectar_whatsapp.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-qrcode"></i> Conexão e Status</a>
        </div>

        <header class="header">
            <h2>Templates de Mensagem (Meetups)</h2>
            <p style="color: var(--text-dim);">Configure as mensagens que serão disparadas (ex: hora exata, lembrete).</p>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <h3 id="form-title" style="margin-bottom: 20px;">Adicionar Template</h3>
                <form method="POST">
                    <input type="hidden" name="id" id="template_id">
                    
                    <div class="form-group">
                        <label>Cenário do Disparo</label>
                        <select name="cenario" id="cenario" class="form-control" required>
                            <option value="Hora Exata">Hora Exata</option>
                            <option value="Resumo do Dia">Resumo do Dia</option>
                            <option value="Lembrete">Lembrete (Ex: 15 min antes)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Minutos de Antecedência (0 = Na hora exata)</label>
                        <input type="number" name="minutos_antes" id="minutos_antes" required value="0">
                        <small style="color: var(--text-dim);">Use 120 para 2 horas antes, 60 para 1 hora antes, etc.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Texto do WhatsApp</label>
                        <textarea name="template_texto" id="template_texto" required></textarea>
                        
                        <div class="variables">
                            <span class="var-chip" onclick="insertVar('{IDIOMA}')">{IDIOMA}</span>
                            <span class="var-chip" onclick="insertVar('{EMOJI_FLAG}')">{EMOJI_FLAG}</span>
                            <span class="var-chip" onclick="insertVar('{EMOJI_FLAGS}')">{EMOJI_FLAGS}</span>
                            <span class="var-chip" onclick="insertVar('{SAUDACAO}')">{SAUDACAO}</span>
                            <span class="var-chip" onclick="insertVar('{MEET_LINK}')">{MEET_LINK}</span>
                            <span class="var-chip" onclick="insertVar('{INSTAGRAM_LINK}')">{INSTAGRAM_LINK}</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><input type="checkbox" name="ativo" id="ativo" checked> Template Ativo</label>
                    </div>
                    
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="submit" name="save_template" class="btn btn-primary">Salvar Template</button>
                        <button type="button" class="btn btn-secondary" id="btn_cancel" style="display:none;" onclick="resetForm()">Cancelar</button>
                    </div>

                    <hr style="border-color: rgba(255,255,255,0.05); margin: 25px 0;">
                    
                    <h4 style="margin-bottom: 10px;">Testar Mensagem no Privado</h4>
                    <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 15px;">Quer ver como a mensagem acima vai ficar no WhatsApp antes de salvar? Digite seu número abaixo e receba um teste com dados fictícios (ex: Inglês).</p>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="test_number" placeholder="Seu Zap (Ex: 31988887777)" style="flex: 1;">
                        <button type="submit" name="test_template" class="btn btn-secondary"><i class="fas fa-paper-plane"></i> Disparar Teste</button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h3>Templates Cadastrados</h3>
                <div class="template-list">
                    <?php if (empty($templates)): ?>
                        <p style="color: var(--text-dim);">Nenhum template cadastrado.</p>
                    <?php endif; ?>
                    
                    <?php foreach ($templates as $t): ?>
                        <div class="template-item">
                            <div>
                                <strong><?= htmlspecialchars($t['cenario']) ?></strong>
                                <div style="color: var(--text-dim); font-size: 0.85rem; margin-top: 4px;">
                                    Tempo: <?= $t['minutos_antes'] == 0 ? 'Exato' : $t['minutos_antes'] . ' min antes' ?>
                                    <span class="template-status <?= $t['ativo'] ? 'status-on' : 'status-off' ?>" style="margin-left: 10px;">
                                        <?= $t['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </div>
                            </div>
                            <div style="display:flex; gap: 5px;">
                                <button class="btn btn-secondary" style="padding: 5px 10px;" onclick="editTemplate(<?= $t['id'] ?>, '<?= addslashes($t['cenario']) ?>', <?= $t['minutos_antes'] ?>, `<?= addslashes($t['template_texto']) ?>`, <?= $t['ativo'] ?>)"><i class="fas fa-edit"></i></button>
                                <a href="?delete=<?= $t['id'] ?>" class="btn btn-secondary" style="padding: 5px 10px; color: var(--accent-red);" onclick="return confirm('Excluir?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function insertVar(variable) {
            const txt = document.getElementById('template_texto');
            const start = txt.selectionStart;
            const end = txt.selectionEnd;
            txt.value = txt.value.substring(0, start) + variable + txt.value.substring(end);
            txt.focus();
            txt.selectionStart = start + variable.length;
            txt.selectionEnd = start + variable.length;
        }
        
        function editTemplate(id, cenario, minutos, texto, ativo) {
            document.getElementById('form-title').textContent = 'Editar Template';
            document.getElementById('template_id').value = id;
            document.getElementById('cenario').value = cenario;
            document.getElementById('minutos_antes').value = minutos;
            document.getElementById('template_texto').value = texto;
            document.getElementById('ativo').checked = (ativo == 1);
            document.getElementById('btn_cancel').style.display = 'inline-block';
        }
        
        function resetForm() {
            document.getElementById('form-title').textContent = 'Adicionar Template';
            document.getElementById('template_id').value = '';
            document.getElementById('cenario').value = '';
            document.getElementById('minutos_antes').value = '0';
            document.getElementById('template_texto').value = '';
            document.getElementById('ativo').checked = true;
            document.getElementById('btn_cancel').style.display = 'none';
        }
    </script>
</body>
</html>
