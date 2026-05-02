<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Busca todos os hosts
$stmt = $conn->query("SELECT * FROM hosts ORDER BY full_name ASC");
$hosts = $stmt->fetchAll();

// Lógica de alternar status (via GET para ser simples agora)
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $newStatus = $_GET['toggle_status'] === 'ativo' ? 'inativo' : 'ativo';
    
    $stmt = $conn->prepare("UPDATE hosts SET status = :status WHERE id = :id");
    $stmt->execute(['status' => $newStatus, 'id' => $id]);
    
    header('Location: hosts.php?msg=Status atualizado com sucesso');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Hosts | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --white: #ffffff;
            --card-bg: #1e293b;
            --success: #10b981;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }

        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

        /* Sidebar (Igual ao Index) */
        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.05); }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 50px; padding: 0 10px; }
        .brand-logo { width: 35px; height: 35px; background: var(--accent-red); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .brand-name { font-size: 1.2rem; font-weight: 700; letter-spacing: -0.5px; }
        .nav-menu { flex: 1; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim); text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s ease; font-weight: 500; }
        .nav-item:hover, .nav-item.active { background: rgba(227, 29, 28, 0.1); color: var(--white); }
        .nav-item.active { background: var(--accent-red); color: white; }
        .nav-logout { margin-top: auto; color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.2); }

        /* Main Content */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header-title h2 { font-size: 1.8rem; font-weight: 700; }

        .btn-add { background: var(--accent-red); color: white; text-decoration: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }

        /* Table Style */
        .table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; background: rgba(0,0,0,0.1); color: var(--text-dim); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        .host-info { display: flex; align-items: center; gap: 15px; }
        .host-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.1); }
        .host-name { font-weight: 600; color: var(--white); }
        .host-langs { font-size: 0.85rem; color: var(--text-dim); }

        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-active { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-inactive { background: rgba(245, 158, 11, 0.1); color: var(--warning); }

        .actions { display: flex; gap: 10px; }
        .action-btn { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1); }
        .btn-edit { color: var(--accent-blue); }
        .btn-edit:hover { background: var(--accent-blue); color: white; }
        .btn-toggle { color: var(--text-dim); }
        .btn-toggle:hover { background: var(--text-main); color: var(--primary-bg); }

        .alert { padding: 15px 25px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 25px; }

        /* Controls Section */
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; flex-wrap: wrap; }
        .filter-group { display: flex; gap: 5px; background: var(--sidebar-bg); padding: 5px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
        .filter-btn { padding: 8px 20px; border-radius: 8px; border: none; background: transparent; color: var(--text-dim); cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; }
        .filter-btn:hover { color: var(--white); }
        .filter-btn.active { background: var(--accent-red); color: white; box-shadow: 0 4px 10px rgba(227, 29, 28, 0.2); }
        
        .search-group { position: relative; flex: 1; max-width: 400px; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-dim); }
        .search-group input { width: 100%; background: var(--card-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 15px 12px 45px; color: var(--text-main); outline: none; transition: all 0.3s ease; }
        .search-group input:focus { border-color: var(--accent-red); box-shadow: 0 0 0 4px rgba(227, 29, 28, 0.1); }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">EI</div>
            <span class="brand-name">ADMIN CENTRAL</span>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            <a href="hosts.php" class="nav-item active">
                <i class="fas fa-users"></i> Anfitriões
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-calendar-alt"></i> Encontros
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-cog"></i> Configurações
            </a>
            <a href="logout.php" class="nav-item nav-logout">
                <i class="fas fa-sign-out-alt"></i> Sair do Painel
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <div class="header-title">
                <h2>Gerenciar Anfitriões</h2>
                <p>Lista completa de todos os membros cadastrados.</p>
            </div>
            <a href="host_form.php" class="btn-add">
                <i class="fas fa-plus"></i> Novo Anfitrião
            </a>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="controls">
            <div class="filter-group">
                <button class="filter-btn active" data-status="ativo">Ativos</button>
                <button class="filter-btn" data-status="inativo">Inativos</button>
                <button class="filter-btn" data-status="all">Todos</button>
            </div>
            <div class="search-group">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="hostSearch" placeholder="Pesquisar por nome ou idioma...">
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Anfitrião</th>
                        <th>Região</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hosts as $host): 
                        $photo = !empty($host['profile_picture']) ? '../assets/images/' . $host['profile_picture'] : '../assets/images/HostSemFoto.png';
                    ?>
                    <tr>
                        <td>
                            <div class="host-info">
                                <img src="<?= $photo ?>" class="host-img" alt="Foto">
                                <div>
                                    <div class="host-name"><?= htmlspecialchars($host['full_name']) ?></div>
                                    <div class="host-langs"><?= htmlspecialchars($host['languages'] ?? 'Nenhum idioma') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($host['region'] ?? 'Não informado') ?></td>
                        <td>
                            <?php if ($host['status'] === 'ativo'): ?>
                                <span class="badge badge-active">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="host_form.php?id=<?= $host['id'] ?>" class="action-btn btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="hosts.php?toggle_status=<?= $host['status'] ?>&id=<?= $host['id'] ?>" class="action-btn btn-toggle" title="Alternar Status">
                                    <i class="fas fa-power-off"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>
        const searchInput = document.getElementById('hostSearch');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const tableRows = document.querySelectorAll('tbody tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const activeFilter = document.querySelector('.filter-btn.active').dataset.status;

            tableRows.forEach(row => {
                const name = row.querySelector('.host-name').textContent.toLowerCase();
                const langs = row.querySelector('.host-langs').textContent.toLowerCase();
                const statusBadge = row.querySelector('.badge');
                const status = statusBadge.textContent.trim().toLowerCase(); // 'ativo' ou 'inativo'
                
                const matchesSearch = name.includes(searchTerm) || langs.includes(searchTerm);
                const matchesStatus = activeFilter === 'all' || status === activeFilter;

                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterTable);

        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                filterButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                filterTable();
            });
        });

        // Inicializa o filtro (Ativos por padrão)
        filterTable();
    </script>
</body>
</html>
