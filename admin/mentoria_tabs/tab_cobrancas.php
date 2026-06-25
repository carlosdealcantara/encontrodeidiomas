<hr style="border-color: rgba(255,255,255,0.05); margin: 40px 0;">

<div class="header" style="margin-bottom: 20px;">
    <div class="header-title">
        <h2>Automação de Mensalidades</h2>
        <p>Edite os textos das cobranças automatizadas (Agradecimento, Avisos Prévios e Vencimento).</p>
    </div>
</div>

<form method="POST" action="mentoria.php">
    <input type="hidden" name="tab" value="cobrancas">
    
    <!-- RODAPÉ GLOBAL PIX -->
    <div class="card" style="background: var(--card-bg); padding: 25px; border-radius: 15px; border: 1px solid var(--accent-blue); margin-bottom: 30px;">
        <h3 style="color: var(--accent-blue); margin-bottom: 10px;"><i class="fas fa-money-check-alt"></i> Rodapé Padrão de Cobrança (PIX)</h3>
        <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 15px;">Este texto será adicionado no final de todas as mensagens de cobrança.</p>
        <textarea name="pix_footer" rows="4" style="width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white;" required><?= htmlspecialchars($pix_footer_atual) ?></textarea>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <?php foreach ($mensagens_cobranca as $msg): ?>
        <div class="card" style="background: var(--card-bg); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <strong style="font-size: 1.05rem;"><?= htmlspecialchars($msg['cenario']) ?></strong>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="msgs[<?= $msg['id'] ?>][ativo]" <?= $msg['ativo'] ? 'checked' : '' ?> style="accent-color: var(--success); width: 18px; height: 18px;">
                    <span style="font-size: 0.9rem; color: var(--text-dim);">Ativado</span>
                </label>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-dim); margin-bottom: 5px;">Dias para o Vencimento</label>
                <?php if($msg['dias_antes'] == -999): ?>
                    <input type="text" value="Disparo Imediato" disabled style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: var(--text-dim);">
                    <input type="hidden" name="msgs[<?= $msg['id'] ?>][dias]" value="-999">
                <?php else: ?>
                    <input type="number" name="msgs[<?= $msg['id'] ?>][dias]" value="<?= $msg['dias_antes'] ?>" style="width: 100%; padding: 10px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white;" required>
                <?php endif; ?>
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-dim); margin-bottom: 5px;">Texto da Mensagem</label>
                <textarea name="msgs[<?= $msg['id'] ?>][texto]" rows="5" style="width: 100%; padding: 10px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; resize: vertical;" required><?= htmlspecialchars($msg['texto']) ?></textarea>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" name="save_cobranca" style="background: var(--success); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-save"></i> Salvar Templates de Cobrança
    </button>
</form>
