<?php
/**
 * Verificação diagnóstica — mentoria espanhol
 * Arquivo temporário de verificação. Não subir para produção.
 */
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') { http_response_code(403); die('Negado'); }

header('Content-Type: text/plain; charset=utf-8');

$conn = connectDB();

echo "=== 1. TABELA mentoria_langs ===\n";
try {
    $rows = $conn->query("SELECT lang_id, nome, ativo FROM mentoria_langs ORDER BY lang_id")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "VAZIA ou não existe!\n";
    } else {
        foreach ($rows as $r) {
            echo "  lang_id={$r['lang_id']} | nome={$r['nome']} | ativo={$r['ativo']}\n";
        }
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== 2. meetup_whatsapp_groups (grupos ES e Rincón) ===\n";
try {
    $rows = $conn->query("
        SELECT group_id, nome, lang_code, ativo, welcome_enabled 
        FROM meetup_whatsapp_groups 
        WHERE lang_code = 'es' OR nome LIKE '%Rinc%' OR nome LIKE '%rincó%' OR nome LIKE '%Espanhol%' OR nome LIKE '%Español%'
        ORDER BY lang_code, nome
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "Nenhum grupo ES encontrado!\n";
    } else {
        foreach ($rows as $r) {
            echo "  group_id={$r['group_id']} | nome={$r['nome']} | lang={$r['lang_code']} | ativo={$r['ativo']} | welcome={$r['welcome_enabled']}\n";
        }
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== 3. Todos os grupos em meetup_whatsapp_groups ===\n";
try {
    $rows = $conn->query("SELECT group_id, nome, lang_code, ativo, welcome_enabled FROM meetup_whatsapp_groups ORDER BY lang_code, nome")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "  [{$r['lang_code']}] {$r['nome']} | ativo={$r['ativo']} | welcome={$r['welcome_enabled']} | jid={$r['group_id']}\n";
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== 4. mentoria_desafio_streaks (membros ES) — últimas 5 entradas ===\n";
try {
    // Verificar se existe a tabela e se tem dados do grupo ES
    $rows = $conn->query("SELECT member_jid, member_name, current_streak, last_completed_date FROM mentoria_desafio_streaks ORDER BY last_completed_date DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "Sem registros.\n";
    } else {
        foreach ($rows as $r) {
            echo "  {$r['member_name']} | streak={$r['current_streak']} | last={$r['last_completed_date']}\n";
        }
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== 5. mentoria_auto_logs — execuções do aviso de desafio hoje ===\n";
try {
    $hoje = date('Y-m-d');
    $rows = $conn->prepare("SELECT tipo, data_execucao, detalhes, created_at FROM mentoria_auto_logs WHERE data_execucao = ? AND tipo LIKE 'desafio%' ORDER BY created_at DESC LIMIT 10");
    $rows->execute([$hoje]);
    $data = $rows->fetchAll(PDO::FETCH_ASSOC);
    if (empty($data)) {
        echo "Nenhum log de desafio encontrado para hoje ({$hoje}).\n";
    } else {
        foreach ($data as $r) {
            echo "  tipo={$r['tipo']} | data={$r['data_execucao']} | detalhes={$r['detalhes']} | at={$r['created_at']}\n";
        }
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\nFIM DO DIAGNÓSTICO\n";
