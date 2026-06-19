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
                        
                        echo "<tr style='border-bottom: 1px solid rgba(255,255,255,0.05);'>";
                        echo "<td style='padding: 15px;'><strong>" . htmlspecialchars($name) . "</strong><br><span style='font-size: 0.85rem; color: var(--text-dim);'>+$phone</span></td>";
                        echo "<td style='padding: 15px; text-align: center; font-size: 1.2rem; font-weight: bold; color: " . ($s['current_streak'] > 0 ? 'var(--success)' : 'var(--text-dim)') . ";'>" . $s['current_streak'] . " $isHot</td>";
                        echo "<td style='padding: 15px; text-align: center; color: var(--accent-blue); font-weight: bold;'>" . $s['longest_streak'] . "</td>";
                        echo "<td style='padding: 15px; text-align: center;'>" . $lastDate . "</td>";
                        echo "<td style='padding: 15px; text-align: center;'>" . $s['total_completions'] . " dias</td>";
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
