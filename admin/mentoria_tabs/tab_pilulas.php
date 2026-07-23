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
            echo '<div><button class="btn" style="background:var(--accent-blue);color:white;border:none;padding:8px 15px;border-radius:6px;cursor:pointer;">Configurar Pílula</button></div>';
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
            echo '<div><button class="btn" style="background:rgba(255,255,255,0.1);color:white;border:none;padding:8px 15px;border-radius:6px;cursor:pointer;"><i class="fas fa-edit"></i> Editar</button></div>';
            echo '</div>';
        }
    } else {
        echo '<p style="color: var(--text-dim);">Nenhuma pílula ativa no momento.</p>';
    }
    ?>
</div>
