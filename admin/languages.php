<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Auto-Migração: Garante que as colunas existam
try {
    $conn->exec("ALTER TABLE languages ADD COLUMN whatsapp_link VARCHAR(255) AFTER active");
    $conn->exec("ALTER TABLE languages ADD COLUMN instagram_link VARCHAR(255) AFTER whatsapp_link");
    
    // Migração inicial do Instagram (se estiver vazio na tabela languages)
    $conn->exec("
        UPDATE languages l
        SET l.instagram_link = (
            SELECT m.instagram_link 
            FROM meetings m 
            WHERE m.language_id = l.id 
              AND m.instagram_link IS NOT NULL 
              AND m.instagram_link != '' 
            LIMIT 1
        )
        WHERE (l.instagram_link IS NULL OR l.instagram_link = '')
    ");
} catch (PDOException $e) {}

// Lógica de Salvar em Lote (Bulk Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    try {
        $conn->beginTransaction();
        foreach ($_POST['langs'] as $id => $data) {
            $stmt = $conn->prepare("UPDATE languages SET name = ?, flag_code = ?, flag_emoji = ?, whatsapp_link = ?, instagram_link = ?, active = ? WHERE id = ?");
            $stmt->execute([
                $data['name'], 
                $data['flag_code'], 
                $data['flag_emoji'], 
                $data['whatsapp_link'], 
                $data['instagram_link'], 
                isset($data['active']) ? 1 : 0,
                $id
            ]);
        }
        $conn->commit();
        $msg = "Todos os idiomas foram atualizados com sucesso!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

// Busca idiomas
$languages = $conn->query("SELECT * FROM languages ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edição em Lote | Idiomas</title>
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

        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; border-right: 1px solid rgba(255,255,255,0.05); }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim); text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: 0.3s; }
        .nav-item.active { background: var(--accent-red); color: white; }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        
        .bulk-card { background: var(--card-bg); border-radius: 24px; padding: 30px; border: 1px solid rgba(255,255,255,0.05); }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--text-dim); font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.05); }
        td { padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); }
        
        input { background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 8px 12px; color: white; width: 100%; transition: 0.3s; }
        input:focus { border-color: var(--accent-red); outline: none; }
        
        .btn-save { background: var(--accent-red); color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1rem; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }

        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div style="font-size: 1.2rem; font-weight: 700; margin-bottom: 40px; color: white;">ADMIN</div>
        <nav>
            <a href="index.php" class="nav-item"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="hosts.php" class="nav-item"><i class="fas fa-users"></i> Equipe</a>
            <a href="meetings.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Online</a>
            <a href="languages.php" class="nav-item active"><i class="fas fa-language"></i> Idiomas</a>
            <a href="useful_links.php" class="nav-item"><i class="fas fa-link"></i> Links</a>
            <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> Configurações</a>
        </nav>
    </aside>

    <main class="main-content">
        <form method="POST">
            <header class="header">
                <div>
                    <h2 style="font-size: 2rem; font-weight: 800;">Edição em Lote</h2>
                    <p style="color: var(--text-dim);">Atualize os links de todos os idiomas em uma única tela.</p>
                </div>
                <button type="submit" name="bulk_save" class="btn-save">Salvar Tudo</button>
            </header>

            <?php if (isset($msg)): ?> <div class="alert"><i class="fas fa-check-circle"></i> <?= $msg ?></div> <?php endif; ?>

            <div class="bulk-card">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%;">Idioma</th>
                            <th style="width: 10%;">Bandeira / Emoji</th>
                            <th>Link WhatsApp</th>
                            <th>Link Instagram</th>
                            <th style="width: 50px;">Ativo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($languages as $l): ?>
                        <tr>
                            <td><input type="text" name="langs[<?= $l['id'] ?>][name]" value="<?= htmlspecialchars($l['name']) ?>"></td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <input type="text" name="langs[<?= $l['id'] ?>][flag_code]" value="<?= htmlspecialchars($l['flag_code'] ?? '') ?>" placeholder="us">
                                    <input type="text" name="langs[<?= $l['id'] ?>][flag_emoji]" value="<?= htmlspecialchars($l['flag_emoji'] ?? '') ?>" placeholder="🇺🇸">
                                </div>
                            </td>
                            <td><input type="url" name="langs[<?= $l['id'] ?>][whatsapp_link]" value="<?= htmlspecialchars($l['whatsapp_link'] ?? '') ?>" placeholder="https://chat.whatsapp.com/..."></td>
                            <td><input type="url" name="langs[<?= $l['id'] ?>][instagram_link]" value="<?= htmlspecialchars($l['instagram_link'] ?? '') ?>" placeholder="https://instagram.com/..."></td>
                            <td style="text-align:center;">
                                <input type="checkbox" name="langs[<?= $l['id'] ?>][active]" <?= $l['active'] ? 'checked' : '' ?> style="width:20px; height:20px; cursor:pointer;">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" name="bulk_save" class="btn-save">Salvar Alterações</button>
            </div>
        </form>
    </main>
</body>
</html>
