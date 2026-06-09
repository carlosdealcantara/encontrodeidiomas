<?php
session_start();
set_time_limit(0); // Impede que o PHP corte a execução antes de 120 segundos
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$msg = null;
$api_error = null;

// Lógica de Importação em Lote (Batch Import)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_batch'])) {
    $selected_groups = $_POST['selected_groups'] ?? [];
    $categoria = $_POST['batch_categoria'];
    $language_id = ($categoria === 'especifico' && !empty($_POST['batch_language_id'])) ? (int)$_POST['batch_language_id'] : null;
    $ativo = isset($_POST['batch_ativo']) ? 1 : 0;
    
    if (!empty($selected_groups)) {
        try {
            $imported = 0;
            $stmt = $conn->prepare("INSERT INTO meetup_whatsapp_groups (nome, group_id, categoria, language_id, ativo) VALUES (?, ?, ?, ?, ?)");
            $stmtCheck = $conn->prepare("SELECT id FROM meetup_whatsapp_groups WHERE group_id = ? AND categoria = ? AND (language_id = ? OR (language_id IS NULL AND ? IS NULL))");
            
            foreach ($selected_groups as $group_json) {
                $group_data = json_decode($group_json, true);
                if ($group_data && isset($group_data['id'])) {
                    $g_id = $group_data['id'];
                    $g_subject = $group_data['subject'] ?? 'Sem Nome';
                    
                    // Verificar duplicidade
                    $stmtCheck->execute([$g_id, $categoria, $language_id, $language_id]);
                    if ($stmtCheck->rowCount() === 0) {
                        $stmt->execute([$g_subject, $g_id, $categoria, $language_id, $ativo]);
                        $imported++;
                    }
                }
            }
            $msg = "Importação em lote concluída! $imported novos grupos cadastrados.";
        } catch (PDOException $e) {
            $api_error = "Erro ao importar em lote: " . $e->getMessage();
        }
    } else {
        $api_error = "Nenhum grupo foi selecionado para importação.";
    }
}

// Lógica de Salvar/Adicionar/Editar Individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_group'])) {
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
        $api_error = "Erro ao salvar grupo: " . $e->getMessage();
    }
}

// Lógica de Excluir
if (isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM meetup_whatsapp_groups WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header('Location: meetup_groups.php?msg=Grupo excluído com sucesso!');
        exit;
    } catch (PDOException $e) {
        $api_error = "Erro ao excluir grupo: " . $e->getMessage();
    }
}

// Lógica de Buscar IDs (usando cache pré-carregado para evitar timeout da Hostinger)
$api_groups = [];
if (isset($_GET['fetch_api'])) {
    $cache_file = __DIR__ . '/groups_cache.json';
    if (file_exists($cache_file)) {
        $res1 = file_get_contents($cache_file);
        // Remove BOM if present
        $res1 = preg_replace('/^[\xef\xbb\xbf]+/', '', $res1);
        $dec1 = json_decode($res1, true);
        if (is_array($dec1)) {
            $api_groups = $dec1;
        } else {
            $api_error = "Formato de retorno do cache inválido.";
        }
    } else {
        $api_error = "Cache de grupos não encontrado. O sistema está carregando a lista, tente novamente em 1 minuto.";
    }
}

// Forçar o fechamento da conexão antiga e abrir uma nova (Evita erro 2006 MySQL server has gone away)
$conn = null;
$conn = connectDB();
try {
    $conn->exec("SET SESSION wait_timeout = 600");
} catch (Exception $e) {}

