<style>
/* ── Ranking Panel ─────────────────────────────────── */
#rankingPanel h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--white);
    margin-bottom: 4px;
}
#rankingPanel .rp-sub {
    color: var(--text-dim);
    font-size: 0.9rem;
    margin-bottom: 24px;
}
#rankingPanel table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
#rankingPanel thead tr {
    border-bottom: 2px solid rgba(255,255,255,0.08);
}
#rankingPanel th {
    padding: 10px 12px;
    color: var(--text-dim);
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}
#rankingPanel th:first-child { text-align: left; }
#rankingPanel tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: background 0.2s;
}
#rankingPanel tbody tr:hover { background: rgba(255,255,255,0.03); }
#rankingPanel td {
    padding: 10px 12px;
    color: var(--text-main);
    text-align: center;
}
#rankingPanel td:first-child {
    text-align: left;
    font-weight: 600;
}

/* Toggle switch – CSS puro */
.rp-toggle {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 24px;
}
.rp-toggle input { display: none; }
.rp-slider {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.12);
    border-radius: 24px;
    cursor: pointer;
    transition: background 0.25s;
}
.rp-slider::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
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
    width: 56px;
    background: var(--input-bg);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 6px;
    color: var(--text-main);
    text-align: center;
    padding: 4px 6px;
    font-size: 0.9rem;
}
.rp-num:focus { outline: none; border-color: var(--accent-blue); }

.rp-badge-pts {
    display: block;
    font-size: 0.7rem;
    color: var(--text-dim);
    margin-top: 2px;
}

#scoreEditorContainer .rp-empty {
    color: var(--text-dim);
    padding: 20px 0;
}
</style>

<div id="rankingPanel">
    <h2>🏆 Ranking — Gestão do Dia</h2>
    <p class="rp-sub">
        Visualize e corrija as pontuações de hoje antes da meia-noite. Alterações são salvas imediatamente.
    </p>

    <div id="scoreEditorContainer">
        <p class="rp-empty">Carregando dados...</p>
    </div>
</div>

<script>
(function() {
    function loadScoreEditor() {
        fetch('mentoria_score_edit_api.php?action=load')
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('scoreEditorContainer').innerHTML =
                        '<p class="rp-empty" style="color:var(--accent-red)">' + (data.error || 'Erro ao carregar.') + '</p>';
                    return;
                }
                renderTable(data.students);
            })
            .catch(err => {
                document.getElementById('scoreEditorContainer').innerHTML =
                    '<p class="rp-empty" style="color:var(--accent-red)">Erro de conexão: ' + err.message + '</p>';
            });
    }

    function renderTable(students) {
        if (!students || students.length === 0) {
            document.getElementById('scoreEditorContainer').innerHTML =
                '<p class="rp-empty">Nenhuma atividade registrada hoje ainda.</p>';
            return;
        }

        const cols = [
            { key: 'pronun',  label: '🎤 Pronún',  pts: '5 pts' },
            { key: 'desafio', label: '📸 Desafio', pts: '5 pts' },
            { key: 'music',   label: '🎵 Music',   pts: '4 pts' },
            { key: 'games',   label: '🎮 Games',   pts: '2 pts' },
            { key: 'vocab',   label: '📖 Vocab',   pts: '1 pt'  },
        ];

        let th = '<th>Aluno</th><th>🖥️ Aula<span class="rp-badge-pts">20 pts</span></th>';
        cols.forEach(c => { th += `<th>${c.label}<span class="rp-badge-pts">${c.pts}</span></th>`; });

        let rows = '';
        students.forEach(s => {
            const jidSafe = s.jid.replace(/[@.]/g, '_');
            let cells = `<td>${s.name}</td>`;

            // Toggle de presença
            const chk = s.class_attended ? 'checked' : '';
            cells += `<td>
                <label class="rp-toggle">
                    <input type="checkbox" id="att_${jidSafe}" ${chk}
                           onchange="rpToggle('${s.jid}', this.checked)">
                    <span class="rp-slider"></span>
                </label>
            </td>`;

            // Campos numéricos
            cols.forEach(c => {
                cells += `<td>
                    <input type="number" min="0" class="rp-num"
                           value="${s[c.key] ?? 0}"
                           onchange="rpEdit('${s.jid}', '${c.key}', this.value)">
                </td>`;
            });

            rows += `<tr>${cells}</tr>`;
        });

        document.getElementById('scoreEditorContainer').innerHTML =
            `<table><thead><tr>${th}</tr></thead><tbody>${rows}</tbody></table>`;
    }

    window.rpToggle = function(jid, attended) {
        fetch('mentoria_score_edit_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_attendance', member_jid: jid, attended: attended })
        }).then(r => r.json()).then(d => { if (!d.success) alert('Erro: ' + d.error); });
    };

    window.rpEdit = function(jid, type, value) {
        fetch('mentoria_score_edit_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'edit_activity', member_jid: jid, type: type, value: parseInt(value) || 0 })
        }).then(r => r.json()).then(d => { if (!d.success) alert('Erro: ' + d.error); });
    };

    // Carrega assim que a aba for ativada (não só no DOMContentLoaded,
    // pois a aba pode ser carregada após o evento disparar)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadScoreEditor);
    } else {
        loadScoreEditor();
    }
})();
</script>
