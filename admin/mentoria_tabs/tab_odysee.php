<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 5px;">Odysee Pipeline (Mentoria)</h2>
        <p style="color: var(--text-dim);">Fila de vídeos da mentoria sendo publicados como "Não-listados" e enviados para o grupo Our Meetups.</p>
    </div>
</div>

<?php
// Ações rápidas
if (isset($_GET['retry']) && is_numeric($_GET['retry'])) {
    $id = (int)$_GET['retry'];
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status = 'pending', retry_count = 0 WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>window.location.href='mentoria.php?tab=odysee';</script>";
    exit;
}
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status = 'error', error_message = 'Cancelado pelo Admin' WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>window.location.href='mentoria.php?tab=odysee';</script>";
    exit;
}

$stmt = $conn->query("
    SELECT *
    FROM mentoria_odysee_queue
    ORDER BY id DESC LIMIT 100
");
$queue = $stmt->fetchAll();

// Diagnóstico (screenshot mais recente)
$screenshots = [];
$active = $conn->query("
    SELECT id, titulo_final, status, last_screenshot, last_screenshot_time
    FROM mentoria_odysee_queue
    WHERE status = 'processing' AND last_screenshot IS NOT NULL
    ORDER BY last_screenshot_time DESC LIMIT 1
")->fetchAll();

if (!empty($active)) {
    $screenshots = $active;
} else {
    $screenshots = $conn->query("
        SELECT id, titulo_final, status, last_screenshot, last_screenshot_time
        FROM mentoria_odysee_queue
        WHERE last_screenshot IS NOT NULL
        ORDER BY last_screenshot_time DESC LIMIT 1
    ")->fetchAll();
}
?>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 20px;">
    <!-- Tabela da Fila -->
    <div class="card" style="background: var(--card-bg); border-radius: 16px; padding: 25px;">
        <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-list-ul"></i> Fila de Processamento</h3>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <th style="padding: 12px; color: var(--text-dim); font-weight: 600;">ID</th>
                        <th style="padding: 12px; color: var(--text-dim); font-weight: 600;">Vídeo</th>
                        <th style="padding: 12px; color: var(--text-dim); font-weight: 600;">Status</th>
                        <th style="padding: 12px; color: var(--text-dim); font-weight: 600;">Link / Msg Wpp</th>
                        <th style="padding: 12px; color: var(--text-dim); font-weight: 600;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($queue)): ?>
                        <tr><td colspan="5" style="padding: 20px; text-align: center; color: var(--text-dim);">Fila vazia. O worker verificará o Drive a cada 60s.</td></tr>
                    <?php else: ?>
                        <?php foreach($queue as $item): 
                            $statusColor = match($item['status']) {
                                'pending' => 'var(--text-dim)',
                                'processing' => 'var(--accent-blue)',
                                'done' => 'var(--success)',
                                'error' => 'var(--danger)',
                                default => 'var(--text-dim)'
                            };
                        ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 12px;"><?= $item['id'] ?></td>
                            <td style="padding: 12px;">
                                <strong><?= htmlspecialchars($item['titulo_final'] ?: $item['drive_file_name']) ?></strong>
                                <br>
                                <small style="color: var(--text-dim);"><?= htmlspecialchars($item['odysee_slug']) ?></small>
                                <?php if($item['error_message']): ?>
                                    <br><small style="color: var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($item['error_message']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; background: <?= $statusColor ?>20; color: <?= $statusColor ?>; border: 1px solid <?= $statusColor ?>50;">
                                    <?= strtoupper($item['status']) ?>
                                    <?php if($item['retry_count'] > 0) echo "(TENTATIVA " . ($item['retry_count']+1) . ")"; ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <?php if($item['odysee_url']): ?>
                                    <a href="<?= htmlspecialchars($item['odysee_url']) ?>" target="_blank" style="color: var(--accent-blue); text-decoration: none; display: block; margin-bottom: 8px;">
                                        <i class="fa-solid fa-link"></i> Odysee Link
                                    </a>
                                <?php endif; ?>
                                <?php if($item['whatsapp_message']): ?>
                                    <button onclick="copiarWpp('msg_wpp_<?= $item['id'] ?>')" style="background: #25D366; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                        <i class="fa-brands fa-whatsapp"></i> Copiar Msg
                                    </button>
                                    <textarea id="msg_wpp_<?= $item['id'] ?>" style="display: none;"><?= htmlspecialchars($item['whatsapp_message']) ?></textarea>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php if($item['status'] == 'error'): ?>
                                    <a href="mentoria.php?tab=odysee&retry=<?= $item['id'] ?>" style="color: var(--accent-blue); text-decoration: none; margin-right: 10px;" title="Tentar Novamente">
                                        <i class="fa-solid fa-rotate-right"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if(in_array($item['status'], ['pending', 'error', 'processing'])): ?>
                                    <a href="mentoria.php?tab=odysee&cancel=<?= $item['id'] ?>" style="color: var(--danger); text-decoration: none;" title="Cancelar" onclick="return confirm('Certeza que deseja cancelar esta tarefa?')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monitor Automático / Diagnóstico -->
    <div class="card" style="background: var(--card-bg); border-radius: 16px; padding: 25px;">
        <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-tv"></i> Visão do Bot</h3>
        
        <?php if(!empty($screenshots)): ?>
            <?php foreach($screenshots as $scr): ?>
                <div style="margin-bottom: 20px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <strong>Tarefa #<?= $scr['id'] ?></strong>
                        <span style="color: var(--text-dim); font-size: 0.85rem;"><?= $scr['last_screenshot_time'] ?></span>
                    </div>
                    <div style="color: var(--accent-blue); margin-bottom: 10px; font-size: 0.9rem;">
                        <?= htmlspecialchars($scr['titulo_final']) ?>
                    </div>
                    <img src="data:image/png;base64,<?= $scr['last_screenshot'] ?>" alt="Screenshot do processo" onclick="openScreenshotModal(this.src)" style="width: 100%; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); cursor: pointer; transition: transform 0.2s;">
                </div>
            <?php endforeach; ?>
            <p style="color: var(--text-dim); font-size: 0.85rem; text-align: center;">Atualize a página para ver o frame mais recente do container.</p>
        <?php else: ?>
            <div style="text-align: center; color: var(--text-dim); padding: 40px 0;">
                <i class="fa-solid fa-camera" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                <p>Nenhuma imagem recente do worker da mentoria capturada.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copiarWpp(elementId) {
    var copyText = document.getElementById(elementId);
    copyText.style.display = "block";
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");
    copyText.style.display = "none";
    alert("Mensagem do WhatsApp copiada para a área de transferência!");
}

function openScreenshotModal(src) {
    document.getElementById('screenshotModalImg').src = src;
    document.getElementById('screenshotModal').style.display = 'flex';
}
function closeScreenshotModal() {
    document.getElementById('screenshotModal').style.display = 'none';
}
</script>

<div id="screenshotModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; justify-content: center; align-items: center; padding: 20px;" onclick="closeScreenshotModal()">
    <span style="position: absolute; top: 20px; right: 40px; color: white; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
    <img id="screenshotModalImg" style="max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
</div>
