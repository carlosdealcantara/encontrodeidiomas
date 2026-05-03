<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Lógica de exclusão
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM useful_links WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header('Location: useful_links.php?msg=Link excluído');
    exit;
}

// Lógica de salvamento (Novo/Editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = $_POST['title'];
    $url      = $_POST['url'];
    $subtitle = $_POST['subtitle'];
    $badge    = $_POST['badge'];
    $icon     = $_POST['icon'];
    $layout   = $_POST['layout_type'];
    $order    = (int)$_POST['order_index'];
    $id       = (int)$_POST['id'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE useful_links SET title = ?, url = ?, subtitle = ?, badge = ?, icon = ?, layout_type = ?, order_index = ? WHERE id = ?");
        $stmt->execute([$title, $url, $subtitle, $badge, $icon, $layout, $order, $id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO useful_links (title, url, subtitle, badge, icon, layout_type, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $url, $subtitle, $badge, $icon, $layout, $order]);
    }
    header('Location: useful_links.php?msg=Link salvo com sucesso');
    exit;
}

$links = $conn->query("SELECT * FROM useful_links ORDER BY order_index DESC, title ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Links Úteis | Admin</title>
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.05); }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim); text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s ease; font-weight: 500; }
        .nav-item.active { background: var(--accent-red); color: white; }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center; }

        .form-inline { background: var(--card-bg); padding: 25px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 40px; }
        .form-grid { display: grid; grid-template-columns: 2fr 2fr 1fr 1fr auto; gap: 15px; align-items: end; }

        .form-group label { display: block; margin-bottom: 8px; font-size: 0.8rem; color: var(--text-dim); text-transform: uppercase; }
        .form-group input { width: 100%; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 10px; color: white; outline: none; }

        .btn-add { background: var(--accent-red); color: white; border: none; padding: 10px 25px; border-radius: 10px; font-weight: 700; cursor: pointer; }

        .links-list { display: grid; gap: 15px; }
        .link-item { background: var(--card-bg); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: space-between; }
        .link-info { display: flex; align-items: center; gap: 15px; }
        .link-icon { width: 40px; height: 40px; background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        
        .actions { display: flex; gap: 10px; }
        .action-btn { background: transparent; border: 1px solid rgba(255,255,255,0.1); color: var(--text-dim); width: 35px; height: 35px; border-radius: 8px; cursor: pointer; transition: 0.3s; }
        .action-btn:hover { color: white; border-color: white; }
        .btn-del:hover { color: #ef4444; border-color: #ef4444; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand" style="display:flex; align-items:center; gap:12px; margin-bottom:50px;">
            <div style="width:35px; height:35px; background:var(--accent-red); border-radius:8px; display:flex; align-items:center; justify-content:center; color:white; font-weight:700;">EI</div>
            <span style="font-size:1.2rem; font-weight:700;">ADMIN</span>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item">Dashboard</a>
            <a href="hosts.php" class="nav-item">Anfitriões</a>
            <a href="meetings.php" class="nav-item">Encontros</a>
            <a href="useful_links.php" class="nav-item active">Links Úteis</a>
            <a href="settings.php" class="nav-item">Configurações</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <div>
                <h2>Gestão de Links</h2>
                <p style="color: var(--text-dim);">Adicione ou remova links da página principal de recursos.</p>
            </div>
        </header>

        <form method="POST" class="form-inline">
            <input type="hidden" name="id" id="linkId" value="0">
            <div class="form-grid">
                <div class="form-group">
                    <label>Título</label>
                    <input type="text" name="title" id="linkTitle" placeholder="Ex: Grupo WhatsApp" required>
                </div>
                <div class="form-group">
                    <label>Descrição (Subtitle)</label>
                    <input type="text" name="subtitle" id="linkSubtitle" placeholder="Ex: O maior grupo do projeto">
                </div>
                <div class="form-group">
                    <label>URL</label>
                    <input type="url" name="url" id="linkUrl" placeholder="https://..." required>
                </div>
                <div class="form-group">
                    <label>Selo (Badge)</label>
                    <input type="text" name="badge" id="linkBadge" placeholder="Ex: Comece por aqui">
                </div>
                <div class="form-group">
                    <label>Ícone (FontAwesome)</label>
                    <input type="text" name="icon" id="linkIcon" placeholder="fab fa-whatsapp" value="fas fa-link">
                </div>
                <div class="form-group">
                    <label>Layout</label>
                    <select name="layout_type" id="linkLayout" style="width: 100%; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 10px; color: white; outline: none;">
                        <option value="standard">Padrão (Linha)</option>
                        <option value="twin">Bloco (Quadrado)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label title="Números maiores aparecem primeiro no topo">Prioridade</label>
                    <input type="number" name="order_index" id="linkOrder" value="0">
                </div>
                <button type="submit" class="btn-add" id="btnSubmit">Salvar</button>
            </div>
        </form>

        <div class="links-list">
            <?php foreach ($links as $l): ?>
                <div class="link-item">
                    <div class="link-info">
                        <div class="link-icon"><i class="<?= $l['icon'] ?>"></i></div>
                        <div>
                            <div style="font-weight: 600; display: flex; align-items: center; gap: 10px;">
                                <?= htmlspecialchars($l['title']) ?>
                                <?php if (($l['layout_type'] ?? 'standard') === 'twin'): ?>
                                    <span style="background: rgba(255,255,255,0.1); font-size: 0.65rem; padding: 2px 8px; border-radius: 4px; color: var(--text-dim); border: 1px solid rgba(255,255,255,0.1);">BLOCO</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-dim);"><?= htmlspecialchars($l['url']) ?></div>
                        </div>
                    </div>
                    <div class="actions">
                        <button class="action-btn" onclick="editLink(<?= htmlspecialchars(json_encode($l)) ?>)"><i class="fas fa-edit"></i></button>
                        <a href="useful_links.php?delete=1&id=<?= $l['id'] ?>" class="action-btn btn-del" onclick="return confirm('Excluir link?')"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        function editLink(link) {
            document.getElementById('linkId').value = link.id;
            document.getElementById('linkTitle').value = link.title;
            document.getElementById('linkSubtitle').value = link.subtitle || '';
            document.getElementById('linkUrl').value = link.url;
            document.getElementById('linkBadge').value = link.badge || '';
            document.getElementById('linkIcon').value = link.icon;
            document.getElementById('linkLayout').value = link.layout_type || 'standard';
            document.getElementById('linkOrder').value = link.order_index;
            document.getElementById('btnSubmit').textContent = 'Salvar Alteração';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>
</html>
