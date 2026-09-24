<div class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h2><i class="fas fa-vial"></i> Testador de Automações Manuais</h2>
        <p style="color: var(--text-dim);">Execute os cron jobs manualmente para diagnosticar problemas ou adiantar mensagens.</p>
    </div>
</div>

<div class="form-card">
    <p style="color: var(--text-dim); margin-bottom: 20px;"><strong>Testar Normal</strong> simula a execução comum (com travas de horário e duplicidade). <strong>Forçar Imediato</strong> ignora todas as regras e dispara a ação na hora.</p>
    
    <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
        <!-- Aviso Matinal -->
        <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Aviso Matinal Our Classes (Meia-noite)</h4>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Abre as inscrições da próxima aula válida do dia.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn" style="background:#334155; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_class_aviso_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                <button type="button" class="btn" style="background:#ea580c; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_class_aviso_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
            </div>
        </div>

        <!-- Encerramento Quorum -->
        <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Encerramento / Quórum (1h antes da aula)</h4>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Cancela a aula se houver 0 presenças confirmadas.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn" style="background:#334155; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_class_quorum_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                <button type="button" class="btn" style="background:#ea580c; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_class_quorum_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
            </div>
        </div>

        <!-- Kickoff -->
        <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Kick-off da Aula (Na hora exata)</h4>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Dispara o link do Google Meet para a turma.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn" style="background:#334155; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_class_kickoff_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                <button type="button" class="btn" style="background:#ea580c; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_class_kickoff_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
            </div>
        </div>

        <!-- Aviso Desafio -->
        <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Aviso Final do Desafio (21:00)</h4>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Lembrete no The Lounge para enviarem as gravações.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn" style="background:#334155; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_desafio_aviso_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                <button type="button" class="btn" style="background:#ea580c; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_desafio_aviso_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
            </div>
        </div>

        <!-- Kick Desafio -->
        <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Expulsão do Desafio (Meia-noite)</h4>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Remove quem não enviou os áudios e tira as vidas.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn" style="background:#334155; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_desafio_kick_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                <button type="button" class="btn" style="background:#ea580c; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_desafio_kick_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
            </div>
        </div>

        <!-- Ranking Diário -->
        <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h4 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">Publicação do Ranking (Meia-noite)</h4>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">Calcula a pontuação de ontem e posta no Lounge.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn" style="background:#334155; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_pontuacao_cron.php', false)"><i class="fas fa-play"></i> Testar Normal</button>
                <button type="button" class="btn" style="background:#ea580c; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer;" onclick="testarCron('mentoria_pontuacao_cron.php', true)"><i class="fas fa-bolt"></i> Forçar Imediato</button>
            </div>
        </div>

    </div>
    
    <div id="test-console" style="display:none; background:#0f172a; border: 1px solid #334155; padding: 15px; margin-top: 25px; border-radius: 8px; font-family: monospace; color: #10b981; max-height: 300px; overflow-y: auto; white-space: pre-wrap;">
        Aguardando execução...
    </div>
</div>