// Buscar idiomas para o select
$languages = [];
try {
    $languages = $conn->query("SELECT id, name FROM languages ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    // Falha silenciosa
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
    $api_error = "Erro no banco: " . $e->getMessage();
}

$registered_groups = [];
foreach ($groups as $g) {
    if (!isset($registered_groups[$g['group_id']])) {
        $registered_groups[$g['group_id']] = ['is_multi' => false, 'languages' => []];
    }
    if ($g['categoria'] === 'multi_idioma') {
        $registered_groups[$g['group_id']]['is_multi'] = true;
    } else {
        $registered_groups[$g['group_id']]['languages'][] = $g['language_name'];
    }
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
        
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: 15px; overflow: hidden; margin-bottom: 30px; }
        th { text-align: left; padding: 15px; background: rgba(0,0,0,0.1); color: var(--text-dim); }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; }
        .badge.multi { background: rgba(56, 189, 248, 0.1); color: #38bdf8; }
        .badge.spec { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .badge.registered { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; color: white; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--accent-red); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }
        .btn-success { background: var(--success); }
        .btn-success:hover { opacity: 0.9; }
        
        .form-card { background: var(--card-bg); padding: 25px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; color: var(--text-dim); }
        input[type="text"], select { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; }
        
        .api-list { background: rgba(0,0,0,0.2); padding: 15px; border-radius: 10px; margin-top: 20px; max-height: 400px; overflow-y: auto; }
        .api-item { display: flex; align-items: center; justify-content: space-between; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .api-item:last-child { border-bottom: none; }
        .api-item input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
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
            <div>
                <h2>Grupos de Automação (Meetups)</h2>
                <p style="color: var(--text-dim);">Gerencie os grupos que receberão as mensagens dos encontros</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="?fetch_api=1" class="btn btn-primary"><i class="fas fa-sync"></i> Buscar Grupos na API</a>
            </div>
        </header>

        <?php if (isset($msg) || isset($_GET['msg'])): ?>
            <div class="alert"><?= htmlspecialchars($msg ?? $_GET['msg'] ?? '') ?></div>
        <?php endif; ?>
        <?php if ($api_error): ?>
            <div class="alert error"><?= htmlspecialchars($api_error) ?></div>
        <?php endif; ?>

        <!-- Importação em Lote -->
        <?php if (isset($_GET['fetch_api']) && !$api_error): ?>
            <div class="form-card">
                <h3><i class="fab fa-whatsapp"></i> Importar Grupos da API (Lote)</h3>
                <p style="color: var(--text-dim); margin-top: 5px; margin-bottom: 20px;">Selecione os grupos abaixo e defina a categoria/idioma padrão para eles.</p>
                
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label>Categoria para os Selecionados</label>
                            <select name="batch_categoria" onchange="toggleBatchLang(this.value)" required>
                                <option value="multi_idioma">Múltiplos Idiomas (Recebe tudo)</option>
                                <option value="especifico">Idioma Específico</option>
                            </select>
                        </div>
                        <div class="form-group" id="batch_lang_box" style="display: none;">
                            <label>Idioma Vinculado</label>
                            <select name="batch_language_id" id="batch_language_id">
                                <option value="">Selecione...</option>
                                <?php foreach ($languages as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <label style="margin-top: 15px;">
                                <input type="checkbox" name="batch_ativo" checked> Importar como Ativo
                            </label>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="font-weight: bold; color: var(--text-dim);">Lista de Grupos Disponíveis:</span>
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" id="select_all_api" onclick="toggleSelectAll(this)"> Selecionar Todos (Visíveis)
                        </label>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <input type="text" id="search_groups" placeholder="Pesquisar nome do grupo..." onkeyup="filterGroups()" style="width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px;">
                    </div>

                    <div class="api-list" id="api_list_container">
                        <?php if (empty($api_groups)): ?>
                            <p style="padding: 15px; text-align: center; color: var(--text-dim);">Nenhum grupo encontrado na API.</p>
                        <?php else: ?>
                            <?php foreach ($api_groups as $ag): 
                                $reg_data = $registered_groups[$ag['id']] ?? null;
                                $is_registered = $reg_data !== null;
                                $is_multi = $is_registered && $reg_data['is_multi'];
                            ?>
                                <div class="api-item">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <?php if (!$is_multi): ?>
                                            <input type="checkbox" name="selected_groups[]" class="api-checkbox" 
                                                value="<?= htmlspecialchars(json_encode(['id' => $ag['id'], 'subject' => $ag['subject']])) ?>">
                                        <?php else: ?>
                                            <input type="checkbox" disabled checked style="opacity: 0.5;">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($ag['subject'] ?? 'Sem Nome') ?></strong><br>
                                            <small style="color: var(--text-dim);"><?= htmlspecialchars($ag['id']) ?></small>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($is_registered): ?>
                                            <?php if ($reg_data['is_multi']): ?>
                                                <span class="badge registered" style="margin-left: 10px;"><i class="fas fa-globe"></i> Múltiplos</span>
                                            <?php else: ?>
                                                <span class="badge spec" style="margin-left: 10px;"><i class="fas fa-check"></i> <?= htmlspecialchars(implode(', ', $reg_data['languages'])) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" name="import_batch" class="btn btn-success"><i class="fas fa-download"></i> Importar Selecionados em Lote</button>
                        <a href="meetup_groups.php" class="btn btn-secondary">Fechar Busca</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Cadastro Individual -->
        <div class="form-card">
            <h3 id="form-title">Adicionar Novo Grupo (Manual)</h3>
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

        <!-- Tabela de Grupos Cadastrados -->
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
                    <td style="color: var(--text-dim); font-size: 0.9rem;">
                        <?= htmlspecialchars($g['group_id']) ?>
                    </td>
                    <td>
                        <?php if ($g['categoria'] == 'multi_idioma'): ?>
                            <span class="badge multi">Múltiplos Idiomas</span>
                        <?php else: ?>
                            <span class="badge spec">Específico: <?= htmlspecialchars($g['language_name'] ?? 'Idioma Removido') ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $g['ativo'] ? '<span style="color:var(--success);">Ativo</span>' : '<span style="color:var(--text-dim);">Inativo</span>' ?></td>
                    <td>
                        <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.9rem;" 
                            onclick="editGroup(<?= $g['id'] ?>, '<?= addslashes($g['nome']) ?>', '<?= $g['group_id'] ?>', '<?= $g['categoria'] ?>', '<?= $g['language_id'] ?>', <?= $g['ativo'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?delete=<?= $g['id'] ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.9rem; color: var(--accent-red);" onclick="return confirm('Tem certeza que deseja excluir este grupo?')">
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

        function toggleBatchLang(val) {
            const box = document.getElementById('batch_lang_box');
            if (val === 'especifico') {
                box.style.display = 'block';
                document.getElementById('batch_language_id').required = true;
            } else {
                box.style.display = 'none';
                document.getElementById('batch_language_id').required = false;
                document.getElementById('batch_language_id').value = '';
            }
        }

        function toggleSelectAll(master) {
            const items = document.querySelectorAll('.api-item');
            items.forEach(item => {
                if (item.style.display !== 'none') {
                    const cb = item.querySelector('.api-checkbox');
                    if (cb && !cb.disabled) {
                        cb.checked = master.checked;
                    }
                }
            });
        }

        function filterGroups() {
            const query = document.getElementById('search_groups').value.toLowerCase();
            const items = document.querySelectorAll('.api-item');
            
            items.forEach(item => {
                const nameText = item.querySelector('strong').innerText.toLowerCase();
                const idText = item.querySelector('small').innerText.toLowerCase();
                if (nameText.includes(query) || idText.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Desmarca o master para evitar confusão de seleção
            document.getElementById('select_all_api').checked = false;
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
            window.scrollTo(0, document.getElementById('form-title').offsetTop - 20);
        }

        function resetForm() {
            document.getElementById('form-title').textContent = 'Adicionar Novo Grupo (Manual)';
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
