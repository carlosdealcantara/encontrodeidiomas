<div class="header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div class="header-title">
        <h2>Alunos da Mentoria</h2>
        <p>Gestão financeira e de acessos automáticos.</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 15px;">
        <a href="mentoria_form.php" class="btn-action btn-add">
            <i class="fas fa-plus"></i> Novo Aluno
        </a>
    </div>
</div>

<div class="controls" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; flex-wrap: wrap;">
    <div class="filter-group" style="display: flex; gap: 5px; background: var(--sidebar-bg); padding: 5px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
        <button class="filter-btn active" data-status="Ativo" style="padding: 8px 20px; border-radius: 8px; border: none; background: transparent; color: var(--text-dim); cursor: pointer; font-weight: 600; font-size: 0.9rem;">Ativos</button>
        <button class="filter-btn" data-status="Inativo" style="padding: 8px 20px; border-radius: 8px; border: none; background: transparent; color: var(--text-dim); cursor: pointer; font-weight: 600; font-size: 0.9rem;">Inativos</button>
        <button class="filter-btn" data-status="Vitalício" style="padding: 8px 20px; border-radius: 8px; border: none; background: transparent; color: var(--text-dim); cursor: pointer; font-weight: 600; font-size: 0.9rem;">Vitalícios</button>
        <button class="filter-btn" data-status="all" style="padding: 8px 20px; border-radius: 8px; border: none; background: transparent; color: var(--text-dim); cursor: pointer; font-weight: 600; font-size: 0.9rem;">Todos</button>
    </div>
    <div class="search-group" style="position: relative; flex: 1; max-width: 400px;">
        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-dim);"></i>
        <input type="text" id="alunoSearch" placeholder="Pesquisar por nome..." style="width: 100%; background: var(--card-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 15px 12px 45px; color: var(--text-main); outline: none;">
    </div>
</div>

<style>
    .btn-action { color: white; text-decoration: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
    .btn-add { background: var(--success); }
    .btn-add:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3); }
    .filter-btn.active { background: var(--accent-red) !important; color: white !important; box-shadow: 0 4px 10px rgba(227, 29, 28, 0.2); }
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
    .btn-renew { color: var(--success); }
    .btn-renew:hover { background: var(--success); color: white; }
    .vencimento-hoje { color: var(--warning); font-weight: bold; }
    .vencimento-atrasado { color: var(--danger); font-weight: bold; }
    .vencimento-normal { color: var(--text-main); }
    .vencimento-inativo { color: var(--text-dim); font-style: italic; }
</style>

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
                
                if(strpos($aluno['proximo_vencimento'], '-0001') !== false || substr($aluno['proximo_vencimento'], 0, 4) == '1900') {
                    $vencimentoText = "1900";
                    $dias = 0;
                } else {
                    $vencimento = new DateTime($aluno['proximo_vencimento']);
                    $vencimento->setTime(0,0,0);
                    $diff = $hoje->diff($vencimento);
                    $dias = (int)$diff->format('%R%a'); 
                    $vencimentoText = $vencimento->format('d/m/Y');
                }
                
                $vencimentoClass = 'vencimento-normal';
                
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
                        <?php if ($aluno['status_aluno'] !== 'Vitalício'): ?>
                        <form action="mentoria_renovar.php" method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $aluno['id'] ?>">
                            <button type="submit" class="action-btn btn-renew" title="Registrar Pagamento" onclick="return confirm('Registrar pagamento de <?= htmlspecialchars($aluno['nome']) ?>?');">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="action-btn" style="background:transparent; color:var(--text-dim); cursor:default;" title="Aluno Vitalício não requer renovação">
                            <i class="fas fa-gem"></i>
                        </span>
                        <?php endif; ?>
                        <a href="mentoria_form.php?id=<?= $aluno['id'] ?>" class="action-btn btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    const searchInput = document.getElementById('alunoSearch');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const tableRows = document.querySelectorAll('.aluno-row');

    function filterTable() {
        if(!searchInput) return;
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

    if(searchInput) {
        searchInput.addEventListener('input', filterTable);

        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                filterButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                filterTable();
            });
        });
        filterTable();
    }
</script>

