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
        
        if (!empty($_POST['id'])) {
            $stmt = $conn->prepare("UPDATE meetup_whatsapp_templates SET cenario = ?, minutos_antes = ?, template_texto = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$cenario, $minutos, $texto, $ativo, $_POST['id']]);
        } else {
            $stmt = $conn->prepare("INSERT INTO meetup_whatsapp_templates (cenario, minutos_antes, template_texto, ativo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$cenario, $minutos, $texto, $ativo]);
        }
        header('Location: meetup_templates.php?msg=Template salvo com sucesso!');
        exit;
    }
}

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM meetup_whatsapp_templates WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    header('Location: meetup_templates.php?msg=Template excluido');
    exit;
}

$templates = $conn->query("SELECT * FROM meetup_whatsapp_templates ORDER BY minutos_antes DESC")->fetchAll();
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
                        <label>Cenário (Ex: Lembrete, Hora Exata)</label>
                        <input type="text" name="cenario" id="cenario" required>
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
                    
                    <button type="submit" name="save_template" class="btn btn-primary">Salvar Template</button>
                    <button type="button" class="btn btn-secondary" id="btn_cancel" style="display:none;" onclick="resetForm()">Cancelar</button>
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
