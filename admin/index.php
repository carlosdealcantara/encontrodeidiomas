<?php
session_start();
require_once '../config.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle status changes
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $field = isset($_GET['field']) ? $_GET['field'] : 'status';
    
    if (in_array($field, ['status', 'technical_status', 'active'])) {
        $conn = connectDB();
        
        // Get current status
        $stmt = $conn->prepare("SELECT $field FROM hosts WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        
        // Toggle status based on field type
        if ($field === 'active') {
            $new_value = $current ? 0 : 1;
        } else {
            $new_value = ($current === 'ativo') ? 'inativo' : 'ativo';
        }
        
        // Update status
        $stmt = $conn->prepare("UPDATE hosts SET $field = ? WHERE id = ?");
        $stmt->execute([$new_value, $id]);
        
        // Redirect to refresh
        header('Location: index.php?status_changed=1');
        exit;
    }
}

// Get all hosts for management
$conn = connectDB();
$stmt = $conn->prepare("SELECT * FROM hosts ORDER BY full_name");
$stmt->execute();
$hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count active/inactive hosts
$activeHosts = 0;
$inactiveHosts = 0;
$technicalHosts = 0;

foreach ($hosts as $host) {
    if ($host['status'] === 'ativo') {
        $activeHosts++;
    } else {
        $inactiveHosts++;
    }
    
    if ($host['technical_status'] === 'ativo') {
        $technicalHosts++;
    }
}

// Page title
$title = "Admin Dashboard - Encontro de Idiomas";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --accent-red: #e31d1c;
            --accent-blue: #002654;
            --accent-green: #28a745;
            --accent-purple: #6f42c1;
            --text-color: #333;
            --bg-light: #f8f9fa;
            --bg-lighter: #f1f3f5;
            --white: #ffffff;
            --gray: #6c757d;
            --border-radius: 16px;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            color: var(--text-color);
            background-color: #f7f7f7;
            line-height: 1.6;
        }
        
        .admin-header {
            background: var(--primary-color);
            color: var(--white);
            padding: 15px 0;
            box-shadow: var(--shadow);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .header-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .admin-actions {
            display: flex;
            gap: 15px;
        }
        
        .admin-btn {
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--white);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .admin-btn.add {
            background-color: var(--accent-green);
        }
        
        .admin-btn.logout {
            background-color: var(--accent-red);
        }
        
        .admin-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .stat-card.total .stat-number {
            color: var(--accent-blue);
        }
        
        .stat-card.active .stat-number {
            color: var(--accent-green);
        }
        
        .stat-card.inactive .stat-number {
            color: var(--accent-red);
        }
        
        .stat-card.technical .stat-number {
            color: var(--accent-purple);
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .content-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .hosts-table {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
        }
        
        .hosts-table th, 
        .hosts-table td {
            padding: 15px;
            text-align: left;
        }
        
        .hosts-table th {
            background-color: var(--bg-lighter);
            font-weight: 600;
        }
        
        .hosts-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }
        
        .hosts-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .hosts-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            text-align: center;
            min-width: 90px;
        }
        
        .status-active {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--accent-green);
        }
        
        .status-inactive {
            background-color: rgba(227, 29, 28, 0.15);
            color: var(--accent-red);
        }
        
        .actions-cell {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .action-btn.view {
            background-color: var(--accent-blue);
        }
        
        .action-btn.edit {
            background-color: var(--accent-green);
        }
        
        .action-btn.toggle {
            background-color: var(--gray);
        }
        
        .action-btn.delete {
            background-color: var(--accent-red);
        }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px 0;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .responsive-table {
            overflow-x: auto;
        }
        
        @media (max-width: 768px) {
            .header-title {
                font-size: 1rem;
            }
            
            .admin-actions {
                gap: 8px;
            }
            
            .admin-btn {
                padding: 6px 10px;
                font-size: 0.9rem;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="header-content">
                <div class="logo-container">
                    <img src="../assets/images/logo.png" alt="Encontro de Idiomas" class="logo">
                    <div class="header-title">Administração - Encontro de Idiomas</div>
                </div>
                
                <div class="admin-actions">
                    <a href="host_form.php" class="admin-btn add">
                        <i class="fas fa-plus"></i> Novo Anfitrião
                    </a>
                    <a href="?action=logout" class="admin-btn logout">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container">
        <?php if (isset($_GET['status_changed'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Status atualizado com sucesso!
            </div>
        <?php endif; ?>
        
        <div class="stats-container">
            <div class="stat-card total">
                <div class="stat-number"><?= count($hosts) ?></div>
                <div class="stat-label">Total de Anfitriões</div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-number"><?= $activeHosts ?></div>
                <div class="stat-label">Anfitriões Ativos</div>
            </div>
            
            <div class="stat-card inactive">
                <div class="stat-number"><?= $inactiveHosts ?></div>
                <div class="stat-label">Anfitriões Inativos</div>
            </div>
            
            <div class="stat-card technical">
                <div class="stat-number"><?= $technicalHosts ?></div>
                <div class="stat-label">Equipe Técnica</div>
            </div>
        </div>
        
        <div class="content-header">
            <h1 class="content-title">Gerenciar Anfitriões</h1>
        </div>
        
        <div class="responsive-table">
            <table class="hosts-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Status</th>
                        <th>Status Técnico</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hosts as $host): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($host['full_name']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($host['category'] ?? 'N/A') ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $host['status'] === 'ativo' ? 'active' : 'inactive' ?>">
                                <?= $host['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $host['technical_status'] === 'ativo' ? 'active' : 'inactive' ?>">
                                <?= $host['technical_status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <a href="host_view.php?id=<?= $host['id'] ?>" class="action-btn view" title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="host_form.php?id=<?= $host['id'] ?>" class="action-btn edit" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?action=toggle_status&id=<?= $host['id'] ?>&field=status" class="action-btn toggle" title="Alternar Status">
                                <i class="fas fa-exchange-alt"></i>
                            </a>
                            <a href="?action=toggle_status&id=<?= $host['id'] ?>&field=technical_status" class="action-btn toggle" title="Alternar Status Técnico">
                                <i class="fas fa-code"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($hosts)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 30px;">
                            Nenhum anfitrião encontrado. <a href="host_form.php">Adicionar novo anfitrião</a>.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            &copy; <?= date('Y') ?> Encontro de Idiomas - Painel Administrativo
        </div>
    </footer>
</body>
</html> 