<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/whatsapp_helper.php';

$conn = connectDB();

try {
    $stmt = $conn->query("SELECT * FROM wpp_broadcast_queue WHERE status IN ('pendente') ORDER BY id ASC LIMIT 1");
    $broadcast = $stmt->fetch();

    if (!$broadcast) {
        die("Nenhum broadcast pendente para testar.\n");
    }

    $b_id = $broadcast['id'];
    $conn->exec("UPDATE wpp_broadcast_queue SET status = 'enviando', iniciado_em = CURRENT_TIMESTAMP WHERE id = $b_id");

    $sql_grupos = "SELECT g.id, g.nome, g.group_id FROM meetup_whatsapp_groups g WHERE g.ativo = 1";
    $params = [];
    if ($broadcast['filtro_categoria'] === 'multi_idioma') {
        $sql_grupos .= " AND g.categoria = 'multi_idioma'";
    } elseif ($broadcast['filtro_categoria'] === 'especifico') {
        $sql_grupos .= " AND g.categoria = 'especifico' AND g.language_id = ?";
        $params[] = $broadcast['filtro_language_id'];
    }
    $sql_grupos .= " AND g.group_id NOT IN (SELECT group_id FROM wpp_broadcast_log WHERE broadcast_id = ?)";
    $params[] = $b_id;
    $sql_grupos .= " LIMIT 1";

    $stmt_grupos = $conn->prepare($sql_grupos);
    $stmt_grupos->execute($params);
    $grupo = $stmt_grupos->fetch();

    if (!$grupo) {
        die("Nenhum grupo encontrado.\n");
    }

    echo "Enviando teste apenas para o grupo: " . htmlspecialchars($grupo['nome']) . "\n";

    // Enviar
    $result = enviarWhatsApp($grupo['group_id'], $broadcast['mensagem'], 'broadcast_admin');
    
    $status_log = 'enviado';
    $erro_msg = null;
    if (!$result['success']) {
        $status_log = 'erro';
        $erro_msg = $result['error'] ?? 'Erro desconhecido';
        echo "Erro ao enviar: " . htmlspecialchars($erro_msg) . "\n";
    } else {
        echo "Mensagem enviada com sucesso.\n";
    }

    $stmt_log = $conn->prepare("INSERT INTO wpp_broadcast_log (broadcast_id, group_id, group_nome, status, erro_msg) VALUES (?, ?, ?, ?, ?)");
    $stmt_log->execute([$b_id, $grupo['group_id'], $grupo['nome'], $status_log, $erro_msg]);

    // Atualiza a fila para concluido
    $conn->exec("UPDATE wpp_broadcast_queue SET status = 'concluido', concluido_em = CURRENT_TIMESTAMP, enviados = 1, total_grupos = 1 WHERE id = $b_id");
    
    echo "Broadcast $b_id forçado a concluído.\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
