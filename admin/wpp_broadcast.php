<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$msg = null;
$error = null;

require_once '../includes/whatsapp_helper.php';

// Disparar broadcast via Baileys Server Queue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enfileirar'])) {
    $titulo = trim($_POST['titulo']);
    $mensagem = trim($_POST['mensagem']);
    $categoria = $_POST['categoria'];
    $language_id = ($categoria === 'especifico' && !empty($_POST['language_id'])) ? (int)$_POST['language_id'] : null;

    if (empty($titulo) || empty($mensagem)) {
        $error = "Título e mensagem são obrigatórios.";
    } else {
        try {
            // Obter grupos a serem afetados
            $sql_grupos = "SELECT group_id FROM meetup_whatsapp_groups WHERE ativo = 1";
            $params = [];
            
            if ($categoria === 'multi_idioma') {
                $sql_grupos .= " AND categoria = 'multi_idioma'";
            } elseif ($categoria === 'especifico') {
                $sql_grupos .= " AND categoria = 'especifico' AND language_id = ?";
                $params[] = $language_id;
            }
            
            $stmt = $conn->prepare($sql_grupos);
            $stmt->execute($params);
            $grupos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $total_grupos = count($grupos);

            if ($total_grupos > 0) {
                // Enviar os grupos diretamente para a fila nativa do Baileys
                $result = enviarWhatsApp($grupos, $mensagem, 'admin_broadcast');
                
                if ($result['success']) {
                    $stmt = $conn->prepare("INSERT INTO wpp_broadcast_queue (titulo, mensagem, filtro_categoria, filtro_language_id, total_grupos, enviados, status, iniciado_em, concluido_em) VALUES (?, ?, ?, ?, ?, ?, 'concluido', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                    $stmt->execute([$titulo, $mensagem, $categoria, $language_id, $total_grupos, $total_grupos]);
                    
                    // PRG: redireciona para GET para evitar re-envio do formulário no reload
                    header('Location: wpp_broadcast.php?msg=Disparo+concluído+com+sucesso%21');
                    exit;
                } else {
                    $erroMsg = $result['error'] ?? 'Erro desconhecido na API do Baileys.';
                    $error = "Falha ao enviar para o motor de disparo: " . $erroMsg;
                    
                    // Registra como erro se falhar na largada
                    $stmt = $conn->prepare("INSERT INTO wpp_broadcast_queue (titulo, mensagem, filtro_categoria, filtro_language_id, total_grupos, status, concluido_em) VALUES (?, ?, ?, ?, ?, 'erro', CURRENT_TIMESTAMP)");
                    $stmt->execute([$titulo, $mensagem, $categoria, $language_id, $total_grupos]);
                }
            } else {
                $error = "Nenhum grupo ativo encontrado para esta categoria. O disparo não foi realizado.";
            }
        } catch (PDOException $e) {
            $error = "Erro no banco de dados: " . $e->getMessage();
        }
    }
}

// Excluir broadcast do histórico (erro ou concluído)
if (isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM wpp_broadcast_queue WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header('Location: wpp_broadcast.php?msg=Broadcast excluído do histórico');
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao excluir: " . $e->getMessage();
    }
}

