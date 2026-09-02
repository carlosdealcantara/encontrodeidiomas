<?php
/**
 * Tab E-book: visualização e gestão dos áudios das palavras do e-book.
 * Inclusa em mentoria.php como uma das abas do Hub da Mentoria.
 */
?>
<style>
    .ebook-progress-wrap { background: var(--card-bg); border-radius: 12px; padding: 24px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05); }
    .ebook-progress-bar-track { background: rgba(255,255,255,0.07); border-radius: 999px; height: 12px; overflow: hidden; margin: 12px 0 6px; }
    .ebook-progress-bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #38bdf8, #818cf8); transition: width 0.8s ease; }
    .ebook-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
    .ebook-stat-card { background: var(--card-bg); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 20px; text-align: center; }
    .ebook-stat-card .num { font-size: 2rem; font-weight: 700; color: var(--accent-blue); }
    .ebook-stat-card .lbl { font-size: 0.85rem; color: var(--text-dim); margin-top: 4px; }
    .ebook-filters { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; }
    .ebook-filter-btn { padding: 7px 18px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.12); background: transparent; color: var(--text-dim); cursor: pointer; font-size: 0.88rem; transition: 0.2s; }
    .ebook-filter-btn.active, .ebook-filter-btn:hover { background: var(--accent-blue); color: #fff; border-color: var(--accent-blue); }
    .ebook-search { background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: var(--text-main); padding: 8px 14px; font-size: 0.9rem; min-width: 180px; }
    .ebook-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
    .ebook-word-card { background: var(--card-bg); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; padding: 16px 14px; text-align: center; position: relative; transition: border-color 0.2s; }
    .ebook-word-card.has-audio { border-color: rgba(56, 189, 248, 0.3); }
    .ebook-word-card.has-audio:hover { border-color: rgba(56, 189, 248, 0.7); }
    .ebook-word-card .word-num { font-size: 1.1rem; font-weight: 700; color: var(--white); }
    .ebook-word-card .word-title { font-size: 0.78rem; color: var(--text-dim); margin-top: 4px; word-break: break-word; min-height: 18px; }
    .ebook-word-card .word-status { margin-top: 8px; font-size: 0.75rem; }
    .ebook-word-card .btn-edit { margin-top: 10px; padding: 5px 12px; border-radius: 6px; border: none; background: rgba(56,189,248,0.15); color: var(--accent-blue); cursor: pointer; font-size: 0.78rem; transition: 0.2s; }
    .ebook-word-card .btn-edit:hover { background: var(--accent-blue); color: #fff; }
    .ebook-word-card .btn-play { margin-top: 6px; padding: 4px 10px; border-radius: 6px; border: none; background: rgba(16,185,129,0.15); color: var(--success); cursor: pointer; font-size: 0.78rem; transition: 0.2s; }
    .ebook-word-card .btn-play:hover { background: var(--success); color: #fff; }
    .ebook-badge-gravado { background: rgba(16,185,129,0.15); color: var(--success); padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: bold; }
    .ebook-badge-pendente { background: rgba(148,163,184,0.12); color: var(--text-dim); padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; }
    .ebook-badge-ativo { background: rgba(129,140,248,0.2); color: #818cf8; padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: bold; }
    #ebookInlinePlayer { width: 100%; margin-top: 8px; }
    .ebook-no-results { grid-column: 1/-1; color: var(--text-dim); text-align: center; padding: 30px 0; }
</style>

<?php
// ---- Dados ----
$totalWords = 1000; // Total esperado de palavras no e-book

$stmtEbook = $conn->query("SELECT * FROM ebook_palavras ORDER BY numero ASC");
$ebookPalavras = $stmtEbook->fetchAll(PDO::FETCH_ASSOC);

$gravadas = count($ebookPalavras);
$ativas   = count(array_filter($ebookPalavras, fn($p) => $p['ativo'] == 1));
$pct      = $totalWords > 0 ? round(($gravadas / $totalWords) * 100, 1) : 0;

// Indexar por número para consulta rápida
$byNum = [];
foreach ($ebookPalavras as $p) {
    $byNum[$p['numero']] = $p;
}
?>

<div class="header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;">
    <div>
        <h2><i class="fas fa-book-open"></i> E-book — Palavras em Áudio</h2>
        <p style="color:var(--text-dim);">Catalogue os áudios das palavras do e-book via comando <code>!wordN</code> no WhatsApp.</p>
    </div>
</div>

<!-- Estatísticas -->
<div class="ebook-stats-grid">
    <div class="ebook-stat-card">
        <div class="num"><?= $gravadas ?></div>
        <div class="lbl">Palavras gravadas</div>
    </div>
    <div class="ebook-stat-card">
        <div class="num"><?= $ativas ?></div>
        <div class="lbl">Habilitadas p/ revisão</div>
    </div>
    <div class="ebook-stat-card">
        <div class="num"><?= $totalWords - $gravadas ?></div>
        <div class="lbl">Faltando gravar</div>
    </div>
</div>

<!-- Barra de progresso -->
<div class="ebook-progress-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <strong>Progresso do e-book</strong>
        <span style="color:var(--accent-blue);font-weight:700;"><?= $pct ?>% (<?= $gravadas ?> / <?= $totalWords ?>)</span>
    </div>
    <div class="ebook-progress-bar-track">
        <div class="ebook-progress-bar-fill" style="width:<?= $pct ?>%;"></div>
    </div>
    <small style="color:var(--text-dim);">Use <code>!wordN</code> (ex: <code>!word1001</code>) respondendo a um áudio no WhatsApp para registrar a palavra.</small>
</div>

<!-- Filtros e busca -->
<div class="ebook-filters">
    <button class="ebook-filter-btn active" id="ebookFilterAll" onclick="setEbookFilter('all',this)">Todas (<?= $totalWords ?>)</button>
    <button class="ebook-filter-btn" id="ebookFilterGravadas" onclick="setEbookFilter('gravadas',this)">✅ Gravadas (<?= $gravadas ?>)</button>
    <button class="ebook-filter-btn" id="ebookFilterPendentes" onclick="setEbookFilter('pendentes',this)">⬜ Pendentes (<?= $totalWords - $gravadas ?>)</button>
    <button class="ebook-filter-btn" id="ebookFilterAtivas" onclick="setEbookFilter('ativas',this)">📤 P/ Revisão (<?= $ativas ?>)</button>
    <input type="number" class="ebook-search" id="ebookSearchInput" placeholder="🔍 Buscar nº..." oninput="filterEbookCards()" min="1">
</div>

<!-- Grid de palavras -->
<div class="ebook-grid" id="ebookGrid">
<?php
$numMin = 1001;
$numMax = $numMin + $totalWords - 1;
for ($n = $numMin; $n <= $numMax; $n++):
    $p = $byNum[$n] ?? null;
    $hasAudio = !empty($p);
    $isAtivo  = $hasAudio && $p['ativo'] == 1;
    $titulo   = $hasAudio ? htmlspecialchars($p['titulo'] ?? '') : '';
?>
    <div class="ebook-word-card <?= $hasAudio ? 'has-audio' : '' ?>"
         data-num="<?= $n ?>"
         data-has-audio="<?= $hasAudio ? '1' : '0' ?>"
         data-ativo="<?= $isAtivo ? '1' : '0' ?>">
        <div class="word-num">#<?= $n ?></div>
        <div class="word-title"><?= $titulo ?></div>
        <div class="word-status">
            <?php if ($isAtivo): ?>
                <span class="ebook-badge-ativo">📤 Revisão</span>
            <?php elseif ($hasAudio): ?>
                <span class="ebook-badge-gravado">✅ Gravado</span>
            <?php else: ?>
                <span class="ebook-badge-pendente">⬜ Pendente</span>
            <?php endif; ?>
        </div>
        <?php if ($hasAudio): ?>
            <?php $json = json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            <button class="btn-play" onclick="playEbookAudio(<?= $json ?>)"><i class="fas fa-play"></i> Ouvir</button>
            <button class="btn-edit" onclick="openEbookModal(<?= $json ?>)"><i class="fas fa-edit"></i> Editar</button>
        <?php endif; ?>
    </div>
<?php endfor; ?>
</div>

<!-- Player inline flutuante -->
<div id="ebookPlayerBar" style="display:none;position:fixed;bottom:0;left:0;right:0;background:#1e293b;border-top:1px solid rgba(255,255,255,0.1);padding:12px 24px;z-index:9999;display:none;align-items:center;gap:16px;">
    <span id="ebookPlayerLabel" style="font-weight:600;color:var(--accent-blue);min-width:80px;"></span>
    <audio id="ebookInlinePlayer" controls style="flex:1;height:36px;"></audio>
    <button onclick="closeEbookPlayer()" style="background:none;border:none;color:var(--text-dim);font-size:1.2rem;cursor:pointer;">✕</button>
</div>

<!-- Modal de edição -->
<div id="ebookModal" class="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:10000;align-items:center;justify-content:center;">
    <div class="modal-content" style="background:var(--card-bg);border-radius:16px;padding:32px;max-width:520px;width:100%;position:relative;border:1px solid rgba(255,255,255,0.08);">
        <h2 id="ebookModalTitle" style="margin-bottom:20px;"><i class="fas fa-book-open"></i> Editar Palavra</h2>
        <form method="POST">
            <input type="hidden" name="action_ebook" value="save">
            <input type="hidden" name="tab" value="ebook">
            <input type="hidden" name="ebook_id" id="formEbookId" value="">
            <input type="hidden" name="ebook_numero" id="formEbookNumero" value="">

            <div class="form-group">
                <label>Título / Palavra</label>
                <input type="text" name="titulo" id="ebookTituloInput"
                       class="input-modern"
                       style="width:100%;padding:10px;margin-top:5px;background:var(--input-bg);border:1px solid rgba(255,255,255,0.1);color:var(--white);border-radius:8px;"
                       placeholder="Ex: serendipity">
                <small style="color:var(--text-dim);">Apenas para organização no painel.</small>
            </div>

            <div class="form-group" style="margin-top:15px;">
                <label>Descrição / Anotação (Opcional)</label>
                <textarea name="descricao" id="ebookDescricaoInput" rows="3"
                          class="input-modern"
                          style="width:100%;padding:10px;margin-top:5px;background:var(--input-bg);border:1px solid rgba(255,255,255,0.1);color:var(--white);border-radius:8px;"
                          placeholder="Ex: verbo irregular / expressão idiomática..."></textarea>
            </div>

            <div style="margin-top:12px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="ativo" id="ebookAtivoCheck" value="1" style="width:18px;height:18px;">
                    <span>📤 Habilitar para revisão aleatória</span>
                </label>
            </div>

            <div id="ebookModalPlayerWrap" style="margin-top:16px;display:none;">
                <label style="color:var(--text-dim);font-size:0.85rem;">🎧 Ouvir áudio:</label>
                <audio id="ebookModalAudio" controls style="width:100%;margin-top:6px;"></audio>
            </div>

            <div style="display:flex;gap:10px;margin-top:24px;">
                <button type="submit" class="btn btn-success" style="flex:1;padding:12px;font-weight:bold;">
                    <i class="fas fa-save"></i> Salvar
                </button>
                <button type="button" class="btn btn-danger"
                        style="flex:1;padding:12px;font-weight:bold;background:var(--danger);border:none;"
                        onclick="closeEbookModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
let _currentEbookFilter = 'all';

function setEbookFilter(filter, btn) {
    _currentEbookFilter = filter;
    document.querySelectorAll('.ebook-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterEbookCards();
}

function filterEbookCards() {
    const search = parseInt(document.getElementById('ebookSearchInput').value || '0');
    document.querySelectorAll('#ebookGrid .ebook-word-card').forEach(card => {
        const num = parseInt(card.dataset.num);
        const hasAudio = card.dataset.hasAudio === '1';
        const isAtivo  = card.dataset.ativo === '1';

        let show = true;
        if (_currentEbookFilter === 'gravadas')  show = hasAudio;
        if (_currentEbookFilter === 'pendentes') show = !hasAudio;
        if (_currentEbookFilter === 'ativas')    show = isAtivo;
        if (search > 0) show = show && (num === search);

        card.style.display = show ? '' : 'none';
    });
}

function openEbookModal(p) {
    document.getElementById('formEbookId').value      = p.id;
    document.getElementById('formEbookNumero').value  = p.numero;
    document.getElementById('ebookTituloInput').value = p.titulo || '';
    document.getElementById('ebookDescricaoInput').value = p.descricao || '';
    document.getElementById('ebookAtivoCheck').checked   = p.ativo == 1;
    document.getElementById('ebookModalTitle').innerHTML = '<i class="fas fa-book-open"></i> Editar Palavra #' + p.numero;

    // Player de áudio no modal
    const wrap = document.getElementById('ebookModalPlayerWrap');
    const player = document.getElementById('ebookModalAudio');
    if (p.audio_path) {
        player.src = '/' + p.audio_path;
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
    }

    document.getElementById('ebookModal').style.display = 'flex';
}

function closeEbookModal() {
    document.getElementById('ebookModal').style.display = 'none';
    document.getElementById('ebookModalAudio').pause();
}

function playEbookAudio(p) {
    const bar    = document.getElementById('ebookPlayerBar');
    const label  = document.getElementById('ebookPlayerLabel');
    const player = document.getElementById('ebookInlinePlayer');

    label.textContent = '#' + p.numero + (p.titulo ? ' – ' + p.titulo : '');
    player.src = '/' + p.audio_path;
    player.play();
    bar.style.display = 'flex';
}

function closeEbookPlayer() {
    const bar    = document.getElementById('ebookPlayerBar');
    const player = document.getElementById('ebookInlinePlayer');
    player.pause();
    bar.style.display = 'none';
}
</script>
