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
    <button class="sub-tab-btn" onclick="openSubTab('sub_meetups')"><i class="fas fa-video"></i> Our Classes</button>
    <button class="sub-tab-btn" onclick="openSubTab('sub_pronunciation')">🗣️ Reading out loud</button>
    <button class="sub-tab-btn" onclick="openSubTab('sub_music')">🎶 Music Lab</button>
    <button class="sub-tab-btn" onclick="openSubTab('sub_vocabulary')">📒 New word!</button>
    <button class="sub-tab-btn" onclick="openSubTab('sub_games')">🧩 Games</button>
    <button class="sub-tab-btn" onclick="openSubTab('sub_testes')"><i class="fas fa-vial"></i> Testes Manuais</button>
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
            <div class="form-group" style="margin-top: 20px;">
                <label>Ranking Diário (Student of the Day + Social)</label>
                <textarea name="tpl_ranking_dedicados" style="min-height: 250px;"><?= htmlspecialchars($tpl_ranking_dedicados) ?></textarea>
                <p class="help-text">Ranking Unificado. Variáveis: <code>{date}</code>, <code>{student_of_the_day}</code>, <code>{other_participants}</code>, <code>{legend}</code>, <code>{word_slingers_list}</code> e <code>{emoji_gang_list}</code>.</p>
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
            <div class="form-group" style="margin-top: 20px;">
                <label>Confirmação de Streak (Tempo Real)</label>
                <textarea name="tpl_streak_confirm"><?= htmlspecialchars($tpl_streak_confirm) ?></textarea>
                <p class="help-text">Enviada assim que a pessoa manda a imagem no grupo. Use <code>@{name}</code> e <code>{streak}</code> para o número de dias seguidos.</p>
            </div>
            <div class="form-group">
                <label>Celebração de Milestone (Tempo Real)</label>
                <textarea name="tpl_streak_milestone"><?= htmlspecialchars($tpl_streak_milestone) ?></textarea>
                <p class="help-text">Enviada quando a pessoa atinge 3, 7, 10, 15, 30, 60, 90... dias. Use <code>@{name}</code> e <code>{streak}</code>.</p>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label>Leaderboard de Streaks (Comando !streaks)</label>
                <textarea name="tpl_streak_leaderboard"><?= htmlspecialchars($tpl_streak_leaderboard) ?></textarea>
                <p class="help-text">Enviado apenas quando alguém digita <code>!streaks</code> no grupo. Use <code>{allTimeList}</code> e <code>{activeList}</code>.</p>
            </div>
        </div>
    </div>

    <!-- SUB TAB OUR CLASSES -->
    <div id="sub_meetups" class="sub-tab-pane">
        <div class="form-card">
            <h3 class="section-title"><i class="fas fa-video"></i> Our Classes</h3>
            <p style="color: var(--text-dim); margin-bottom: 20px;">Configurações e mensagens do grupo oficial das aulas de mentoria.</p>
            
            <div class="form-group">
                <label>Mapeamento de Grupo (JID)</label>
                <?= renderGroupSelect('jid_our_classes', $jid_our_classes, $available_groups) ?>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Aviso Matinal Our Classes (À meia-noite no dia da aula)</label>
                <textarea name="tpl_class_aviso"><?= htmlspecialchars($tpl_class_aviso) ?></textarea>
                <p class="help-text">Use <code>{horario}</code> e <code>{deadline}</code> para mostrar as horas da aula e do prazo final de inscrição.</p>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Aviso de Aula Cancelada</label>
                <textarea name="tpl_class_cancel"><?= htmlspecialchars($tpl_class_cancel) ?></textarea>
                <p class="help-text">Enviada 1 hora antes caso ninguém confirme presença. Use <code>{horario}</code>.</p>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Kick-off da Aula (Link do Google Meet)</label>
                <textarea name="tpl_class_kickoff"><?= htmlspecialchars($tpl_class_kickoff) ?></textarea>
                <p class="help-text">Disparada no momento exato da aula. Use <code>{link}</code> para o URL da sala. A tolerância é de 15 minutos.</p>
            </div>

            <h4 style="margin-top: 40px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;"><i class="fas fa-users"></i> Templates para Prática entre Alunos</h4>

            <div class="form-group">
                <label>Aviso Matinal da Prática</label>
                <textarea name="tpl_practice_aviso"><?= htmlspecialchars($tpl_practice_aviso) ?></textarea>
                <p class="help-text">Aviso da sessão de prática sem professor. Use <code>{horario}</code> e <code>{deadline}</code>.</p>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Aviso de Prática Cancelada (Falta de Quórum)</label>
                <textarea name="tpl_practice_cancel"><?= htmlspecialchars($tpl_practice_cancel) ?></textarea>
                <p class="help-text">Enviada 1 hora antes se houver menos de 2 alunos confirmados. Use <code>{horario}</code>.</p>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Kick-off da Prática (Link do Google Meet)</label>
                <textarea name="tpl_practice_kickoff"><?= htmlspecialchars($tpl_practice_kickoff) ?></textarea>
                <p class="help-text">Disparada no momento da sessão de prática. Use <code>{link}</code>.</p>
            </div>

            <h4 style="margin-top: 40px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;"><i class="fas fa-search"></i> Comando de Consulta</h4>

            <div class="form-group">
                <label>Status da Aula (!list)</label>
                <textarea name="tpl_class_status"><?= htmlspecialchars($tpl_class_status) ?></textarea>
                <p class="help-text">Retorna as informações da próxima aula sem registrar o aluno. Use <code>{class_info}</code>, <code>{attendees}</code> e <code>{deadline_info}</code>.</p>
            </div>
        </div>
    </div>

    <!-- SUB TAB READING OUT LOUD -->
    <div id="sub_pronunciation" class="sub-tab-pane">
        <div class="form-card">
            <h3 class="section-title">🗣️ Reading out loud</h3>
            <p style="color: var(--text-dim); margin-bottom: 20px;">Grupo de prática de pronúncia. O bot detecta automaticamente quando alguém envia um <strong>áudio</strong> e registra como atividade de pronúncia (<strong>4 pts + ⭐</strong>) para o ranking diário.</p>
            <div class="form-group">
                <label>Mapeamento de Grupo (JID)</label>
                <?= renderGroupSelect('jid_pronunciation', $jid_pronunciation, $available_groups) ?>
                <p class="help-text">Selecione o grupo "Reading out loud" do WhatsApp. Mensagens de <strong>áudio</strong> enviadas aqui serão contabilizadas no ranking.</p>
            </div>
        </div>
    </div>

    <!-- SUB TAB MUSIC LAB -->
    <div id="sub_music" class="sub-tab-pane">
        <div class="form-card">
            <h3 class="section-title">🎶 Music Lab</h3>
            <p style="color: var(--text-dim); margin-bottom: 20px;">Grupo de música com LingoClip. O bot detecta automaticamente quando alguém envia uma <strong>imagem</strong> (print da partida) e registra como atividade musical (<strong>2 pts</strong>) para o ranking diário.</p>
            <div class="form-group">
                <label>Mapeamento de Grupo (JID)</label>
                <?= renderGroupSelect('jid_music', $jid_music, $available_groups) ?>
                <p class="help-text">Selecione o grupo "Music Lab" do WhatsApp. <strong>Imagens</strong> enviadas aqui serão contabilizadas no ranking.</p>
            </div>
        </div>
    </div>

    <!-- SUB TAB NEW WORD! -->
    <div id="sub_vocabulary" class="sub-tab-pane">
        <div class="form-card">
            <h3 class="section-title">📒 New word!</h3>
            <p style="color: var(--text-dim); margin-bottom: 20px;">Grupo de vocabulário. O bot detecta automaticamente quando alguém envia uma <strong>imagem</strong> (palavras novas aprendidas) e registra como atividade de vocabulário (<strong>2 pts</strong>) para o ranking diário.</p>
            <div class="form-group">
                <label>Mapeamento de Grupo (JID)</label>
                <?= renderGroupSelect('jid_vocabulary', $jid_vocabulary, $available_groups) ?>
                <p class="help-text">Selecione o grupo "New word!" do WhatsApp. <strong>Imagens</strong> enviadas aqui serão contabilizadas no ranking.</p>
            </div>
        </div>
    </div>

    <!-- SUB TAB GAMES -->
    <div id="sub_games" class="sub-tab-pane">
        <div class="form-card">
            <h3 class="section-title">🧩 Games</h3>
            <p style="color: var(--text-dim); margin-bottom: 20px;">Grupo de jogos educativos de inglês. O bot detecta automaticamente quando alguém envia uma <strong>imagem</strong> (print do jogo) e registra como atividade lúdica (<strong>2 pts</strong>) para o ranking diário.</p>
            <div class="form-group">
                <label>Mapeamento de Grupo (JID)</label>
                <?= renderGroupSelect('jid_games', $jid_games, $available_groups) ?>
                <p class="help-text">Selecione o grupo "Games" do WhatsApp. <strong>Imagens</strong> enviadas aqui serão contabilizadas no ranking.</p>
            </div>
        </div>
    </div>

    <button type="submit" name="save_config" class="btn" style="background: var(--accent-red); color: white; font-size: 1.1rem; padding: 15px 30px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; margin-bottom: 20px;">
        <i class="fas fa-save"></i> Salvar Configurações no Robô
    </button>
</form>

<!-- SUB TAB TESTES MANUAIS (Fora do Form para evitar conflito de botões) -->
<div id="sub_testes" class="sub-tab-pane">
    <?php include 'tab_testes.php'; ?>
</div>

<script>
    function openSubTab(tabId, btn) {
        document.querySelectorAll('.sub-tab-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.sub-tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        
        if (btn) {
            btn.classList.add('active');
        } else if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        } else {
            const targetBtn = document.querySelector(`.sub-tab-btn[onclick*="${tabId}"]`);
            if (targetBtn) targetBtn.classList.add('active');
        }
        localStorage.setItem('active_mentoria_sub_tab', tabId);
    }

    document.addEventListener("DOMContentLoaded", function() {
        const savedTab = localStorage.getItem('active_mentoria_sub_tab');
        if (savedTab && document.getElementById(savedTab)) {
            openSubTab(savedTab);
        }
    });
</script>