// Buscar idiomas para o select
$languages = [];
try {
    $languages = $conn->query("SELECT id, name FROM languages ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {}

// Buscar histórico
$historico = [];
try {
    $historico = $conn->query("
        SELECT q.*, l.name as language_name 
        FROM wpp_broadcast_queue q 
        LEFT JOIN languages l ON q.filtro_language_id = l.id 
        ORDER BY q.criado_em DESC LIMIT 20
    ")->fetchAll();


} catch (PDOException $e) {
    // Se a tabela não existir, ignora (ou exibe aviso)
    $error = "Erro ao carregar histórico: " . $e->getMessage() . " (Execute a migração primeiro se não tiver feito).";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disparar Mensagem | Admin</title>
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
            --info: #3b82f6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { margin-bottom: 30px; }
        
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid transparent; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--success); border-color: rgba(16, 185, 129, 0.2); }
        .alert-error { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border-color: rgba(227, 29, 28, 0.2); }
        
        .card { background: var(--card-bg); padding: 25px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; color: var(--text-dim); }
        input[type="text"], select, textarea { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; font-family: inherit; }
        textarea { min-height: 150px; resize: vertical; }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; color: white; display: inline-block; transition: 0.2s; }
        .btn-primary { background: var(--accent-red); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }
        
        .preview-box { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: var(--info); padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; padding: 15px; background: rgba(0,0,0,0.1); color: var(--text-dim); }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; }
        .bg-pending { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .bg-sending { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .bg-done { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .bg-error { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); }

        .progress-bar-container { background: var(--input-bg); border-radius: 10px; height: 10px; width: 100%; overflow: hidden; margin-top: 5px; }
        .progress-bar { background: var(--info); height: 100%; transition: width 0.3s ease; }
        .progress-bar.done { background: var(--success); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- WhatsApp Sub-Nav -->
        <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
            <a href="meetup_groups.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'meetup_groups.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fab fa-whatsapp"></i> Configurar Grupos</a>
            <a href="meetup_templates.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'meetup_templates.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-comment-dots"></i> Templates de Mensagem</a>
            <a href="wpp_broadcast.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'wpp_broadcast.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-bullhorn"></i> Disparar Mensagem</a>
            <a href="wpp_resumo_semanal.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'wpp_resumo_semanal.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-list-alt"></i> Resumo Semanal</a>
            <a href="conectar_whatsapp.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'conectar_whatsapp.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-qrcode"></i> Conexão e Status</a>
        </div>

        <header class="header">
            <h2>📢 Canhão de Disparo WhatsApp</h2>
            <p style="color: var(--text-dim);">Envie mensagens para múltiplos grupos simultaneamente com delay automático anti-ban.</p>
        </header>

        <?php if ($msg || isset($_GET['msg'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg ?? $_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div class="card">
                <h3>Compor Mensagem</h3>
                <form method="POST" style="margin-top: 20px;">
                    <div class="form-group">
                        <label>Título do Disparo (Uso interno)</label>
                        <input type="text" name="titulo" value="<?= htmlspecialchars($_POST['prefill_title'] ?? '') ?>" required placeholder="Ex: Encontros da Semana 1">
                    </div>
                    
                    <div class="form-group">
                        <label>Mensagem a disparar</label>
                        <textarea name="mensagem" id="mensagem" required placeholder="Cole sua mensagem aqui. Emojis, links e formatação do WhatsApp (*negrito*, _itálico_) serão preservados." oninput="countChars(this)"><?= htmlspecialchars($_POST['prefill_message'] ?? '') ?></textarea>
                        <small style="color: var(--text-dim); display: block; text-align: right; margin-top: 5px;"><span id="charCount">0</span> caracteres</small>
                    </div>

                    <div class="form-group">
                        <label>Público Alvo</label>
                        <select name="categoria" id="categoria" onchange="toggleLang(); updatePreview();" required>
                            <option value="todos">Todos os Grupos Ativos (Múltiplos + Específicos)</option>
                            <option value="multi_idioma">Apenas Múltiplos Idiomas</option>
                            <option value="especifico">Apenas Idioma Específico</option>
                        </select>
                    </div>

                    <div class="form-group" id="lang_box" style="display: none;">
                        <label>Qual idioma?</label>
                        <select name="language_id" id="language_id" onchange="updatePreview();">
                            <option value="">Selecione um idioma...</option>
                            <?php foreach ($languages as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="preview-box">
                        <i class="fas fa-users" style="font-size: 1.5rem;"></i>
                        <div>
                            <span style="font-size: 1.2rem; font-weight: bold;" id="preview_count">Calculando...</span>
                            <br><small style="color: var(--text-main);">grupos receberão esta mensagem.</small>
                        </div>
                    </div>

                    <button type="submit" name="enfileirar" class="btn btn-primary" style="width: 100%;"><i class="fas fa-paper-plane"></i> Disparar Mensagem</button>
                    <p style="text-align: center; color: var(--text-dim); font-size: 0.85rem; margin-top: 10px;">O sistema cuidará do envio no plano de fundo automaticamente (delay de 5s entre cada mensagem).</p>
                </form>
            </div>

            <div class="card" style="overflow-y: auto; max-height: 800px;">
                <h3>Histórico de Disparos</h3>
                <?php if (empty($historico)): ?>
                    <p style="color: var(--text-dim); margin-top: 15px;">Nenhum disparo na fila.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Disparo</th>
                                <th>Status</th>
                                <th>Progresso</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico as $h): 
                                $perc = $h['total_grupos'] > 0 ? floor(($h['enviados'] / $h['total_grupos']) * 100) : 0;
                                $badgeClass = 'bg-pending';
                                $statusLabel = 'Pendente';
                                if ($h['status'] === 'enviando') { $badgeClass = 'bg-sending'; $statusLabel = 'Enviando...'; }
                                if ($h['status'] === 'concluido') { $badgeClass = 'bg-done'; $statusLabel = 'Concluído'; }
                                if ($h['status'] === 'erro') { $badgeClass = 'bg-error'; $statusLabel = 'Erro/Cancelado'; }
                                
                                // Ajuste de fuso horário UTC para UTC-3 (America/Sao_Paulo)
                                try {
                                    $dt = new DateTime($h['criado_em'], new DateTimeZone('UTC'));
                                    $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                    $data_formatada = $dt->format('d/m/Y H:i');
                                } catch (Exception $e) {
                                    $data_formatada = date('d/m/Y H:i', strtotime($h['criado_em']));
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($h['titulo']) ?></strong><br>
                                    <small style="color: var(--text-dim);"><?= $data_formatada ?></small><br>
                                    <small style="color: var(--text-dim);">Filtro: <?= $h['filtro_categoria'] == 'especifico' ? 'Especifico ('.$h['language_name'].')' : $h['filtro_categoria'] ?></small>
                                </td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span></td>
                                <td style="width: 120px;">
                                    <small><?= $h['enviados'] ?> / <?= $h['total_grupos'] ?> (<?= $perc ?>%)</small>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar <?= $h['status'] === 'concluido' ? 'done' : '' ?>" style="width: <?= $perc ?>%;"></div>
                                    </div>
                                </td>
                                <td>
                                        <a href="?delete=<?= $h['id'] ?>" class="btn btn-secondary" style="padding: 5px 10px; color: var(--accent-red);" title="Excluir do Histórico" onclick="return confirm('Excluir este item do histórico?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function countChars(obj){
            document.getElementById('charCount').innerHTML = obj.value.length;
        }

        function toggleLang() {
            const val = document.getElementById('categoria').value;
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

        function updatePreview() {
            const cat = document.getElementById('categoria').value;
            const lang = document.getElementById('language_id').value;
            
            document.getElementById('preview_count').innerHTML = 'Calculando...';

            const formData = new FormData();
            formData.append('categoria', cat);
            if(lang) formData.append('language_id', lang);

            fetch('ajax/wpp_broadcast_preview.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.count !== undefined) {
                    document.getElementById('preview_count').innerHTML = data.count;
                } else {
                    document.getElementById('preview_count').innerHTML = 'Erro';
                }
            })
            .catch(err => {
                document.getElementById('preview_count').innerHTML = 'Erro de conexão';
            });
        }

        // Call preview on load
        updatePreview();
        

    </script>
</body>
</html>

