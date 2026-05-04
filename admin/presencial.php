<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Toggle ativo
if (isset($_GET['toggle_active']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE in_person_events SET active = NOT active WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header('Location: presencial.php?msg=Status atualizado');
    exit;
}

// Deletar
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM in_person_events WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header('Location: presencial.php?msg=Evento excluído');
    exit;
}

// Busca eventos
$events = $conn->query("
    SELECT e.*, h.full_name as host_name
    FROM in_person_events e
    LEFT JOIN hosts h ON e.host_id = h.id
    ORDER BY e.city ASC, e.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Presencial | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a; --sidebar-bg: #1e293b; --accent-red: #e31d1c;
            --accent-blue: #38bdf8; --text-main: #f1f5f9; --text-dim: #94a3b8;
            --white: #ffffff; --card-bg: #1e293b; --success: #10b981;
            --warning: #f59e0b; --danger: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.05); flex-shrink: 0; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 50px; padding: 0 10px; }
        .brand-logo { width: 35px; height: 35px; background: var(--accent-red); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .brand-name { font-size: 1.2rem; font-weight: 700; letter-spacing: -0.5px; }
        .nav-menu { flex: 1; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim); text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s ease; font-weight: 500; }
        .nav-item:hover { background: rgba(227, 29, 28, 0.1); color: var(--white); }
        .nav-item.active { background: var(--accent-red); color: white; }
        .nav-logout { margin-top: auto; color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.2); }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header-title h2 { font-size: 1.8rem; font-weight: 700; }
        .header-title p { color: var(--text-dim); margin-top: 4px; }
        .btn-add { background: var(--accent-red); color: white; text-decoration: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }
        .alert { padding: 15px 25px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 25px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card-bg); padding: 20px 25px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .icon-blue { background: rgba(56, 189, 248, 0.1); color: var(--accent-blue); }
        .icon-red { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); }
        .icon-green { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-info h3 { font-size: 1.6rem; font-weight: 700; }
        .stat-info p { color: var(--text-dim); font-size: 0.85rem; margin-top: 2px; }
        .table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; background: rgba(0,0,0,0.1); color: var(--text-dim); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 18px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .city-tag { background: rgba(56, 189, 248, 0.1); color: var(--accent-blue); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-active { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-inactive { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .event-title { font-weight: 600; color: var(--white); margin-bottom: 4px; }
        .event-desc { font-size: 0.83rem; color: var(--text-dim); }
        .actions { display: flex; gap: 8px; }
        .action-btn { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1); }
        .btn-edit { color: var(--accent-blue); }
        .btn-edit:hover { background: var(--accent-blue); color: white; }
        .btn-toggle { color: var(--text-dim); }
        .btn-toggle:hover { background: var(--text-main); color: var(--primary-bg); }
        .btn-delete { color: var(--danger); }
        .btn-delete:hover { background: var(--danger); color: white; }
        .empty-state { text-align: center; padding: 60px; color: var(--text-dim); }
        .empty-state i { font-size: 3rem; margin-bottom: 20px; opacity: 0.2; display: block; }
        .controls { display: flex; gap: 15px; align-items: center; margin-bottom: 25px; flex-wrap: wrap; }
        .search-group { position: relative; flex: 1; max-width: 400px; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-dim); }
        .search-group input { width: 100%; background: var(--card-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 15px 12px 45px; color: var(--text-main); outline: none; transition: all 0.3s ease; }
        .search-group input:focus { border-color: var(--accent-red); }
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='25' fill='%23e31d1c'/%3E%3Ctext x='50' y='53' font-family='sans-serif' font-weight='bold' font-size='55' fill='white' text-anchor='middle' dominant-baseline='central'%3EEi%3C/text%3E%3C/svg%3E">
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">Ei</div>
            <span class="brand-name">ADMIN CENTRAL</span>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="hosts.php" class="nav-item"><i class="fas fa-users"></i> Equipe</a>
            <a href="meetings.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Online</a>
            <a href="presencial.php" class="nav-item active"><i class="fas fa-map-marker-alt"></i> Presencial</a>
            <a href="languages.php" class="nav-item"><i class="fas fa-language"></i> Idiomas</a>
            <a href="useful_links.php" class="nav-item"><i class="fas fa-link"></i> Links</a>
            <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> Configurações</a>
            <a href="logout.php" class="nav-item nav-logout"><i class="fas fa-sign-out-alt"></i> Sair do Painel</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <div class="header-title">
                <h2>Encontros Presenciais</h2>
                <p>Gerencie eventos que acontecem pessoalmente nas cidades.</p>
            </div>
            <a href="presencial_form.php" class="btn-add">
                <i class="fas fa-plus"></i> Novo Evento
            </a>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert"><i class="fas fa-check-circle" style="margin-right:8px;"></i><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <?php
        $total   = count($events);
        $ativos  = count(array_filter($events, fn($e) => $e['active']));
        $cidades = count(array_unique(array_column($events, 'city')));
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info"><h3><?= $total ?></h3><p>Total de Eventos</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info"><h3><?= $ativos ?></h3><p>Eventos Ativos</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-red"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-info"><h3><?= $cidades ?></h3><p>Cidades</p></div>
            </div>
        </div>

        <div class="controls">
            <div class="search-group">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar por cidade ou título...">
            </div>
        </div>

        <div class="table-container">
            <?php if (empty($events)): ?>
                <div class="empty-state">
                    <i class="fas fa-map-marked-alt"></i>
                    <p>Nenhum evento presencial cadastrado ainda.</p>
                    <a href="presencial_form.php" style="color: var(--accent-red); margin-top: 10px; display:inline-block;">Criar primeiro evento</a>
                </div>
            <?php else: ?>
            <table id="eventsTable">
                <thead>
                    <tr>
                        <th>Cidade / Estado</th>
                        <th>Evento</th>
                        <th>Host Responsável</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                    <tr>
                        <td>
                            <span class="city-tag">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($ev['city']) ?>
                                <?= !empty($ev['state']) ? '- ' . htmlspecialchars($ev['state']) : '' ?>
                            </span>
                        </td>
                        <td>
                            <div class="event-title"><?= htmlspecialchars($ev['title']) ?></div>
                            <?php if (!empty($ev['description'])): ?>
                                <div class="event-desc"><?= htmlspecialchars(mb_strimwidth($ev['description'], 0, 80, '...')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color: var(--text-dim); font-size: 0.9rem;">
                                <i class="fas fa-user-circle" style="margin-right:5px;"></i>
                                <?= htmlspecialchars($ev['host_name'] ?? 'Não definido') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($ev['active']): ?>
                                <span class="badge badge-active">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="presencial_form.php?id=<?= $ev['id'] ?>" class="action-btn btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="presencial.php?toggle_active=1&id=<?= $ev['id'] ?>" class="action-btn btn-toggle" title="Alternar Status"><i class="fas fa-power-off"></i></a>
                                <a href="presencial.php?delete=1&id=<?= $ev['id'] ?>" class="action-btn btn-delete" title="Excluir" onclick="return confirm('Excluir este evento?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('searchInput');
        const rows = document.querySelectorAll('#eventsTable tbody tr');
        searchInput?.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
