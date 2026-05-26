<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Lógica de Salvar/Adicionar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_group'])) {
        $nome = trim($_POST['nome']);
        $group_id = trim($_POST['group_id']);
        $categoria = $_POST['categoria'];
        $language_id = ($categoria === 'especifico' && !empty($_POST['language_id'])) ? (int)$_POST['language_id'] : null;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        try {
            if (!empty($_POST['id'])) {
                // Atualizar
                $stmt = $conn->prepare("UPDATE meetup_whatsapp_groups SET nome = ?, group_id = ?, categoria = ?, language_id = ?, ativo = ? WHERE id = ?");
                $stmt->execute([$nome, $group_id, $categoria, $language_id, $ativo, $_POST['id']]);
                $msg = "Grupo atualizado com sucesso!";
            } else {
                // Inserir
                $stmt = $conn->prepare("INSERT INTO meetup_whatsapp_groups (nome, group_id, categoria, language_id, ativo) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $group_id, $categoria, $language_id, $ativo]);
                $msg = "Novo grupo adicionado com sucesso!";
            }
        } catch (PDOException $e) {
            $msg = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

// Lógica de Excluir
if (isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM meetup_whatsapp_groups WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header('Location: meetup_groups.php?msg=Grupo excluido');
        exit;
    } catch (PDOException $e) {
        $msg = "Erro ao excluir: " . $e->getMessage();
    }
}

// Lógica de Buscar IDs da API
$api_groups = [];
if (isset($_GET['fetch_api'])) {
    $headers = ["apikey: SenhaMeetups2026"];
    
    // Tentativa 1: fetchAllGroups (sem getParticipants)
    $ch1 = curl_init("http://136.248.92.126:8080/group/fetchAllGroups/meetups");
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
    $res1 = curl_exec($ch1);
    $code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
    curl_close($ch1);
    
    // Tentativa 2: findChats
    $ch2 = curl_init("http://136.248.92.126:8080/chat/findChats/meetups");
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
    $res2 = curl_exec($ch2);
    $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    $found = false;
    
    if ($code1 === 200 && $res1) {
        $dec1 = json_decode($res1, true);
        if (is_array($dec1) && !empty($dec1)) {
            $api_groups = $dec1;
            $found = true;
        }
    }
    
    if (!$found && $code2 === 200 && $res2) {
        $dec2 = json_decode($res2, true);
        if (is_array($dec2) && !empty($dec2)) {
            // FindChats retorna todas as conversas, filtramos por grupo
            foreach ($dec2 as $chat) {
                // Algumas versoes da API trazem o array em chaves diferentes
                $id = $chat['id'] ?? ($chat['remoteJid'] ?? '');
                $name = $chat['name'] ?? ($chat['pushName'] ?? 'Sem Nome');
                
                if (strpos($id, '@g.us') !== false) {
                    $api_groups[] = [
                        'id' => $id,
                        'subject' => $name
                    ];
                }
            }
            if (!empty($api_groups)) $found = true;
        }
    }
    
    if (!$found) {
        // Formata os retornos para depuracao
        $r1_debug = htmlspecialchars(substr($res1 ?: 'Vazio', 0, 100));
        $r2_debug = htmlspecialchars(substr($res2 ?: 'Vazio', 0, 100));
        $api_error = "Nenhum grupo localizado. fetchAllGroups($code1): $r1_debug | findChats($code2): $r2_debug";
    }
}

// Buscar idiomas para o select
$languages = [];
try {
    $languages = $conn->query("SELECT id, name FROM languages ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    // Falha silenciosa ou avisa
}

// Buscar grupos cadastrados
$groups = [];
try {
    $stmt = $conn->query("
        SELECT g.*, l.name as language_name 
        FROM meetup_whatsapp_groups g 
        LEFT JOIN languages l ON g.language_id = l.id 
        ORDER BY g.categoria, g.nome ASC
    ");
    $groups = $stmt->fetchAll();
} catch (PDOException $e) {
    $api_error = "Erro no banco: " . $e->getMessage() . ". As tabelas provavelmente ainda não foram criadas.";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos WhatsApp (Meetups) | Admin</title>
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
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.error { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border-color: rgba(227, 29, 28, 0.2); }
        
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: 15px; overflow: hidden; }
        th { text-align: left; padding: 15px; background: rgba(0,0,0,0.1); color: var(--text-dim); }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; }
        .badge.multi { background: rgba(56, 189, 248, 0.1); color: #38bdf8; }
        .badge.spec { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; color: white; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--accent-red); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }
        
        .form-card { background: var(--card-bg); padding: 25px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; color: var(--text-dim); }
        input[type="text"], select { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; }
        
        .api-list { background: rgba(0,0,0,0.2); padding: 15px; border-radius: 10px; margin-top: 20px; max-height: 300px; overflow-y: auto; }
        .api-item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .api-item button { background: none; border: 1px solid #38bdf8; color: #38bdf8; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div>
                <h2>Grupos de Automação (Meetups)</h2>
                <p style="color: var(--text-dim);">Gerencie os grupos que receberão as mensagens dos encontros</p>
            </div>
            <a href="?fetch_api=1" class="btn btn-secondary"><i class="fas fa-sync"></i> Buscar IDs na API (Oracle)</a>
        </header>

        <?php if (isset($msg) || isset($_GET['msg'])): ?>
            <div class="alert"><?= htmlspecialchars($msg ?? $_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if (isset($api_error)): ?>
            <div class="alert error"><?= htmlspecialchars($api_error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['fetch_api']) && !isset($api_error)): ?>
            <div class="form-card">
                <h3><i class="fab fa-whatsapp"></i> Grupos Encontrados na API</h3>
                <p style="color: var(--text-dim); margin-top: 5px;">Estes são os grupos onde o número conectado atualmente faz parte. Copie o ID para cadastrar abaixo.</p>
                <div class="api-list">
                    <?php if (empty($api_groups)): ?>
                        <p>Nenhum grupo encontrado.</p>
                    <?php else: ?>
                        <?php foreach ($api_groups as $ag): ?>
                            <div class="api-item">
                                <div>
                                    <strong><?= htmlspecialchars($ag['subject'] ?? 'Sem Nome') ?></strong><br>
                                    <small style="color: var(--text-dim);"><?= htmlspecialchars($ag['id']) ?></small>
                                </div>
                                <button onclick="preencherId('<?= $ag['id'] ?>', '<?= addslashes($ag['subject'] ?? '') ?>')">Usar ID</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h3 id="form-title">Adicionar Novo Grupo</h3>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="id" id="group_id_db">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Nome do Grupo (Identificação Interna)</label>
                        <input type="text" name="nome" id="nome" required placeholder="Ex: Geral Meetups">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp Group ID (12036... @g.us)</label>
                        <input type="text" name="group_id" id="group_id" required>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="categoria" id="categoria" onchange="toggleLang(this.value)" required>
                            <option value="multi_idioma">Múltiplos Idiomas (Recebe tudo)</option>
                            <option value="especifico">Idioma Específico</option>
                        </select>
                    </div>
                    <div class="form-group" id="lang_box" style="display: none;">
                        <label>Idioma Vinculado</label>
                        <select name="language_id" id="language_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($languages as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="ativo" id="ativo" checked> Grupo Ativo
                    </label>
                </div>
                <button type="submit" name="save_group" class="btn btn-primary">Salvar Grupo</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()" style="display:none;" id="btn_cancel">Cancelar</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nome do Grupo</th>
                    <th>ID (WhatsApp)</th>
                    <th>Categoria / Idioma</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($g['nome']) ?></strong></td>
                    <td style="color: var(--text-dim); font-size: 0.9rem;"><?= htmlspecialchars($g['group_id']) ?></td>
                    <td>
                        <?php if ($g['categoria'] == 'multi_idioma'): ?>
                            <span class="badge multi">Múltiplos Idiomas</span>
                        <?php else: ?>
                            <span class="badge spec">Específico: <?= htmlspecialchars($g['language_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $g['ativo'] ? '<span style="color:var(--success);">Ativo</span>' : '<span style="color:var(--text-dim);">Inativo</span>' ?></td>
                    <td>
                        <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.9rem;" 
                            onclick="editGroup(<?= $g['id'] ?>, '<?= addslashes($g['nome']) ?>', '<?= $g['group_id'] ?>', '<?= $g['categoria'] ?>', '<?= $g['language_id'] ?>', <?= $g['ativo'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?delete=<?= $g['id'] ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.9rem; color: var(--accent-red);" onclick="return confirm('Excluir?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <script>
        function toggleLang(val) {
            const box = document.getElementById('lang_box');
            if (val === 'especifico') {
                box.style.display = 'block';
                document.getElementById('language_id').required = true;
            } else {
                box.style.display = 'none';
                document.getElementById('language_id').required = false;
                document.getElementById('language_id').value = '';
            }
        }

        function preencherId(id, nome) {
            document.getElementById('group_id').value = id;
            if (!document.getElementById('nome').value) {
                document.getElementById('nome').value = nome;
            }
            window.scrollTo(0, document.querySelector('.form-card').offsetTop - 20);
        }

        function editGroup(id, nome, group_id, categoria, language_id, ativo) {
            document.getElementById('form-title').textContent = 'Editar Grupo';
            document.getElementById('group_id_db').value = id;
            document.getElementById('nome').value = nome;
            document.getElementById('group_id').value = group_id;
            document.getElementById('categoria').value = categoria;
            toggleLang(categoria);
            if (language_id) document.getElementById('language_id').value = language_id;
            document.getElementById('ativo').checked = (ativo == 1);
            document.getElementById('btn_cancel').style.display = 'inline-block';
            window.scrollTo(0, document.querySelector('.form-card').offsetTop - 20);
        }

        function resetForm() {
            document.getElementById('form-title').textContent = 'Adicionar Novo Grupo';
            document.getElementById('group_id_db').value = '';
            document.getElementById('nome').value = '';
            document.getElementById('group_id').value = '';
            document.getElementById('categoria').value = 'multi_idioma';
            toggleLang('multi_idioma');
            document.getElementById('ativo').checked = true;
            document.getElementById('btn_cancel').style.display = 'none';
        }
    </script>
</body>
</html>
