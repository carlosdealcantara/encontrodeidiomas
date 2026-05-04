<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Processamento do Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
        $stmt->execute(['value' => $value, 'key' => $key]);
    }
    $msg = "Configurações atualizadas com sucesso!";
}

// Busca todas as configurações
$settings = $conn->query("SELECT * FROM settings ORDER BY category, label")->fetchAll();
$categories = [];
foreach ($settings as $s) {
    $categories[$s['category']][] = $s;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | Admin</title>
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
        .header { margin-bottom: 40px; }
        .header h2 { font-size: 1.8rem; font-weight: 700; }

        .form-container { max-width: 800px; }
        .settings-card { background: var(--card-bg); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 30px; }
        .category-title { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--accent-red); margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 10px; color: var(--text-main); font-weight: 600; font-size: 0.95rem; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 15px; color: var(--text-main); outline: none; transition: all 0.3s ease;
        }
        .form-group input:focus { border-color: var(--accent-red); }

        .btn-save { background: var(--accent-red); color: white; border: none; padding: 16px 40px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; width: 100%; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }

        .alert { padding: 15px 25px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10b981; border-radius: 12px; margin-bottom: 25px; }
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='25' fill='%23e31d1c'/%3E%3Ctext x='50' y='53' font-family='sans-serif' font-weight='bold' font-size='55' fill='white' text-anchor='middle' dominant-baseline='central'%3EEi%3C/text%3E%3C/svg%3E">
</head>
<body>
    <aside class="sidebar">
        <div class="brand" style="display:flex; align-items:center; gap:12px; margin-bottom:50px;">
            <div style="width:35px; height:35px; background:var(--accent-red); border-radius:8px; display:flex; align-items:center; justify-content:center; color:white; font-weight:700;">EI</div>
            <span style="font-size:1.2rem; font-weight:700;">ADMIN</span>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item">Dashboard</a>
            <a href="hosts.php" class="nav-item">Equipe</a>
            <a href="meetings.php" class="nav-item">Online</a>
            <a href="presencial.php" class="nav-item"><i class="fas fa-map-marker-alt"></i> Presencial</a>
            <a href="languages.php" class="nav-item">Idiomas</a>
            <a href="useful_links.php" class="nav-item">Links</a>
            <a href="settings.php" class="nav-item active">Configurações</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h2>Central de Controle</h2>
            <p style="color: var(--text-dim);">Ajuste detalhes globais do site e metadados.</p>
        </header>

        <?php if (isset($msg)): ?>
            <div class="alert"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <form method="POST" class="form-container">
            <?php foreach ($categories as $cat => $items): ?>
                <div class="settings-card">
                    <h3 class="category-title"><?= $cat ?></h3>
                    <?php foreach ($items as $item): ?>
                        <div class="form-group">
                            <label><?= htmlspecialchars($item['label']) ?></label>
                            <?php if ($item['type'] === 'textarea'): ?>
                                <textarea name="settings[<?= $item['setting_key'] ?>]" rows="3"><?= htmlspecialchars($item['setting_value']) ?></textarea>
                            <?php elseif ($item['type'] === 'boolean'): ?>
                                <select name="settings[<?= $item['setting_key'] ?>]">
                                    <option value="1" <?= $item['setting_value'] == '1' ? 'selected' : '' ?>>Ativado</option>
                                    <option value="0" <?= $item['setting_value'] == '0' ? 'selected' : '' ?>>Desativado</option>
                                </select>
                            <?php else: ?>
                                <input type="text" name="settings[<?= $item['setting_key'] ?>]" value="<?= htmlspecialchars($item['setting_value']) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-save">Salvar Todas as Configurações</button>
        </form>
    </main>
</body>
</html>
