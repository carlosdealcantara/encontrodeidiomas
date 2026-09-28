<?php
/**
 * CRON: Aviso de Desafio (21h) — Multi-idioma
 * Frequência: 1x/dia, todos os dias, às 21:00 BRT
 * Itera sobre todos os idiomas ativos em mentoria_langs.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$conn = connectDB();
$hoje = date('Y-m-d');

try {
    $conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_auto_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        data_execucao DATE NOT NULL,
        membro_jid VARCHAR(50) NULL,
        detalhes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (tipo, data_execucao)
    )");
} catch (Exception $e) {}

// Busca todos os idiomas ativos
$langs = $conn->query("SELECT lang_id FROM mentoria_langs WHERE ativo = 1 ORDER BY lang_id ASC")->fetchAll(PDO::FETCH_COLUMN);

if (empty($langs)) {
    die("Nenhum idioma ativo encontrado em mentoria_langs.\n");
}

$adminJid = null; // Será lido do config de cada idioma

foreach ($langs as $lang) {
    echo "\n🌐 Processando idioma: [{$lang}]\n";

    // Anti-duplicidade por idioma
    $logType = 'desafio_aviso_' . $lang;
    $check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = ? AND data_execucao = ?");
    $check->execute([$logType, $hoje]);
    if ($check->rowCount() > 0 && !isset($_GET['force'])) {
        echo "  ⏭️ Aviso já enviado hoje para [{$lang}]. Use &force=1 para forçar o reenvio.\n";
        continue;
    }

    $config = getMentoriaConfig($lang);
    $desafioJid = $config['groups']['desafio']['jid'] ?? null;

    if (!$desafioJid) {
        echo "  ⚠️ Grupo do desafio não configurado para o idioma [{$lang}]. Pulando.\n";
        continue;
    }

    $currentAdminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";
    $cleanAdminJid   = preg_replace('/:\d+@/', '@', $currentAdminJid);

    // Defaults de template por idioma
    $defaultTemplates = [
        'es' => "⚠️ *¡Alerta de Desafío!*\n{pendentes}\nEste es un recordatorio amistoso de que aún no has publicado tu actividad diaria. ¡Tienes hasta medianoche! ⏳",
        'en' => "⚠️ *Challenge Alert!*\n{pendentes}\nThis is a friendly reminder that you haven't posted your daily activity yet. You have until midnight! ⏳",
    ];
    $defaultTpl = $defaultTemplates[$lang] ?? $defaultTemplates['en'];
    $template = $config['templates']['aviso_desafio'] ?? $defaultTpl;

    $members       = fetchGroupMembers($desafioJid);
    $activity      = fetchBaileysActivity($hoje);
    $desafioActivity = $activity[$desafioJid] ?? [];

    $pendentes = [];
    $mentions  = [];

    foreach ($members as $memberData) {
        $memberJid      = $memberData['id'];
        $cleanMemberJid = preg_replace('/:\d+@/', '@', $memberJid);

        // Ignora admin e o próprio bot
        $isAdmin = !empty($memberData['admin']);
        if ($cleanMemberJid === $cleanAdminJid || $isAdmin) continue;

        // Verifica se enviou IMAGEM hoje no JSON
        $enviouImagem = isset($desafioActivity[$memberJid]) && ($desafioActivity[$memberJid]['images_sent'] ?? 0) > 0;

        // Escudo MySQL: cruza com o banco como dupla checagem
        if (!$enviouImagem) {
            $stmtShield = $conn->prepare("SELECT last_completed_date FROM mentoria_desafio_streaks WHERE member_jid = ?");
            $stmtShield->execute([$memberJid]);
            $rowShield = $stmtShield->fetch(PDO::FETCH_ASSOC);
            if ($rowShield && $rowShield['last_completed_date'] === $hoje) {
                $enviouImagem = true; // Salvo pelo escudo!
            }
        }

        if (!$enviouImagem) {
            $numero      = explode('@', $memberJid)[0];
            $pendentes[] = "@" . $numero;
            $mentions[]  = $memberJid;
        }
    }

    if (empty($pendentes)) {
        echo "  ℹ️ Nenhum pendente hoje para [{$lang}]. Nenhuma mensagem enviada.\n";
        $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES (?, ?, 'Nenhum pendente')")->execute([$logType, $hoje]);
        continue;
    }

    $listaPendentes = implode(", ", $pendentes);
    $msg            = str_replace('{pendentes}', $listaPendentes, $template);

    // Envia mensagem com menção real
    $result = enviarWhatsAppMention($desafioJid, $msg, $mentions);

    if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
        $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES (?, ?, ?)")
             ->execute([$logType, $hoje, count($pendentes) . ' pendentes avisados']);
        echo "  ✅ Aviso de 21h enviado no grupo Desafio [{$lang}] marcando " . count($pendentes) . " pessoas!\n";
    } else {
        echo "  ❌ Erro ao enviar aviso [{$lang}]: HTTP " . $result['httpCode'] . "\n";
    }
}

echo "\n🏁 Aviso de Desafio Multi-idioma concluído.\n";
