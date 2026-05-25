<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Lógica de alternar status_pagamento (Ex: Pago para Pendente)
if (isset($_GET['toggle_pagamento']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $newStatus = $_GET['toggle_pagamento'] === 'Pago' ? 'Pendente' : 'Pago';
    
    $stmt = $conn->prepare("UPDATE mentoria_alunos SET status_pagamento = :status WHERE id = :id");
    $stmt->execute(['status' => $newStatus, 'id' => $id]);
    
    header('Location: mentoria.php?msg=Status de pagamento atualizado com sucesso');
    exit;
}

// Busca todos os alunos
$stmt = $conn->query("SELECT * FROM mentoria_alunos ORDER BY proximo_vencimento ASC");
$alunos = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Mentoria | Admin</title>
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
            --danger: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header-title h2 { font-size: 1.8rem; font-weight: 700; }

        .header-actions { display: flex; gap: 15px; }
        .btn-action { color: white; text-decoration: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .btn-add { background: var(--success); }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3); }
        .btn-settings { background: var(--sidebar-bg); border: 1px solid rgba(255,255,255,0.1); }
        .btn-settings:hover { background: rgba(255,255,255,0.1); }

        .table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; background: rgba(0,0,0,0.1); color: var(--text-dim); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        .aluno-name { font-weight: 600; color: var(--white); font-size: 1.1rem; }
        .aluno-phone { font-size: 0.85rem; color: var(--text-dim); }

        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        
        .badge-pago { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-pendente { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-suspenso { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge-isento { background: rgba(148, 163, 184, 0.1); color: var(--text-dim); }

        .badge-ativo { background: rgba(56, 189, 248, 0.1); color: var(--accent-blue); }

        .actions { display: flex; gap: 10px; }
        .action-btn { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1); }
        .btn-edit { color: var(--accent-blue); }
        .btn-edit:hover { background: var(--accent-blue); color: white; }
        .btn-toggle { color: var(--text-dim); }
        .btn-toggle:hover { background: var(--text-main); color: var(--primary-bg); }
        .btn-renew { color: var(--success); }
        .btn-renew:hover { background: var(--success); color: white; }

        .alert { padding: 15px 25px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 25px; }
        
        .vencimento-hoje { color: var(--warning); font-weight: bold; }
        .vencimento-atrasado { color: var(--danger); font-weight: bold; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div class="header-title">
                <h2>Alunos da Mentoria</h2>
                <p>Gestão financeira e de acessos automáticos.</p>
            </div>
            <div class="header-actions">
                <a href="mentoria_settings.php" class="btn-action btn-settings">
                    <i class="fas fa-cog"></i> Configurar Mensagens
                </a>
                <a href="mentoria_form.php" class="btn-action btn-add">
                    <i class="fas fa-plus"></i> Novo Aluno
                </a>
            </div>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Plano</th>
                        <th>Vencimento</th>
                        <th>Status Pgto</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($alunos) === 0): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-dim);">Nenhum aluno cadastrado.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($alunos as $aluno): 
                        $hoje = new DateTime();
                        $vencimento = new DateTime($aluno['proximo_vencimento']);
                        $diff = $hoje->diff($vencimento);
                        $dias = (int)$diff->format('%R%a'); // Negativo se já passou
                        
                        $vencimentoClass = '';
                        $vencimentoText = $vencimento->format('d/m/Y');
                        if ($dias === 0) { $vencimentoClass = 'vencimento-hoje'; $vencimentoText .= ' (Hoje)'; }
                        elseif ($dias < 0) { $vencimentoClass = 'vencimento-atrasado'; $vencimentoText .= " (Atrasado $dias dias)"; }
                    ?>
                    <tr>
                        <td>
                            <div class="aluno-name"><?= htmlspecialchars($aluno['nome']) ?> <span class="badge badge-ativo" style="font-size:0.6rem; padding: 2px 6px; margin-left:5px; vertical-align: middle;"><?= htmlspecialchars($aluno['status_aluno']) ?></span></div>
                            <div class="aluno-phone"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($aluno['telefone']) ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600;">R$ <?= number_format($aluno['valor_mensalidade'], 2, ',', '.') ?></div>
                            <div style="font-size:0.8rem; color:var(--text-dim);">Dia <?= htmlspecialchars($aluno['dia_vencimento']) ?></div>
                        </td>
                        <td>
                            <div class="<?= $vencimentoClass ?>"><?= $vencimentoText ?></div>
                        </td>
                        <td>
                            <?php 
                                $badgeClass = 'badge-isento';
                                if($aluno['status_pagamento'] === 'Pago') $badgeClass = 'badge-pago';
                                if($aluno['status_pagamento'] === 'Pendente') $badgeClass = 'badge-pendente';
                                if($aluno['status_pagamento'] === 'Suspenso') $badgeClass = 'badge-suspenso';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($aluno['status_pagamento']) ?></span>
                        </td>
                        <td>
                            <div class="actions">
                                <!-- Botão de Renovação Rápida -->
                                <form action="mentoria_renovar.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $aluno['id'] ?>">
                                    <button type="submit" class="action-btn btn-renew" title="Renovar +1 Mês e Marcar Pago" onclick="return confirm('Renovar o aluno <?= htmlspecialchars($aluno['nome']) ?> para o próximo mês?');">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>

                                <a href="mentoria_form.php?id=<?= $aluno['id'] ?>" class="action-btn btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                                
                                <a href="mentoria.php?toggle_pagamento=<?= $aluno['status_pagamento'] ?>&id=<?= $aluno['id'] ?>" class="action-btn btn-toggle" title="Alternar Pago/Pendente">
                                    <i class="fas fa-exchange-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
