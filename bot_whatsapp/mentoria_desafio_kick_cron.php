<?php
/**
 * CRON: Auto-kick do Desafio — Multi-idioma
 * Frequência: 1x/dia, todos os dias, às 00:00 BRT
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

// Analisamos a atividade de ontem, a menos que estejamos testando hoje
if (isset($_GET['test_hoje']) && $_GET['test_hoje'] == '1') {
    $ontem = date('Y-m-d');
} else {
    $ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');
}

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

$totalKicked = 0;

foreach ($langs as $lang) {
    echo "\n🌐 Processando idioma: [{$lang}]\n";

    // Anti-duplicidade por idioma
    $logType = 'desafio_kick_run_' . $lang;
    $check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = ? AND data_execucao = ?");
    $check->execute([$logType, $ontem]);
    if ($check->rowCount() > 0 && !isset($_GET['force'])) {
        echo "  ⏭️ Verificação de kick já rodou para [{$lang}] na data $ontem. Use &force=1 para forçar.\n";
        continue;
    }

    $config     = getMentoriaConfig($lang);
    $desafioJid = $config['groups']['desafio']['jid'] ?? null;
    $adminJid   = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";

    // Defaults de template por idioma
    $defaultTemplates = [
        'es' => "⚠️ {name} fue removido/a por no haber publicado la actividad diaria.",
        'en' => "⚠️ {name} has been removed for missing the daily activity.",
    ];
    $defaultTpl = $defaultTemplates[$lang] ?? $defaultTemplates['en'];
    $template = $config['templates']['kick_desafio'] ?? $defaultTpl;

    if (!$desafioJid) {
        echo "  ⚠️ Grupo do desafio não configurado para o idioma [{$lang}]. Pulando.\n";
        // Registra a execução mesmo sem grupo para evitar reprocessamento infinito
        $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES (?, ?, 'Grupo não configurado')")->execute([$logType, $ontem]);
        continue;
    }

    $members       = fetchGroupMembers($desafioJid);
    $activity      = fetchBaileysActivity($ontem);
    $desafioActivity = $activity[$desafioJid] ?? [];

    $cleanAdminJid = preg_replace('/:\d+@/', '@', $adminJid);
    $kickedCount   = 0;

    foreach ($members as $memberData) {
        $memberJid      = $memberData['id'];
        $cleanMemberJid = preg_replace('/:\d+@/', '@', $memberJid);

        // Ignora admin e o próprio bot
        $isAdmin = !empty($memberData['admin']);
        if ($cleanMemberJid === $cleanAdminJid || $isAdmin) continue;

        // Verifica se mandou IMAGEM no grupo ontem no JSON
        $interagiu = isset($desafioActivity[$memberJid]) && ($desafioActivity[$memberJid]['images_sent'] ?? 0) > 0;

        // Escudo MySQL: cruza com o banco como dupla checagem
        if (!$interagiu) {
            $stmtShield = $conn->prepare("SELECT last_completed_date FROM mentoria_desafio_streaks WHERE member_jid = ?");
            $stmtShield->execute([$memberJid]);
            $rowShield = $stmtShield->fetch(PDO::FETCH_ASSOC);
            if ($rowShield && $rowShield['last_completed_date'] === $ontem) {
                $interagiu = true; // Salvo pelo escudo! O banco tem o registro correto.
            }
        }

        if (!$interagiu) {
            // Monta a mensagem de kick
            $numero = explode('@', $memberJid)[0];
            $msg    = str_replace(['@{name}', '{name}'], ["@".$numero, $numero], $template);

            enviarWhatsAppMention($desafioJid, $msg, [$memberJid]);

            // Pausa de 5 segundos para garantir a entrega e visualização antes do kick
            sleep(5);

            // Remove do grupo
            $resRemove = removerDoGrupo($desafioJid, [$memberJid]);

            if (($resRemove['success'] ?? false) || ($resRemove['httpCode'] ?? 0) === 200) {
                $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes) VALUES ('desafio_kick', ?, ?, ?)")
                     ->execute([$ontem, $memberJid, $lang]);

                // Reset streak
                try {
                    $conn->prepare("UPDATE mentoria_desafio_streaks SET current_streak = 0 WHERE member_jid = ?")
                         ->execute([$memberJid]);
                } catch (Exception $e) {}

                $kickedCount++;
                $totalKicked++;
                echo "  🚪 Kicked [{$lang}]: $memberJid\n";
            }
        }
    }

    // Marca que a verificação rodou para este idioma
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES (?, ?, ?)")
         ->execute([$logType, $ontem, $kickedCount . ' removidos']);
    echo "  ✅ Kick [{$lang}] concluído: $kickedCount removidos.\n";
}

echo "\n🏁 Kick do Desafio Multi-idioma concluído! Total removidos: $totalKicked.\n";
