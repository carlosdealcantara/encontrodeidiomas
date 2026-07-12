<div class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h2><i class="fas fa-fire" style="color: #ea580c;"></i> Streaks do Desafio Diário</h2>
        <p style="color: var(--text-dim);">Acompanhe a ofensiva atual e o recorde de cada membro.</p>
    </div>
</div>

<div class="form-card" style="padding: 0; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: rgba(0,0,0,0.2);">
                <th style="padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-dim);">Nome / Número</th>
                <th style="padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-dim);">Streak Atual</th>
                <th style="padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-dim);">Recorde</th>
                <th style="padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-dim);">Último Envio</th>
                <th style="padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-dim);">Total Completado</th>
                <th style="padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-dim);">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            try {
                $stmt = $conn->query("SELECT * FROM mentoria_desafio_streaks ORDER BY current_streak DESC, longest_streak DESC");
                $streaks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($streaks)) {
                    echo '<tr><td colspan="5" style="padding: 20px; text-align: center; color: var(--text-dim);">Nenhum dado de streak registrado ainda.</td></tr>';
                } else {
                    foreach ($streaks as $s) {
                        $name = $s['member_name'] ?: 'Desconhecido';
                        $phone = explode('@', $s['member_jid'])[0];
                        $lastDate = $s['last_completed_date'] ? date('d/m/Y', strtotime($s['last_completed_date'])) : '-';
                        
                        $isHot = $s['current_streak'] >= 3 ? '🔥' : '';
                        
                        echo "<tr style='border-bottom: 1px solid rgba(255,255,255,0.05);' id='row-{$s['member_jid']}'>";
                        echo "<td style='padding: 15px;'><strong>" . htmlspecialchars($name) . "</strong><br><span style='font-size: 0.85rem; color: var(--text-dim);'>+$phone</span></td>";
                        echo "<td style='padding: 15px; text-align: center; font-size: 1.2rem; font-weight: bold; color: " . ($s['current_streak'] > 0 ? 'var(--success)' : 'var(--text-dim)') . ";'>
                                <input type='number' class='streak-input' id='current-{$s['member_jid']}' value='{$s['current_streak']}' style='width: 60px; background: transparent; color: inherit; border: 1px solid rgba(255,255,255,0.2); text-align: center; border-radius: 4px;'>
                                $isHot
                              </td>";
                        echo "<td style='padding: 15px; text-align: center; color: var(--accent-blue); font-weight: bold;'>
                                <input type='number' class='streak-input' id='longest-{$s['member_jid']}' value='{$s['longest_streak']}' style='width: 60px; background: transparent; color: inherit; border: 1px solid rgba(255,255,255,0.2); text-align: center; border-radius: 4px;'>
                              </td>";
                        $rawLastDate = $s['last_completed_date'] ? date('Y-m-d', strtotime($s['last_completed_date'])) : '';

                        echo "<td style='padding: 15px; text-align: center;'>
                                <input type='date' class='streak-input' id='lastdate-{$s['member_jid']}' value='{$rawLastDate}' style='background: transparent; color: inherit; border: 1px solid rgba(255,255,255,0.2); text-align: center; border-radius: 4px; padding: 2px;'>
                              </td>";
                        echo "<td style='padding: 15px; text-align: center;'>
                                <input type='number' class='streak-input' id='total-{$s['member_jid']}' value='{$s['total_completions']}' style='width: 60px; background: transparent; color: inherit; border: 1px solid rgba(255,255,255,0.2); text-align: center; border-radius: 4px;'> dias
                              </td>";
                        echo "<td style='padding: 15px; text-align: center;'>
                                <button onclick=\"saveStreak('{$s['member_jid']}')\" class='btn-sm btn-primary' style='padding: 4px 8px; font-size: 0.8rem; border: none; border-radius: 4px; cursor: pointer; background: var(--accent-blue); color: white;'><i class='fas fa-save'></i> Salvar</button>
                              </td>";
                        echo "</tr>";
                    }
                }
            } catch (Exception $e) {
                echo '<tr><td colspan="5" style="padding: 20px; text-align: center; color: var(--danger);">A tabela de streaks ainda não foi criada. Ela será criada automaticamente no primeiro envio.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<script>
function saveStreak(jid) {
    const current = document.getElementById('current-' + jid).value;
    const longest = document.getElementById('longest-' + jid).value;
    const total = document.getElementById('total-' + jid).value;
    const lastDate = document.getElementById('lastdate-' + jid).value;
    
    const btn = document.querySelector(`#row-${jid.replace('@', '\\@')} button`);
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetch('mentoria_score_edit_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'edit_streak',
            member_jid: jid,
            current_streak: current,
            longest_streak: longest,
            total_completions: total,
            last_completed_date: lastDate
        })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> OK';
            btn.style.background = 'var(--success)';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = 'var(--accent-blue)';
                btn.disabled = false;
            }, 2000);
        } else {
            alert('Erro ao salvar: ' + data.error);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('Erro de conexão');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>
