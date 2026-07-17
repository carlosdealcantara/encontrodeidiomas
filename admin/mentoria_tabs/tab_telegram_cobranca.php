<hr style="border-color: rgba(255,255,255,0.05); margin: 40px 0;">

<div class="header" style="margin-bottom: 20px;">
    <div class="header-title">
        <h2>Avisos de Cobrança via Telegram (Relay Manual)</h2>
        <p>Receba as mensagens de cobrança no seu Telegram para copiar e enviar manualmente aos alunos.</p>
    </div>
</div>

<?php
// Carrega estado atual
$telegramAtivo = (int)getSetting('telegram_cobranca_ativo', '0');

// Busca histórico
$historico = [];
try {
    $stmtHist = $conn->query("
        SELECT l.*, a.nome, m.cenario 
        FROM telegram_cobranca_logs l
        JOIN mentoria_alunos a ON l.aluno_id = a.id
        JOIN mentoria_mensagens m ON l.mensagem_id = m.id
        ORDER BY l.created_at DESC LIMIT 15
    ");
    $historico = $stmtHist->fetchAll();
} catch (Exception $e) {
    // Tabela pode não existir ainda
}
?>

<form method="POST" action="mentoria.php">
    <input type="hidden" name="tab" value="telegram_cobranca">
    
    <div class="card" style="background: var(--card-bg); padding: 25px; border-radius: 15px; border: 1px solid <?= $telegramAtivo ? 'var(--success)' : 'rgba(255,255,255,0.1)' ?>; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
            <div>
                <h3 style="color: <?= $telegramAtivo ? 'var(--success)' : 'white' ?>; margin-bottom: 5px;">
                    <i class="fab fa-telegram"></i> Status do Relay
                </h3>
                <p style="color: var(--text-dim); font-size: 0.95rem;">
                    Quando ativado, os avisos programados de cobrança (3 dias, 1 dia e 0 dia) serão enviados para o seu bot do Telegram, mesmo se estiverem inativos no painel principal.
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 15px; background: rgba(0,0,0,0.2); padding: 12px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <span style="font-weight: 600; font-size: 1.05rem; color: white;">
                        Ativar Relay do Telegram
                    </span>
                    <input type="checkbox" name="telegram_cobranca_ativo" <?= $telegramAtivo ? 'checked' : '' ?> style="accent-color: var(--success); width: 22px; height: 22px; cursor: pointer;">
                </label>
                <?php if ($telegramAtivo): ?>
                    <span style="background: rgba(34, 197, 94, 0.2); color: var(--success); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: bold;">LIGADO</span>
                <?php else: ?>
                    <span style="background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: bold;">DESLIGADO</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="display: flex; gap: 15px; margin-top: 20px;">
            <button type="submit" name="save_telegram_relay" style="background: var(--accent-blue); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-save"></i> Salvar Configuração
            </button>
            
            <a href="../bot_telegram/mentoria_telegram_cron.php?token=83x9aZ2pLQw1&force=1" target="_blank" style="background: rgba(255,255,255,0.1); color: white; text-decoration: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.2s;">
                <i class="fas fa-play"></i> Testar Relay Agora (Forçar)
            </a>
        </div>
    </div>
</form>

<div class="card" style="background: var(--card-bg); padding: 25px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05);">
    <h3 style="margin-bottom: 20px;"><i class="fas fa-history"></i> Últimos Avisos Enviados</h3>
    
    <?php if (empty($historico)): ?>
        <p style="color: var(--text-dim); text-align: center; padding: 20px;">Nenhum aviso foi enviado ainda.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--text-dim);">Data/Hora</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--text-dim);">Aluno</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--text-dim);">Tipo de Aviso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historico as $h): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                    <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 600; color: white;"><?= htmlspecialchars($h['nome']) ?></td>
                    <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);"><span class="badge" style="background: rgba(56, 189, 248, 0.1); color: var(--accent-blue); padding: 5px 10px; border-radius: 8px;"><?= htmlspecialchars($h['cenario']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
