<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Auto-Migration: Garante que a coluna existe
try { $conn->exec("ALTER TABLE languages ADD COLUMN odysee_auto_enabled TINYINT(1) DEFAULT 0"); } catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    try {
        $conn->beginTransaction();
        foreach ($_POST['langs'] as $id => $data) {
            $auto_enabled = isset($data['auto']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE languages SET odysee_auth_token = ?, odysee_channel_name = ?, odysee_auto_enabled = ? WHERE id = ?");
            $stmt->execute([
                trim($data['token']),
                trim($data['channel']),
                $auto_enabled,
                $id
            ]);
        }
        $conn->commit();
        $msg = "Configurações do Odysee salvas com sucesso!";
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

$languages = $conn->query("SELECT id, name, odysee_auth_token, odysee_channel_name, odysee_auto_enabled FROM languages ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações Odysee</title>
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
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        
        .bulk-card { background: var(--card-bg); border-radius: 24px; padding: 30px; border: 1px solid rgba(255,255,255,0.05); }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--text-dim); font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.05); }
        td { padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); }
        
        input { background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px 15px; color: white; width: 100%; transition: 0.3s; font-size: 0.95rem; }
        input:focus { border-color: var(--accent-red); outline: none; }
        
        .btn-save { background: var(--accent-red); color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1rem; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }

        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 20px; }

        /* Estilo do Toggle */
        .switch { display: inline-flex; align-items: center; cursor: pointer; user-select: none; }
        .switch input { display: none; }
        .slider { 
            width: 44px; height: 24px; background: #334155; border-radius: 24px; position: relative; transition: .3s; flex-shrink: 0;
        }
        .slider:before { 
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; transition: .3s; border-radius: 50%; 
        }
        input:checked + .slider { background: var(--success); }
        input:checked + .slider:before { transform: translateX(20px); }
        
        .status-badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
        }
        .status-ok { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-warn { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <form method="POST">
            <header class="header">
                <div>
                    <h2 style="font-size: 2rem; font-weight: 800;">Configurações Odysee</h2>
                    <p style="color: var(--text-dim);">Gerencie os tokens, canais e a automação de postagem.</p>
                </div>
                <div>
                    <button type="submit" name="bulk_save" class="btn-save"><i class="fas fa-save"></i> Salvar Alterações</button>
                </div>
            </header>

            <?php if (isset($msg)): ?> <div class="alert"><i class="fas fa-check-circle"></i> <?= $msg ?></div> <?php endif; ?>
            <?php if (isset($error)): ?> <div class="alert" style="background: rgba(227, 29, 28, 0.1); border: 1px solid rgba(227, 29, 28, 0.2); color: var(--accent-red);"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div> <?php endif; ?>

            <div class="bulk-card">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%;">Idioma</th>
                            <th style="width: 35%;">Token Odysee (auth_token)</th>
                            <th style="width: 25%;">Nome do Canal (ex: @Canal)</th>
                            <th style="width: 10%;">Status</th>
                            <th style="width: 15%; text-align: center;">Robô Ativo?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($languages as $l): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($l['name']) ?></td>
                            <td><input type="password" name="langs[<?= $l['id'] ?>][token]" value="<?= htmlspecialchars($l['odysee_auth_token'] ?? '') ?>" placeholder="Colar token longo aqui..."></td>
                            <td><input type="text" name="langs[<?= $l['id'] ?>][channel]" value="<?= htmlspecialchars($l['odysee_channel_name'] ?? '') ?>" placeholder="@Exemplo"></td>
                            <td>
                                <?php if (empty($l['odysee_auth_token'])): ?>
                                    <span class="status-badge status-warn">Sem Token</span>
                                <?php else: ?>
                                    <span class="status-badge status-ok">Configurado</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <label class="switch">
                                    <input type="checkbox" name="langs[<?= $l['id'] ?>][auto]" <?= $l['odysee_auto_enabled'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </main>
</body>
</html>
