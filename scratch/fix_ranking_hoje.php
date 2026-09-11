<?php
/**
 * Remove o grupo Indonésio antigo do ranking de hoje.
 * Busca o JID do grupo pelo nome no banco e chama o endpoint do Baileys.
 */
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = connectDB();

    // Pega todos os grupos ativos para identificar o correto (sem "Ei |")
    $stmt = $conn->query("SELECT group_id, nome, comunidade, ativo FROM meetup_whatsapp_groups WHERE nome LIKE '%Bahasa%' OR nome LIKE '%Indonesia%'");
    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== Grupos encontrados ===\n";
    foreach ($grupos as $g) {
        echo "Nome: {$g['nome']} | JID: {$g['group_id']} | Comunidade: {$g['comunidade']} | Ativo: {$g['ativo']}\n";
    }

    // Identifica o JID do grupo antigo (o que não tem "Ei |" no nome)
    $jidAntigo = null;
    foreach ($grupos as $g) {
        if (strpos($g['nome'], 'Ei |') === false) {
            $jidAntigo = $g['group_id'];
            echo "\n→ JID a remover do ranking: $jidAntigo\n";
            break;
        }
    }

    if (!$jidAntigo) {
        echo "\nNenhum grupo antigo encontrado para remover.\n";
        exit;
    }

    // Chama endpoint para deletar a entrada do activity_log de hoje
    $hoje = date('Y-m-d');
    $res = sendBaileysRequest('/community-activity-delete', ['date' => $hoje, 'groupJid' => $jidAntigo], 'POST');

    echo "\n=== Resposta do Baileys ===\n";
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
