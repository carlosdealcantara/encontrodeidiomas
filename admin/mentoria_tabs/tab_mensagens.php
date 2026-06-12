<div class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h2><i class="fas fa-robot"></i> Automações da Mentoria</h2>
        <p style="color: var(--text-dim);">Configure as mensagens e mapeamento de grupos do Hub Bidirecional</p>
    </div>
    <div>
        <form method="POST" style="display: inline-block;">
            <input type="hidden" name="tab" value="mensagens">
            <button type="submit" name="sync_groups" class="btn" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; color: white;">
                <i class="fas fa-sync-alt"></i> Sincronizar Grupos
            </button>
        </form>
    </div>
</div>

<style>
    .sub-tabs-container { margin-bottom: 20px; display: flex; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; overflow-x: auto; }
    .sub-tab-btn { padding: 10px 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: transparent; color: var(--text-dim); cursor: pointer; font-weight: 600; white-space: nowrap; transition: 0.2s; }
    .sub-tab-btn:hover { color: var(--white); background: rgba(255,255,255,0.05); }
    .sub-tab-btn.active { background: var(--accent-red); color: white; border-color: var(--accent-red); }
    .sub-tab-pane { display: none; }
    .sub-tab-pane.active { display: block; animation: fadeIn 0.3s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    .form-card { background: var(--card-bg); padding: 25px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; color: var(--text-dim); font-weight: 600; }
    .form-group input[type="text"], .form-group textarea { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; }
    .form-group textarea { resize: vertical; min-height: 120px; }
    .help-text { font-size: 0.85rem; color: var(--text-dim); margin-top: 5px; }
    .section-title { font-size: 1.2rem; color: #38bdf8; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }
</style>

<div class="sub-tabs-container">
    <button class="sub-tab-btn active" onclick="openSubTab('sub_global')"><i class="fas fa-globe"></i> Configuração Global</button>
    <button class="sub-tab-btn" onclick="openSubTab('sub_thelounge')"><i class="fas fa-coffee"></i> The Lounge</button>
    <button class="sub-tab-btn" onclick="openSubTab('sub_desafio')"><i class="fas fa-fire"></i> Desafio Diário</button>
    <button class="sub-tab-btn" onclick="openSubTab('sub_meetups')"><i class="fas fa-video"></i> Our Meetups</button>
</div>

<form method="POST">
    <input type="hidden" name="tab" value="mensagens">
    
    <!-- SUB TAB GLOBAL -->
    <div id="sub_global" class="sub-tab-pane active">
        <div class="form-card">
            <h3 class="section-title"><i class="fas fa-cog"></i> Configuração Geral</h3>
            <div class="form-group">
                <label>Seu WhatsApp JID (Admin)</label>
                <input type="text" name="admin_jid" value="<?= htmlspecialchars($admin_jid) ?>" required>
                <p class="help-text">Este número é excluído do ranking e do sistema de expulsão do desafio.</p>
            </div>
        </div>
    </div>

    <!-- SUB TAB THE LOUNGE -->
    <div id="sub_thelounge" class="sub-tab-pane">
        <div class="form-card">
            <h3 class="section-title"><i class="fas fa-coffee"></i> The Lounge</h3>
            <div class="form-group">
                <label>Mapeamento de Grupo (JID)</label>
                <?= renderGroupSelect('jid_the_lounge', $jid_the_lounge, $available_groups) ?>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label>Mensagem de Boas-vindas</label>
                <textarea name="tpl_welcome"><?= htmlspecialchars($tpl_welcome) ?></textarea>
                <p class="help-text">Use <code>{name}</code> para o nome ou <code>@{name}</code> para marcar a pessoa.</p>
            </div>
            <div class="form-group">
                <label>Ranking Diário (Enviado à meia-noite)</label>
                <textarea name="tpl_ranking"><?= htmlspecialchars($tpl_ranking) ?></textarea>
                <p class="help-text">Use <code>{date}</code> para a data e <code>{ranking_list}</code> para injetar a lista top 5.</p>
            </div>
        </div>
    </div>

    <!-- SUB TAB DESAFIO DIÁRIO -->
    <div id="sub_desafio" class="sub-tab-pane">
        <div class="form-card">
            <h3 class="section-title"><i class="fas fa-fire"></i> Desafio Diário</h3>
            <div class="form-group">
                <label>Mapeamento de Grupo (JID)</label>
                <?= renderGroupSelect('jid_desafio', $jid_desafio, $available_groups) ?>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label>Aviso Prévio Desafio (Às 21h)</label>
                <textarea name="tpl_aviso_desafio"><?= htmlspecialchars($tpl_aviso_desafio) ?></textarea>
                <p class="help-text">As alunas que ainda não enviaram imagem no dia serão marcadas junto com a mensagem.</p>
            </div>
            <div class="form-group">
                <label>Mensagem de Expulsão (À meia-noite)</label>
                <textarea name="tpl_kick_desafio"><?= htmlspecialchars($tpl_kick_desafio) ?></textarea>
                <p class="help-text">Use <code>@{name}</code> para mencionar quem está sendo removido.</p>
            </div>
        </div>
    </div>

    <!-- SUB TAB OUR MEETUPS -->
    <div id="sub_meetups" class="sub-tab-pane">
        <div class="form-card">
            <h3 class="section-title"><i class="fas fa-video"></i> Our Meetups</h3>
            <div class="form-group">
                <label>Mapeamento de Grupo (JID)</label>
                <?= renderGroupSelect('jid_our_meetups', $jid_our_meetups, $available_groups) ?>
            </div>
            
            <!-- Removed the unused lembrete diário since it's now meetup specific -->
            <div class="form-group" style="display:none;">
                <textarea name="tpl_lembrete"><?= htmlspecialchars($tpl_lembrete) ?></textarea>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Aviso Matinal Our Meetups (À meia-noite no dia da aula)</label>
                <textarea name="tpl_meetup_aviso"><?= htmlspecialchars($tpl_meetup_aviso) ?></textarea>
                <p class="help-text">Use <code>{horario}</code> e <code>{deadline}</code> para mostrar as horas da aula e do prazo final de inscrição.</p>
            </div>
            <div class="form-group">
                <label>Cancelamento (1h antes, se 0 confirmados)</label>
                <textarea name="tpl_meetup_cancel"><?= htmlspecialchars($tpl_meetup_cancel) ?></textarea>
            </div>
            <div class="form-group">
                <label>Início da Aula (Kick-off na hora exata, se > 0 confirmados)</label>
                <textarea name="tpl_meetup_kickoff"><?= htmlspecialchars($tpl_meetup_kickoff) ?></textarea>
                <p class="help-text">Use <code>{link}</code> para o link do Google Meet.</p>
            </div>
        </div>
    </div>

    <button type="submit" name="save_config" class="btn" style="background: var(--accent-red); color: white; font-size: 1.1rem; padding: 15px 30px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; margin-bottom: 20px;">
        <i class="fas fa-save"></i> Salvar Configurações no Robô
    </button>
</form>

<script>
    function openSubTab(tabId) {
        document.querySelectorAll('.sub-tab-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.sub-tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }
</script>
