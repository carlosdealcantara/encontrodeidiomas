<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

if (isset($_GET['toggle_pagamento']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $newStatus = $_GET['toggle_pagamento'] === 'Pago' ? 'Pendente' : 'Pago';
    
    $stmt = $conn->prepare("UPDATE mentoria_alunos SET status_pagamento = :status WHERE id = :id");
    $stmt->execute(['status' => $newStatus, 'id' => $id]);
    
    header('Location: mentoria.php?msg=Status de pagamento atualizado com sucesso');
    exit;
}

// Busca todos os alunos, ordenando os Ativos primeiro, e depois por data de vencimento
$stmt = $conn->query("
    SELECT * FROM mentoria_alunos 
    ORDER BY 
        CASE WHEN status_aluno = 'Ativo' THEN 1 ELSE 2 END ASC, 
        proximo_vencimento ASC
");
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
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header-title h2 { font-size: 1.8rem; font-weight: 700; }

        .header-actions { display: flex; gap: 15px; }
        .btn-action { color: white; text-decoration: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .btn-add { background: var(--success); }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3); }
        .btn-settings { background: var(--sidebar-bg); border: 1px solid rgba(255,255,255,0.1); }
        .btn-settings:hover { background: rgba(255,255,255,0.1); }

        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; flex-wrap: wrap; }
        .filter-group { display: flex; gap: 5px; background: var(--sidebar-bg); padding: 5px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
        .filter-btn { padding: 8px 20px; border-radius: 8px; border: none; background: transparent; color: var(--text-dim); cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; }
        .filter-btn:hover { color: var(--white); }
        .filter-btn.active { background: var(--accent-red); color: white; box-shadow: 0 4px 10px rgba(227, 29, 28, 0.2); }
        
        .search-group { position: relative; flex: 1; max-width: 400px; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-dim); }
        .search-group input { width: 100%; background: var(--card-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 15px 12px 45px; color: var(--text-main); outline: none; transition: all 0.3s ease; }
        .search-group input:focus { border-color: var(--accent-red); box-shadow: 0 0 0 4px rgba(227, 29, 28, 0.1); }


        .table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; background: rgba(0,0,0,0.1); color: var(--text-dim); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        .aluno-name { font-weight: 600; color: var(--white); font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }
        .aluno-phone { font-size: 0.85rem; color: var(--text-dim); margin-top: 4px; }

        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        
        .badge-pago { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-pendente { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-suspenso { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge-isento { background: rgba(148, 163, 184, 0.1); color: var(--text-dim); }

        .badge-ativo { background: rgba(56, 189, 248, 0.1); color: var(--accent-blue); }
        .badge-inativo { background: rgba(148, 163, 184, 0.1); color: var(--text-dim); }
        .badge-vitalicio { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

        .actions { display: flex; gap: 10px; }
        .action-btn { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1); cursor: pointer; background: transparent; }
        .btn-edit { color: var(--accent-blue); }
        .btn-edit:hover { background: var(--accent-blue); color: white; }
        .btn-toggle { color: var(--text-dim); }
        .btn-toggle:hover { background: var(--text-main); color: var(--primary-bg); }
        .btn-renew { color: var(--success); }
        .btn-renew:hover { background: var(--success); color: white; }

        .alert { padding: 15px 25px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 25px; }
        
        .vencimento-hoje { color: var(--warning); font-weight: bold; }
        .vencimento-atrasado { color: var(--danger); font-weight: bold; }
        .vencimento-normal { color: var(--text-main); }
        .vencimento-inativo { color: var(--text-dim); font-style: italic; }
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

        <div class="controls">
            <div class="filter-group">
                <button class="filter-btn active" data-status="Ativo">Ativos</button>
                <button class="filter-btn" data-status="Inativo">Inativos</button>
                <button class="filter-btn" data-status="Vitalício">Vitalícios</button>
                <button class="filter-btn" data-status="all">Todos</button>
            </div>
            <div class="search-group">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="alunoSearch" placeholder="Pesquisar por nome...">
            </div>
        </div>

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
                        $hoje->setTime(0,0,0);
                        
                        // Fallback para datas bizarras do legado (como 0001)
                        if(strpos($aluno['proximo_vencimento'], '-0001') !== false || substr($aluno['proximo_vencimento'], 0, 4) == '1900') {
                            $vencimentoText = "1900";
                            $dias = 0;
                        } else {
                            $vencimento = new DateTime($aluno['proximo_vencimento']);
                            $vencimento->setTime(0,0,0);
                            $diff = $hoje->diff($vencimento);
                            $dias = (int)$diff->format('%R%a'); // Negativo se já passou
                            $vencimentoText = $vencimento->format('d/m/Y');
                        }
                        
                        $vencimentoClass = 'vencimento-normal';
                        
                        // Só calcula atraso se for um aluno Ativo
                        if ($aluno['status_aluno'] === 'Ativo' && $aluno['status_pagamento'] !== 'Isento') {
                            if ($dias === 0) { 
                                $vencimentoClass = 'vencimento-hoje'; 
                                $vencimentoText .= ' (Hoje)'; 
                            } elseif ($dias < 0) { 
                                $vencimentoClass = 'vencimento-atrasado'; 
                                $vencimentoText .= " (Atrasado " . abs($dias) . " dias)"; 
                            }
                        } else {
                            $vencimentoClass = 'vencimento-inativo';
                        }
                    ?>
                    <tr class="aluno-row" data-status-aluno="<?= htmlspecialchars($aluno['status_aluno']) ?>">
                        <td>
                            <div class="aluno-name">
                                <?= htmlspecialchars($aluno['nome']) ?> 
                                <?php 
                                    $badgeStClass = 'badge-inativo';
                                    if($aluno['status_aluno'] === 'Ativo') $badgeStClass = 'badge-ativo';
                                    if($aluno['status_aluno'] === 'Vitalício') $badgeStClass = 'badge-vitalicio';
                                ?>
                                <span class="badge <?= $badgeStClass ?>"><?= htmlspecialchars($aluno['status_aluno']) ?></span>
                            </div>
                            <div class="aluno-phone"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($aluno['telefone']) ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600;">R$ <?= number_format($aluno['valor_mensalidade'], 2, ',', '.') ?></div>
                            <div style="font-size:0.8rem; color:var(--text-dim);">LTV: R$ <?= number_format($aluno['total_investido'] ?? 0, 2, ',', '.') ?></div>
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
                                <!-- Botão de Renovação (Registrar Pagamento) -->
                                <form action="mentoria_renovar.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $aluno['id'] ?>">
                                    <button type="submit" class="action-btn btn-renew" title="Registrar Pagamento: Renova +1 Mês e Adiciona R$ <?= number_format($aluno['valor_mensalidade'], 2, ',', '.') ?> no LTV" onclick="return confirm('Registrar pagamento de <?= htmlspecialchars($aluno['nome']) ?>? Isso avançará o vencimento para o próximo mês.');">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                </form>

                                <a href="mentoria_form.php?id=<?= $aluno['id'] ?>" class="action-btn btn-edit" title="Editar Dados / LTV / Data de Início"><i class="fas fa-edit"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('alunoSearch');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const tableRows = document.querySelectorAll('.aluno-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const activeFilter = document.querySelector('.filter-btn.active').dataset.status;

            tableRows.forEach(row => {
                const name = row.querySelector('.aluno-name').textContent.toLowerCase();
                const statusAluno = row.dataset.statusAluno;
                
                const matchesSearch = name.includes(searchTerm);
                const matchesStatus = activeFilter === 'all' || statusAluno === activeFilter;

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
