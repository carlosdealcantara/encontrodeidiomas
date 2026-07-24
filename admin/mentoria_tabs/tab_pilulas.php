<div class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h2><i class="fas fa-pills"></i> Pílulas de Inglês</h2>
        <p style="color: var(--text-dim);">Gestão de conteúdo automatizado para grupos externos</p>
    </div>
</div>

<style>
    .pilula-card { background: var(--card-bg); padding: 20px; border-radius: 12px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
    .badge { padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; }
    .badge.rascunho { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
    .badge.ativo { background: rgba(16, 185, 129, 0.2); color: var(--success); }
</style>

<div class="form-card">
    <h3 class="section-title"><i class="fas fa-inbox"></i> Rascunhos Capturados</h3>
    <p class="help-text" style="margin-bottom: 15px;">Estes áudios foram capturados do The Lounge e aguardam sua configuração para envio externo.</p>
    
    <?php
    $stmt = $conn->query("SELECT * FROM pilulas_conteudo WHERE ativo = 0 ORDER BY criado_em DESC");
    $rascunhos = $stmt->fetchAll();
    if (count($rascunhos) > 0) {
        foreach ($rascunhos as $p) {
            echo '<div class="pilula-card">';
            echo '<div>';
            echo '<span class="badge rascunho">Rascunho</span> ';
            echo '<strong>' . htmlspecialchars($p['titulo']) . '</strong><br>';
            echo '<small style="color:var(--text-dim)">Capturado em: ' . date('d/m/Y H:i', strtotime($p['criado_em'])) . '</small>';
            echo '</div>';
            $json = json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT);
            echo '<div><button type="button" class="btn" style="background:var(--accent-blue);color:white;border:none;padding:8px 15px;border-radius:6px;cursor:pointer;" onclick=\'openPilulaModal('.$json.', false)\'>Configurar Pílula</button></div>';
            echo '</div>';
        }
    } else {
        echo '<p style="color: var(--text-dim);">Nenhum rascunho aguardando. Grave um áudio no grupo The Lounge para começar.</p>';
    }
    ?>
</div>

<div class="form-card">
    <h3 class="section-title"><i class="fas fa-check-circle"></i> Pílulas Ativas</h3>
    <?php
    $stmt = $conn->query("SELECT * FROM pilulas_conteudo WHERE ativo = 1 ORDER BY ultimo_envio DESC, criado_em DESC");
    $ativas = $stmt->fetchAll();
    if (count($ativas) > 0) {
        foreach ($ativas as $p) {
            echo '<div class="pilula-card">';
            echo '<div>';
            echo '<span class="badge ativo">Pronto</span> ';
            echo '<strong>' . htmlspecialchars($p['titulo']) . '</strong><br>';
            echo '<small style="color:var(--text-dim)">Enviado ' . $p['vezes_enviado'] . ' vezes. Último envio: ' . ($p['ultimo_envio'] ? date('d/m/Y', strtotime($p['ultimo_envio'])) : 'Nunca') . '</small>';
            echo '</div>';
            $json = json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT);
            echo '<div><button type="button" class="btn" style="background:rgba(255,255,255,0.1);color:white;border:none;padding:8px 15px;border-radius:6px;cursor:pointer;" onclick=\'openPilulaModal('.$json.', true)\'><i class="fas fa-edit"></i> Editar</button></div>';
            echo '</div>';
        }
    } else {
        echo '<p style="color: var(--text-dim);">Nenhuma pílula ativa no momento.</p>';
    }
    ?>
</div>

<!-- Modal Configurar Pílula -->
<div id="pilulaModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <h2 id="pilulaModalTitle">Configurar Pílula</h2>
        <form method="POST">
            <input type="hidden" name="action_pilula" value="save">
            <input type="hidden" name="pilula_id" id="formPilulaId" value="">
            <input type="hidden" name="tab" value="pilulas">
            
            <div class="form-group">
                <label>Título Interno</label>
                <input type="text" name="titulo" id="pilula_titulo" required class="input-modern" style="width:100%; padding: 10px; margin-top:5px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:var(--white);">
                <small style="color:var(--text-dim)">Apenas para organização, não será enviado no WhatsApp.</small>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label>Texto de Apoio (Opcional)</label>
                <textarea name="texto_corpo" id="pilula_texto" rows="4" class="input-modern" style="width:100%; padding: 10px; margin-top:5px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:var(--white);" placeholder="Ex: Aqui vai uma dica extra sobre a pronúncia..."></textarea>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label>Enquete: Pergunta (Opcional)</label>
                <input type="text" name="enquete_pergunta" id="pilula_enquete_pergunta" class="input-modern" style="width:100%; padding: 10px; margin-top:5px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:var(--white);" placeholder="Ex: Você já conhecia essa expressão?">
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label>Enquete: Opções (Separadas por vírgula)</label>
                <input type="text" name="enquete_opcoes" id="pilula_enquete_opcoes" class="input-modern" style="width:100%; padding: 10px; margin-top:5px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:var(--white);" placeholder="Ex: Sim, Não, Mais ou menos">
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label>
                    <input type="checkbox" name="ativo" id="pilula_ativo" value="1">
                    Ativar Pílula (Pronta para envio)
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="flex: 1; padding: 12px; font-weight:bold;">Salvar</button>
                <button type="button" class="btn btn-danger" style="flex: 1; padding: 12px; font-weight:bold; background:var(--danger); border:none;" onclick="closePilulaModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPilulaModal(pilula, isEdit = false) {
    document.getElementById('formPilulaId').value = pilula.id;
    document.getElementById('pilula_titulo').value = pilula.titulo || '';
    document.getElementById('pilula_texto').value = pilula.texto_corpo || '';
    document.getElementById('pilula_enquete_pergunta').value = pilula.enquete_pergunta || '';
    
    let opcoesStr = '';
    if (pilula.enquete_opcoes) {
        try {
            let ops = JSON.parse(pilula.enquete_opcoes);
            if (Array.isArray(ops)) {
                opcoesStr = ops.join(', ');
            }
        } catch(e) {}
    }
    document.getElementById('pilula_enquete_opcoes').value = opcoesStr;
    
    document.getElementById('pilula_ativo').checked = pilula.ativo == 1;
    
    document.getElementById('pilulaModalTitle').innerText = isEdit ? 'Editar Pílula' : 'Configurar Rascunho';
    document.getElementById('pilulaModal').style.display = 'flex';
}

function closePilulaModal() {
    document.getElementById('pilulaModal').style.display = 'none';
}
</script>