<!-- DIAGNÓSTICO DO CRON DE QUÓRUM -->
<div class="form-card" style="margin-top: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="margin: 0; color: #f59e0b;"><i class="fas fa-stethoscope"></i> Diagnóstico: Execuções do Cron de Quórum</h3>
            <p style="margin: 5px 0 0 0; font-size: 0.88rem; color: var(--text-dim);">
                Cada run do cron gera uma linha — <strong>mesmo quando não faz nada</strong>.<br>
                Se não há entradas num horário esperado, o problema é que o cron da Hostinger simplesmente não rodou.
            </p>
        </div>
        <button onclick="location.reload()" style="background: #334155; border: none; padding: 8px 16px; border-radius: 8px; color: white; cursor: pointer; font-size: 0.85rem;">
            <i class="fas fa-sync"></i> Atualizar
        </button>
    </div>

    <?php if (empty($cronDiagLogs)): ?>
        <div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 15px; color: #fca5a5; text-align: center;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Nenhum log encontrado.</strong> A tabela ainda não existe (cron nunca rodou com a nova versão) ou está vazia.
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
        <table style="width:100%; border-collapse:collapse; font-size: 0.82rem;">
            <thead>
                <tr style="background: rgba(0,0,0,0.2); color: var(--text-dim); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px;">
                    <th style="padding: 10px 12px; text-align:left;">ID</th>
                    <th style="padding: 10px 12px; text-align:left;">Executado em</th>
                    <th style="padding: 10px 12px; text-align:left;">Schedule</th>
                    <th style="padding: 10px 12px; text-align:left;">Deadline</th>
                    <th style="padding: 10px 12px; text-align:center;">Diff (s)</th>
                    <th style="padding: 10px 12px; text-align:left;">Ação</th>
                    <th style="padding: 10px 12px; text-align:center;">Presentes</th>
                    <th style="padding: 10px 12px; text-align:left;">Notas</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $acaoCores = [
                'cancel_sent'          => ['#10b981', 'rgba(16,185,129,0.12)', '✅'],
                'quorum_ok'            => ['#38bdf8', 'rgba(56,189,248,0.12)', '✔️'],
                'dedup_skipped'        => ['#94a3b8', 'rgba(148,163,184,0.1)', '⏭️'],
                'deadline_nao_passou'  => ['#64748b', 'rgba(0,0,0,0.15)',      '⏳'],
                'aula_ja_comecou'      => ['#f97316', 'rgba(249,115,22,0.12)', '🔕'],
                'sem_schedule'         => ['#6366f1', 'rgba(99,102,241,0.12)', '📭'],
            ];
            foreach ($cronDiagLogs as $log):
                $acao = $log['acao'] ?? 'desconhecido';
                [$cor, $bg, $icon] = $acaoCores[$acao] ?? ['#e2e8f0', 'transparent', '❓'];
            ?>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); background: <?= $bg ?>;">
                    <td style="padding: 8px 12px; color: var(--text-dim);"><?= $log['id'] ?></td>
                    <td style="padding: 8px 12px; font-family: monospace; color: #e2e8f0;"><?= htmlspecialchars($log['executado_em']) ?></td>
                    <td style="padding: 8px 12px; color: var(--text-dim);"><?= $log['schedule_id'] ?? '—' ?></td>
                    <td style="padding: 8px 12px; font-family: monospace; color: var(--text-dim);">
                        <?= $log['deadline_time'] ? substr($log['deadline_time'], 11, 5) : '—' ?>
                    </td>
                    <td style="padding: 8px 12px; text-align:center; color: var(--text-dim);">
                        <?= $log['diff_segundos'] !== null ? $log['diff_segundos'] . 's' : '—' ?>
                    </td>
                    <td style="padding: 8px 12px;">
                        <span style="color: <?= $cor ?>; font-weight: 600;"><?= $icon ?> <?= htmlspecialchars($acao) ?></span>
                    </td>
                    <td style="padding: 8px 12px; text-align:center; color: var(--text-dim);">
                        <?= $log['attendees_count'] !== null ? $log['attendees_count'] : '—' ?>
                    </td>
                    <td style="padding: 8px 12px; color: var(--text-dim); font-size: 0.78rem; max-width: 220px; word-break: break-word;">
                        <?= htmlspecialchars($log['notas'] ?? '') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="color: var(--text-dim); font-size: 0.78rem; margin-top: 10px;">
            Mostrando os últimos 100 registros. Legenda:
            <span style="color:#10b981">✅ cancel_sent</span> |
            <span style="color:#38bdf8">✔️ quorum_ok</span> |
            <span style="color:#94a3b8">⏭️ dedup_skipped</span> |
            <span style="color:#64748b">⏳ deadline_nao_passou</span> |
            <span style="color:#f97316">🔕 aula_ja_comecou</span>
        </p>
    <?php endif; ?>
</div>

<script>
async function testarCron(scriptUrl, forcar) {
    const painel = document.getElementById('test-console');
    painel.style.display = 'block';
    painel.style.color = '#e2e8f0';
    painel.innerHTML = `⏳ Executando <span style="color:#38bdf8">${scriptUrl}</span>... Aguarde.`;

    let url = `../bot_whatsapp/${scriptUrl}?token=83x9aZ2pLQw1`;
    if (forcar) {
        url += '&test_now=1&force=1&test_hoje=1';
    } else {
        url += '&test_now=1';
    }

    try {
        const response = await fetch(url);
        const text = await response.text();
        
        painel.innerHTML = `<strong style="color: #10b981;">✅ Execução Concluída (HTTP ${response.status}):</strong><br><br>` + 
                           (text ? text.replace(/</g, "&lt;").replace(/>/g, "&gt;") : '<i>[Nenhuma saída de texto retornada pelo script]</i>');
    } catch (err) {
        painel.innerHTML = `<strong style="color: #ef4444;">❌ Erro na Requisição:</strong><br>${err.message}`;
    }
}
</script>
