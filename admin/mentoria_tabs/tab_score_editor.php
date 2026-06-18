<style>
/* ── Ranking Panel – global ──────────────────────────── */
#rankingPanel h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--white);
    margin-bottom: 4px;
}
#rankingPanel .rp-sub {
    color: var(--text-dim);
    font-size: 0.88rem;
    margin-bottom: 28px;
}

/* ── Section blocks ──────────────────────────────────── */
.rp-section {
    margin-bottom: 40px;
}
.rp-section-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--white);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.rp-section-desc {
    font-size: 0.8rem;
    color: var(--text-dim);
    margin-bottom: 14px;
}

/* ── Tables ──────────────────────────────────────────── */
.rp-scroll { overflow-x: auto; }

.rp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    min-width: 500px;
}
.rp-table thead tr {
    border-bottom: 2px solid rgba(255,255,255,0.08);
}
.rp-table th {
    padding: 8px 10px;
    color: var(--text-dim);
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    font-size: 0.78rem;
}
.rp-table th:first-child { text-align: left; }
.rp-table th .rp-pts {
    display: block;
    font-size: 0.7rem;
    font-weight: 400;
    color: rgba(255,255,255,0.3);
    margin-top: 1px;
}
.rp-table th.rp-total-head {
    color: var(--accent-blue);
}
.rp-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.15s;
}
.rp-table tbody tr:hover { background: rgba(255,255,255,0.03); }
.rp-table td {
    padding: 9px 10px;
    color: var(--text-main);
    text-align: center;
}
.rp-table td:first-child {
    text-align: left;
    font-weight: 600;
    white-space: nowrap;
}
.rp-table td.rp-total-cell {
    color: var(--accent-blue);
    font-weight: 700;
    font-size: 0.9rem;
}

