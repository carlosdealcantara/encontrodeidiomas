<?php
/**
 * Odysee Queue Admin Panel
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Handle Retries
if (isset($_GET['retry']) && is_numeric($_GET['retry'])) {
    $id = (int)$_GET['retry'];
    $stmt = $conn->prepare("UPDATE odysee_publish_queue SET status = 'pending', retry_count = 0 WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: odysee_queue.php?msg=Retrying');
    exit;
}

// Handle Cancel
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    $stmt = $conn->prepare("UPDATE odysee_publish_queue SET status = 'error', error_message = 'Cancelled by Admin' WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: odysee_queue.php?msg=Cancelled');
    exit;
}

// Fetch Queue
$stmt = $conn->query("
    SELECT q.*, l.name as language_name 
    FROM odysee_publish_queue q
    LEFT JOIN languages l ON q.language_id = l.id
    ORDER BY q.id DESC LIMIT 100
");
$queue = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odysee Queue - Admin Central</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 1.8rem; font-weight: 700; color: var(--white); }
        .data-table { width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .data-table th, .data-table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .data-table th { background: rgba(0,0,0,0.2); font-weight: 600; color: var(--text-dim); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }
        .data-table td { color: var(--text-main); font-size: 0.95rem; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .badge-pending { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .badge-processing { background: rgba(56, 189, 248, 0.2); color: #38bdf8; }
        .badge-done { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-error { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-waiting_host { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
        
        .btn-sm { padding: 6px 12px; background: rgba(255,255,255,0.1); border-radius: 6px; color: white; text-decoration: none; font-size: 0.85rem; transition: 0.2s; border: none; cursor: pointer; }
        .btn-sm:hover { background: var(--accent-blue); }
        .btn-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .btn-danger:hover { background: #ef4444; color: white; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Fila de Uploads (Odysee)</h1>
            <button class="btn-sm" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Atualizar</button>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div style="background: var(--success); color: white; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> Ação realizada: <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Idioma</th>
                    <th>Título Final</th>
                    <th>Status</th>
                    <th>Tentativas</th>
                    <th>Erro/Detalhe</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queue as $row): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['language_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($row['titulo_final'] ?? 'Aguardando Host...') ?>
                        <?php if ($row['odysee_url']): ?>
                            <br><a href="<?= $row['odysee_url'] ?>" target="_blank" style="color:var(--accent-blue); font-size:0.85rem;"><?= $row['odysee_url'] ?></a>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                    <td><?= $row['retry_count'] ?>/3</td>
                    <td style="font-size: 0.85rem; color: var(--text-dim); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['error_message']) ?>">
                        <?= htmlspecialchars($row['error_message']) ?>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'error' || $row['status'] === 'processing'): ?>
                            <a href="?retry=<?= $row['id'] ?>" class="btn-sm"><i class="fas fa-redo"></i> Tentar Novamente</a>
                        <?php endif; ?>
                        <?php if ($row['status'] === 'pending' || $row['status'] === 'processing'): ?>
                            <a href="?cancel=<?= $row['id'] ?>" class="btn-sm btn-danger"><i class="fas fa-times"></i> Cancelar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($queue)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;">A fila está vazia.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
