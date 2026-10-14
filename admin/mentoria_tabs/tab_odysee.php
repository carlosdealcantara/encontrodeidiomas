<?php
// Salva template da mensagem WhatsApp do pipeline Odysee por idioma
$wppSettingKey = ($current_lang === 'en') ? 'mentoria_odysee_wpp_template' : 'mentoria_odysee_wpp_template_' . $current_lang;
if (isset($_POST['save_odysee_wpp_template'])) {
    updateSetting($wppSettingKey, $_POST['odysee_wpp_template'] ?? '');
    echo "<script>window.location.href='mentoria.php?tab=odysee&lang=" . urlencode($current_lang) . "&msg=Template+salvo+com+sucesso!';</script>";
    exit;
}
$odysee_wpp_template = getSetting($wppSettingKey, getSetting('mentoria_odysee_wpp_template', "🎓 *{titulo}*\n\n🔗 {url}"));
?>

<div class="card" style="background: var(--card-bg); border-radius: 16px; padding: 25px; margin-bottom: 25px; border: 1px solid rgba(56,189,248,0.2);">
    <h3 style="color: var(--accent-blue); margin-bottom: 6px;"><i class="fa-brands fa-whatsapp"></i> Mensagem WhatsApp — Pipeline Odysee (<?= htmlspecialchars($langName) ?> <?= $langFlag ?>)</h3>
    <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 18px;">
        Texto enviado ao grupo <strong>Our Classes</strong> após cada publicação no Odysee.<br>
        Variáveis disponíveis: <code style="background:rgba(255,255,255,0.08); padding:2px 6px; border-radius:4px;">{titulo}</code> — título do vídeo &nbsp;|&nbsp;
        <code style="background:rgba(255,255,255,0.08); padding:2px 6px; border-radius:4px;">{url}</code> — link do Odysee &nbsp;|&nbsp;
        <code style="background:rgba(255,255,255,0.08); padding:2px 6px; border-radius:4px;">{bandeira}</code> — bandeira do idioma (ex: 🇺🇸, 🇪🇸)
    </p>
    <form method="POST">
        <input type="hidden" name="tab" value="odysee">
        <textarea name="odysee_wpp_template" rows="4" style="width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; font-family: 'Outfit', sans-serif; font-size: 0.95rem; resize: vertical;"><?= htmlspecialchars($odysee_wpp_template) ?></textarea>
        <div style="margin-top: 12px; display: flex; align-items: center; gap: 15px;">
            <button type="submit" name="save_odysee_wpp_template" style="background: var(--accent-blue); color: #0f172a; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-save"></i> Salvar Template (<?= htmlspecialchars($langName) ?>)
            </button>
            <span style="color: var(--text-dim); font-size: 0.85rem;">💡 A alteração reflete na próxima publicação processada pelo worker.</span>
        </div>
    </form>
</div>

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 5px;">Odysee Pipeline (Mentoria - <?= htmlspecialchars($langName) ?> <?= $langFlag ?>)</h2>
        <p style="color: var(--text-dim);">Fila de vídeos da mentoria sendo publicados como "Não-listados" e enviados para o grupo Our Meetups.</p>
    </div>
</div>


