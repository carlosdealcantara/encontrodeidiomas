<?php
session_start();
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die("ID inválido.");
}

$msg = '';
$error = '';

// Se o form foi enviado com a URL (caso estava vazio)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url_curta'])) {
    $url = trim($_POST['url_curta']);
    if ($url) {
        $stmt = $conn->prepare("UPDATE odysee_publish_queue SET odysee_url = ? WHERE id = ?");
        $stmt->execute([$url, $id]);
        
        $stmt2 = $conn->prepare("SELECT language_id FROM odysee_publish_queue WHERE id = ?");
        $stmt2->execute([$id]);
        $langId = $stmt2->fetchColumn();
        
        if ($langId) {
            $stmt3 = $conn->prepare("UPDATE meetup_replays SET link = ? WHERE language_id = ? AND (link IS NULL OR link = '') ORDER BY semana DESC LIMIT 1");
            $stmt3->execute([$url, $langId]);
        }
    }
}

// Busca os dados da tarefa
$stmt = $conn->prepare("
    SELECT q.*, l.name as language_name, l.flag_emoji, l.odysee_channel_name 
    FROM odysee_publish_queue q
    LEFT JOIN languages l ON q.language_id = l.id
    WHERE q.id = ?
");
$stmt->execute([$id]);
$tarefa = $stmt->fetch();

if (!$tarefa) {
    die("Tarefa não encontrada.");
}

if (empty($tarefa['odysee_url'])) {
    // Exibir form pedindo a URL curta
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Informar URL - Encontro de Idiomas</title>
        <style>
            body { background: #0f172a; color: white; font-family: sans-serif; padding: 40px; }
            .card { background: #1e293b; padding: 30px; border-radius: 12px; max-width: 500px; margin: 0 auto; }
            input { width: 100%; padding: 10px; margin: 15px 0; background: #334155; border: 1px solid #475569; color: white; border-radius: 6px; box-sizing: border-box; }
            button { background: #25D366; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>Falta a URL do Odysee</h2>
            <p>O robô não salvou a URL para a gravação <strong><?= htmlspecialchars($tarefa['titulo_final']) ?></strong>. Informe a URL encurtada (ex: is.gd/algo) para continuarmos com o disparo:</p>
            <form method="POST">
                <input type="text" name="url_curta" placeholder="https://is.gd/..." required autofocus>
                <button type="submit">Salvar e Continuar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Processo de disparo real
if (isset($_GET['confirm']) && $_GET['confirm'] == 1) {
    // MODO DE CONTENÇÃO: verifica flag no banco
    $odysee_modo = getSystemSetting($conn, 'wpp_odysee_ativo', '0');
    if ($odysee_modo === '1') $odysee_modo = 'full';

    if ($odysee_modo === '0') {
        $error = "⛔ Modo de Contenção Ativo. O disparo do Odysee está desligado temporariamente para evitar banimento. Ajuste no painel de Modo Contenção.";
    } else {
        try {
        // 1. Template
        $stmtT = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'odysee_whatsapp_template'");
        $stmtT->execute();
        $rowT = $stmtT->fetch();
        $template = $rowT ? $rowT['setting_value'] : "🎬 *Replay:* {bandeira} {titulo}\n\n🔗 {link}";
        
        $titulo = $tarefa['titulo_final'] ?: 'Sem Título';
        $link = $tarefa['odysee_url'];
        $idioma = $tarefa['language_name'];
        $bandeira = $tarefa['flag_emoji'] ?: '';
        
        $mensagem = str_replace(
            ['{titulo}', '{link}', '{idioma}', '{bandeira}'],
            [$titulo, $link, $idioma, $bandeira],
            $template
        );
        
        // Função para expandir URL curta (resolve redirecionamentos)
        function expandUrl($url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            if (preg_match('/^Location:\s*(.+?)$/mi', $response, $matches)) {
                return trim($matches[1]);
            }
            return $url;
        }

        $urlOriginal = expandUrl($link);

        // 2. Link Preview Data
        $linkPreview = [
            "title" => $titulo,
            "body" => "Disponível agora no Odysee",
            "url" => $urlOriginal // Passamos a URL original para o Baileys conseguir ler a thumbnail!
        ];
        // Nota: Não usamos $tarefa['last_screenshot'] como thumbnail porque ele é um print inteiro da tela do Odysee!
        // O Baileys vai automaticamente ler as tags OpenGraph da URL original e baixar a thumbnail oficial do vídeo.
        
        // 3. Grupos Alvo
        if ($odysee_modo === 'hosts') {
            $grupos = ['120363164732845564@g.us'];
        } else {
            $stmtG = $conn->prepare("
                SELECT group_id FROM meetup_whatsapp_groups 
                WHERE ativo = 1 AND (categoria = 'multi_idioma' OR (categoria = 'especifico' AND language_id = ?))
            ");
            $stmtG->execute([$tarefa['language_id']]);
            $grupos = $stmtG->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $enviados = 0;
        foreach ($grupos as $gid) {
            $payload = [
                "to" => $gid,
                "message" => $mensagem,
                "source" => "odysee_pipeline_manual",
                "linkPreview" => $linkPreview
            ];
            // Usar API Baileys interna
            $url = 'http://136.248.92.126:3000/send';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: SenhaMeetups2026", "Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $res = curl_exec($ch);
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
                $enviados++;
            }
            curl_close($ch);
        }
        
        // 4. Webhook Hosts
        $wh = curl_init('https://dev.encontrodeidiomas.com.br/ajax/webhook_odysee_success.php');
        curl_setopt($wh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($wh, CURLOPT_TIMEOUT, 10);
        curl_setopt($wh, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($wh, CURLOPT_POSTFIELDS, json_encode([
            "apikey" => "SenhaMeetups2026",
            "lang_id" => $tarefa['language_id']
        ]));
        curl_exec($wh);
        curl_close($wh);
        
        header("Location: odysee.php?tab=fila&msg=" . urlencode("Disparado com sucesso para $enviados grupos!"));
        exit;
        
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Disparo - Encontro de Idiomas</title>
    <style>
        body { background: #0f172a; color: white; font-family: sans-serif; padding: 40px; }
        .card { background: #1e293b; padding: 30px; border-radius: 12px; max-width: 600px; margin: 0 auto; }
        .btn { padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; border: none; text-decoration: none; display: inline-block; }
        .btn-green { background: #25D366; color: white; }
        .btn-gray { background: #475569; color: white; margin-left: 10px; }
        pre { background: #334155; padding: 15px; border-radius: 8px; white-space: pre-wrap; font-family: monospace; }
        .thumb-preview { max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 20px; border: 2px solid #475569; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Confirmar Disparo Manual</h2>
        <?php
            $modo_atual = getSystemSetting($conn, 'wpp_odysee_ativo', '0');
            if ($modo_atual === '1') $modo_atual = 'full';
            
            if ($modo_atual === 'hosts'):
        ?>
            <p style="color: #f59e0b;"><i class="fas fa-shield-alt"></i> <strong>Modo Apenas Hosts:</strong> Você está prestes a enviar o vídeo do <strong><?= htmlspecialchars($tarefa['language_name']) ?></strong> <u>apenas para o grupo interno dos Hosts</u>.</p>
        <?php elseif ($modo_atual === 'full'): ?>
            <p style="color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> <strong>Modo Disparo Total:</strong> Você está prestes a disparar a mensagem do <strong><?= htmlspecialchars($tarefa['language_name']) ?></strong> para TODOS os grupos deste idioma da comunidade.</p>
        <?php else: ?>
            <p>O envio está desligado no modo de contenção.</p>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background: #ef4444; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <p style="color: #94a3b8; font-size: 0.9em; margin-bottom: 20px;">
            A miniatura oficial do vídeo será gerada automaticamente pelo WhatsApp com base no link do Odysee.
        </p>

        <div style="margin-top: 30px;">
            <a href="?id=<?= $id ?>&confirm=1" class="btn btn-green">Sim, Enviar Agora</a>
            <a href="odysee.php?tab=fila" class="btn btn-gray">Cancelar</a>
        </div>
    </div>
</body>
</html>