/* Toggle switch */
.rp-toggle {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
}
.rp-toggle input { display: none; }
.rp-slider {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.12);
    border-radius: 22px;
    cursor: pointer;
    transition: background 0.25s;
}
.rp-slider::before {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #fff;
    top: 3px;
    left: 3px;
    transition: transform 0.25s;
}
.rp-toggle input:checked + .rp-slider { background: #10b981; }
.rp-toggle input:checked + .rp-slider::before { transform: translateX(18px); }

/* Number inputs */
.rp-num {
    width: 52px;
    background: var(--input-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: var(--text-main);
    text-align: center;
    padding: 3px 4px;
    font-size: 0.85rem;
    transition: border-color 0.2s;
}
.rp-num:focus { outline: none; border-color: var(--accent-blue); }
.rp-num.rp-zero { color: rgba(255,255,255,0.25); }

/* Loading / empty */
.rp-empty { color: var(--text-dim); padding: 12px 0; font-size: 0.9rem; }
</style>

<div id="rankingPanel">
    <h2>🏆 Ranking — Gestão do Dia</h2>
    <p class="rp-sub">
        Visualize e corrija os dados de hoje antes da meia-noite.<br>
        As edições são salvas diretamente no log e serão lidas pelo cron de pontuação.
    </p>

    <!-- ── ATIVIDADES ── -->
    <div class="rp-section">
        <div class="rp-section-title">⭐ Atividades <span style="font-weight:400;font-size:0.85rem;color:var(--text-dim)">(Student of the Day)</span></div>
        <p class="rp-section-desc">
            Pontos por participação nos grupos temáticos. O toggle de Aula reflete a presença confirmada via !attend.
        </p>
        <div class="rp-scroll">
            <div id="rpActivities"><p class="rp-empty">Carregando…</p></div>
        </div>
    </div>

    <!-- ── WORD SLINGERS ── -->
    <div class="rp-section">
        <div class="rp-section-title">💬 Word Slingers <span style="font-weight:400;font-size:0.85rem;color:var(--text-dim)">(mensagens enviadas por grupo)</span></div>
        <p class="rp-section-desc">
            Contagem de mensagens de texto por grupo. A coluna <strong style="color:var(--accent-blue)">Total</strong> = soma de mensagens + imagens + áudios de todos os grupos — esse é o valor real que o cron usa no ranking.
        </p>
        <div class="rp-scroll">
            <div id="rpWordSlingers"><p class="rp-empty">Carregando…</p></div>
        </div>
    </div>

    <!-- ── EMOJI GANG ── -->
    <div class="rp-section">
        <div class="rp-section-title">😄 Emoji Gang <span style="font-weight:400;font-size:0.85rem;color:var(--text-dim)">(reações dadas por grupo)</span></div>
        <p class="rp-section-desc">
            Reações dadas em cada grupo. A coluna <strong style="color:var(--accent-blue)">Total</strong> é o que o cron usa.
        </p>
        <div class="rp-scroll">
            <div id="rpEmojiGang"><p class="rp-empty">Carregando…</p></div>
        </div>
    </div>
</div>

<script>
(function () {
    let _groupsOrdered = [];

    // ── Fetch ──────────────────────────────────────────
    function loadScoreEditor() {
        fetch('mentoria_score_edit_api.php?action=load')
            .then(r => r.json())
            .then(data => {
                if (!data.success) { showError('rpActivities', data.error); return; }
                _groupsOrdered = data.groups_ordered || [];
                renderActivities(data.students);
                renderWordSlingers(data.groups_ordered, data.social);
                renderEmojiGang(data.groups_ordered, data.social);
            })
            .catch(err => showError('rpActivities', err.message));
    }

    function showError(id, msg) {
        document.getElementById(id).innerHTML =
            `<p class="rp-empty" style="color:var(--accent-red)">Erro: ${msg}</p>`;
    }

    // ── ATIVIDADES ──────────────────────────────────────
    function renderActivities(students) {
        const el = document.getElementById('rpActivities');
        if (!students || !students.length) { el.innerHTML = '<p class="rp-empty">Nenhuma atividade hoje ainda.</p>'; return; }

        const cols = [
            { key: 'pronun',  label: '🎤 Pronún.',  pts: '5 pts' },
            { key: 'desafio', label: '📸 Desafio',  pts: '5 pts' },
            { key: 'music',   label: '🎵 Music Lab', pts: '4 pts' },
            { key: 'games',   label: '🎮 Games',    pts: '2 pts' },
            { key: 'vocab',   label: '📖 Vocab.',   pts: '1 pt'  },
        ];

        let th = `<th>Aluno</th>
                  <th>🖥️ Aula<span class="rp-pts">20 pts</span></th>`;
        cols.forEach(c => { th += `<th>${c.label}<span class="rp-pts">${c.pts}</span></th>`; });

        let rows = '';
        students.forEach(s => {
            const safe = s.jid.replace(/[@.]/g, '_');
            let cells = `<td>${s.name}</td>`;
            cells += `<td><label class="rp-toggle">
                        <input type="checkbox" ${s.class_attended ? 'checked' : ''}
                               onchange="rpToggleAtt('${s.jid}', this.checked)">
                        <span class="rp-slider"></span></label></td>`;
            cols.forEach(c => {
                const v = s[c.key] ?? 0;
                cells += `<td><input type="number" min="0" class="rp-num${v === 0 ? ' rp-zero' : ''}"
                               value="${v}" onchange="rpEditAct('${s.jid}','${c.key}',this.value,this)"></td>`;
            });
            rows += `<tr>${cells}</tr>`;
        });

        el.innerHTML = `<table class="rp-table"><thead><tr>${th}</tr></thead><tbody>${rows}</tbody></table>`;
    }

    // ── WORD SLINGERS ───────────────────────────────────
    function renderWordSlingers(groups, social) {
        const el = document.getElementById('rpWordSlingers');
        if (!social || !social.length) { el.innerHTML = '<p class="rp-empty">Nenhuma mensagem hoje ainda.</p>'; return; }

        // Filtra só quem tem pelo menos 1 interação
        const active = social.filter(s => s.total_interactions > 0);
        if (!active.length) { el.innerHTML = '<p class="rp-empty">Nenhuma mensagem hoje ainda.</p>'; return; }

        // Header: Name + one col per group (messages) + Total
        let th = '<th>Aluno</th>';
        groups.forEach(g => {
            th += `<th>${g.name}<span class="rp-pts">msgs texto</span></th>`;
        });
        th += '<th class="rp-total-head">Total<span class="rp-pts">cron usa este</span></th>';

        let rows = '';
        active.forEach(s => {
            let cells = `<td>${s.name}</td>`;
            groups.forEach(g => {
                const gData = s.by_group[g.jid] || {};
                const v = gData.messages ?? 0;
                // Mostra mídias como hint no title para auditoria
                const media = (gData.images_sent ?? 0) + (gData.audios_sent ?? 0);
                const hint = media > 0 ? ` title="+${media} mídia(s) neste grupo"` : '';
                cells += `<td><input type="number" min="0" class="rp-num${v===0?' rp-zero':''}"
                               value="${v}"${hint}
                               onchange="rpEditSocial('${s.jid}','${g.jid}','messages',this.value,this)"></td>`;
            });
            cells += `<td class="rp-total-cell">${s.total_interactions}</td>`;
            rows += `<tr>${cells}</tr>`;
        });

        el.innerHTML = `<table class="rp-table"><thead><tr>${th}</tr></thead><tbody>${rows}</tbody></table>`;
    }

    // ── EMOJI GANG ──────────────────────────────────────
    function renderEmojiGang(groups, social) {
        const el = document.getElementById('rpEmojiGang');
        if (!social || !social.length) { el.innerHTML = '<p class="rp-empty">Nenhuma reação hoje ainda.</p>'; return; }

        const active = social.filter(s => s.total_reactions > 0);
        if (!active.length) { el.innerHTML = '<p class="rp-empty">Nenhuma reação hoje ainda.</p>'; return; }

        let th = '<th>Aluno</th>';
        groups.forEach(g => { th += `<th>${g.name}<span class="rp-pts">reações</span></th>`; });
        th += '<th class="rp-total-head">Total<span class="rp-pts">cron usa este</span></th>';

        let rows = '';
        active.forEach(s => {
            let cells = `<td>${s.name}</td>`;
            groups.forEach(g => {
                const v = (s.by_group[g.jid] || {}).reactions_given ?? 0;
                cells += `<td><input type="number" min="0" class="rp-num${v===0?' rp-zero':''}"
                               value="${v}"
                               onchange="rpEditSocial('${s.jid}','${g.jid}','reactions_given',this.value,this)"></td>`;
            });
            cells += `<td class="rp-total-cell">${s.total_reactions}</td>`;
            rows += `<tr>${cells}</tr>`;
        });

        el.innerHTML = `<table class="rp-table"><thead><tr>${th}</tr></thead><tbody>${rows}</tbody></table>`;
    }

    // ── API helpers ────────────────────────────────────
    window.rpToggleAtt = function(jid, attended) {
        _post({ action: 'toggle_attendance', member_jid: jid, attended });
    };

    window.rpEditAct = function(jid, type, value, el) {
        const v = parseInt(value) || 0;
        el.classList.toggle('rp-zero', v === 0);
        _post({ action: 'edit_activity', member_jid: jid, type, value: v });
    };

    window.rpEditSocial = function(jid, groupJid, field, value, el) {
        const v = parseInt(value) || 0;
        el.classList.toggle('rp-zero', v === 0);
        _post({ action: 'edit_social', member_jid: jid, group_jid: groupJid, field, value: v });
    };

    function _post(body) {
        fetch('mentoria_score_edit_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(r => r.json()).then(d => { if (!d.success) alert('Erro ao salvar: ' + d.error); });
    }

    // Carrega ao exibir a aba
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadScoreEditor);
    } else {
        loadScoreEditor();
    }
})();
</script>
