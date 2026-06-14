<?php
/**
 * Worker do Canhão de Disparos WhatsApp
 * Deve ser rodado via cronjob (ex: a cada 1 minuto)
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/whatsapp_helper.php';

// Prevenir múltiplas execuções concorrentes (se o cron for muito rápido)
$lock_file = sys_get_temp_dir() . '/wpp_broadcast_worker.lock';
$lock_fp = fopen($lock_file, "w+");
if (!flock($lock_fp, LOCK_EX | LOCK_NB)) {
    echo "Worker já está em execução.\n";
    exit;
}

$conn = connectDB();

try {
    // Busca o broadcast mais antigo pendente ou em andamento
    $stmt = $conn->query("SELECT * FROM wpp_broadcast_queue WHERE status IN ('pendente', 'enviando') ORDER BY id ASC LIMIT 1");
    $broadcast = $stmt->fetch();

    if (!$broadcast) {
        echo "Nenhum broadcast na fila.\n";
        flock($lock_fp, LOCK_UN);
        fclose($lock_fp);
        exit;
    }

    $b_id = $broadcast['id'];

    // Se estava pendente, muda para enviando e seta data de inicio
    if ($broadcast['status'] === 'pendente') {
        $conn->exec("UPDATE wpp_broadcast_queue SET status = 'enviando', iniciado_em = CURRENT_TIMESTAMP WHERE id = $b_id");
    }

    // Identificar quais grupos precisam receber
    // Busca todos os grupos que combinam com o filtro e que AINDA NÃO ESTÃO no log
    $sql_grupos = "
        SELECT g.id, g.nome, g.group_id 
        FROM meetup_whatsapp_groups g 
        WHERE g.ativo = 1
    ";

    $params = [];
    if ($broadcast['filtro_categoria'] === 'multi_idioma') {
        $sql_grupos .= " AND g.categoria = 'multi_idioma'";
    } elseif ($broadcast['filtro_categoria'] === 'especifico') {
        $sql_grupos .= " AND g.categoria = 'especifico' AND g.language_id = ?";
        $params[] = $broadcast['filtro_language_id'];
    }

    // Excluir os que já receberam ou deram erro neste broadcast
    $sql_grupos .= " AND g.group_id NOT IN (SELECT group_id FROM wpp_broadcast_log WHERE broadcast_id = ?)";
    $params[] = $b_id;

    $stmt_grupos = $conn->prepare($sql_grupos);
    $stmt_grupos->execute($params);
    $grupos_restantes = $stmt_grupos->fetchAll();

    if (count($grupos_restantes) === 0) {
        // Acabou
        $conn->exec("UPDATE wpp_broadcast_queue SET status = 'concluido', concluido_em = CURRENT_TIMESTAMP WHERE id = $b_id");
        echo "Broadcast $b_id concluído.\n";
        flock($lock_fp, LOCK_UN);
        fclose($lock_fp);
        exit;
    }

    // Configuração Anti-Ban
    $lote_tamanho = 5;
    $grupos_lote = array_slice($grupos_restantes, 0, $lote_tamanho);

    echo "Processando lote de " . count($grupos_lote) . " grupos para o broadcast $b_id...\n";

    $stmt_log = $conn->prepare("INSERT INTO wpp_broadcast_log (broadcast_id, group_id, group_nome, status, erro_msg) VALUES (?, ?, ?, ?, ?)");
    $stmt_upd = $conn->prepare("UPDATE wpp_broadcast_queue SET enviados = enviados + 1 WHERE id = ?");

    foreach ($grupos_lote as $g) {
        // Envia mensagem
        $result = enviarWhatsApp($g['group_id'], $broadcast['mensagem'], 'broadcast_admin');
        
        $status_log = 'enviado';
        $erro_msg = null;

        if (!$result['success']) {
            $status_log = 'erro';
            $erro_msg = $result['error'] ?? 'Erro desconhecido';
        }

        // Registrar no log
        $stmt_log->execute([$b_id, $g['group_id'], $g['nome'], $status_log, $erro_msg]);

        // Incrementar enviados se sucesso
        if ($status_log === 'enviado') {
            $stmt_upd->execute([$b_id]);
        }

        // Delay entre mensagens do mesmo lote (1 a 3 segundos)
        sleep(rand(1, 3));
    }

    // Se ainda tem mais grupos na fila (alem do lote atual), não fazemos nada, 
    // a próxima execução do cron pega o resto. O cron vai cuidar do delay entre lotes naturalmente.
    // Ou podemos rodar um sleep de 10-18 segundos aqui e puxar outro lote, mas se rodar via cron,
    // é melhor deixar terminar o script rápido e o próximo cron rodar daqui a 1 minuto.
    // Mas para ser um pouco mais rápido, se o script for rodado a cada minuto e envia 5 grupos, são 300 grupos por hora.
    // Como a base tem ~475 grupos (no projeto geral), isso levaria 1h30. Para os grupos de idioma (talvez 50), leva 10 min.

    echo "Lote finalizado.\n";

} catch (Exception $e) {
    echo "Erro geral: " . $e->getMessage() . "\n";
} finally {
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
}