<?php
// Ações rápidas
if (isset($_GET['retry']) && is_numeric($_GET['retry'])) {
    $id = (int)$_GET['retry'];
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status = 'pending', retry_count = 0 WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>window.location.href='mentoria.php?tab=odysee&lang=" . urlencode($current_lang) . "';</script>";
    exit;
}
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status = 'error', error_message = 'Cancelado pelo Admin' WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>window.location.href='mentoria.php?tab=odysee&lang=" . urlencode($current_lang) . "';</script>";
    exit;
}
if (isset($_GET['send_wpp']) && is_numeric($_GET['send_wpp'])) {
    $id = (int)$_GET['send_wpp'];
    try {
        $stmtWpp = $conn->prepare("SELECT * FROM mentoria_odysee_queue WHERE id = ?");
        $stmtWpp->execute([$id]);
        $task = $stmtWpp->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new Exception("Tarefa não encontrada.");
        }

        // Obtém o grupo Our Classes configurado para o idioma
        $confMentoria = getMentoriaConfig($task['lang_id'] ?? $current_lang);
        $targetJid = $confMentoria['groups']['our_classes']['jid'] ?? '';
        
        if (empty($targetJid)) {
            throw new Exception("Grupo Our Classes não configurado para este idioma no Baileys.");
        }

        $rawMsg = $task['whatsapp_message'] ?? '';
        // Remove prefixo de erro se existir para enviar o texto limpo
        $cleanMsg = preg_replace('/^\[WPP FALHOU[^\]]*\]\s*/i', '', $rawMsg);
        if (empty(trim($cleanMsg))) {
            // Reconstrói a partir do template caso a coluna esteja vazia
            $taskLang = $task['lang_id'] ?? $current_lang;
            $tplKey = ($taskLang === 'en') ? 'mentoria_odysee_wpp_template' : 'mentoria_odysee_wpp_template_' . $taskLang;
            $tpl = getSetting($tplKey, getSetting('mentoria_odysee_wpp_template', "🎓 *{titulo}*\n\n🔗 {url}"));
            $flagEmoji = ($taskLang === 'en') ? '🇺🇸' : (($taskLang === 'es') ? '🇪🇸' : '');
            try {
                $stmtLang = $conn->prepare("SELECT bandeira FROM mentoria_langs WHERE lang_id = ? LIMIT 1");
                $stmtLang->execute([$taskLang]);
                $flagDb = $stmtLang->fetchColumn();
                if (!empty($flagDb)) $flagEmoji = $flagDb;
            } catch (Exception $e) {}

            $cleanMsg = str_replace(
                ['{titulo}', '{url}', '{bandeira}'],
                [$task['titulo_final'] ?: $task['drive_file_name'], $task['odysee_url'], $flagEmoji],
                $tpl
            );
        }

        $linkPreview = null;
        if (!empty($task['odysee_url'])) {
            $linkPreview = [
                'title' => $task['titulo_final'] ?: $task['drive_file_name'],
                'body'  => "Disponível agora no Odysee (Não-listado)",
                'url'   => $task['odysee_url']
            ];
        }

        $resWpp = enviarWhatsApp($targetJid, $cleanMsg, 'mentoria_pipeline_manual', $linkPreview);

        if ($resWpp['success']) {
            // Atualiza a mensagem no banco removendo o erro
            $stmtUp = $conn->prepare("UPDATE mentoria_odysee_queue SET whatsapp_message = ? WHERE id = ?");
            $stmtUp->execute([$cleanMsg, $id]);
            echo "<script>window.location.href='mentoria.php?tab=odysee&lang=" . urlencode($current_lang) . "&msg=" . urlencode("Mensagem disparada com sucesso no grupo Our Classes!") . "';</script>";
            exit;
        } else {
            $errDetail = $resWpp['error'] ?? 'Falha ao comunicar com o servidor Baileys';
            echo "<script>alert('Erro ao disparar WhatsApp: " . addslashes($errDetail) . "'); window.location.href='mentoria.php?tab=odysee&lang=" . urlencode($current_lang) . "';</script>";
            exit;
        }
    } catch (Exception $e) {
        echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "'); window.location.href='mentoria.php?tab=odysee&lang=" . urlencode($current_lang) . "';</script>";
        exit;
    }
}

$stmt = $conn->prepare("
    SELECT *
    FROM mentoria_odysee_queue
    WHERE lang_id = ?
    ORDER BY odysee_slug DESC LIMIT 100
");
$stmt->execute([$current_lang]);
$queue = $stmt->fetchAll();

// Diagnóstico (screenshot mais recente)
$screenshots = [];
$stmtScr = $conn->prepare("
    SELECT id, titulo_final, status, last_screenshot, last_screenshot_time
    FROM mentoria_odysee_queue
    WHERE status = 'processing' AND last_screenshot IS NOT NULL AND lang_id = ?
    ORDER BY last_screenshot_time DESC LIMIT 1
");
$stmtScr->execute([$current_lang]);
$active = $stmtScr->fetchAll();

if (!empty($active)) {
    $screenshots = $active;
} else {
    $stmtScrFallback = $conn->prepare("
        SELECT id, titulo_final, status, last_screenshot, last_screenshot_time
        FROM mentoria_odysee_queue
        WHERE last_screenshot IS NOT NULL 
          AND lang_id = ?
          AND status IN ('processing', 'pending', 'done', 'error')
        ORDER BY last_screenshot_time DESC LIMIT 1
    ");
    $stmtScrFallback->execute([$current_lang]);
    $screenshots = $stmtScrFallback->fetchAll();
}
?>

<style>
    .odysee-grid { display: grid; grid-template-columns: 1fr 400px; gap: 20px; }
    @media (max-width: 900px) {
        .odysee-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="odysee-grid">
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
                                'pending' => 'var(--warning)',
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
                                <?php if($item['whatsapp_message']): 
                                    $wppFalhou = (stripos($item['whatsapp_message'], '[WPP FALHOU') !== false);
                                ?>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <button onclick="copiarWpp('msg_wpp_<?= $item['id'] ?>')" style="background: #25D366; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;" title="Copiar texto da mensagem">
                                            <i class="fa-brands fa-whatsapp"></i> Copiar
                                        </button>
                                        <a href="mentoria.php?tab=odysee&lang=<?= urlencode($current_lang) ?>&send_wpp=<?= $item['id'] ?>" 
                                           onclick="return confirm('Deseja realmente disparar esta mensagem agora para o grupo Our Classes?')"
                                           style="background: <?= $wppFalhou ? '#ef4444' : '#0284c7' ?>; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;"
                                           title="<?= $wppFalhou ? 'O envio automático falhou. Clique para disparar agora!' : 'Disparar ou reenviar mensagem no grupo' ?>">
                                            <i class="fa-solid fa-paper-plane"></i> <?= $wppFalhou ? 'Reenviar Wpp' : 'Enviar Wpp' ?>
                                        </a>
                                    </div>
                                    <textarea id="msg_wpp_<?= $item['id'] ?>" style="display: none;"><?= htmlspecialchars($item['whatsapp_message']) ?></textarea>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php if(in_array($item['status'], ['error', 'done'])): ?>
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
