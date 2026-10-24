<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');

$conn = connectDB();

echo "=== GRUPOS DA COMUNIDADE GLOBAL ===\n";
$stmt = $conn->query("SELECT id, group_id, nome, lang_code, comunidade, welcome_enabled, ativo FROM meetup_whatsapp_groups WHERE comunidade = 'global' ORDER BY nome ASC");
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($groups as $g) {
    echo "ID: {$g['id']} | Nome: {$g['nome']} | Lang: {$g['lang_code']} | JID: {$g['group_id']} | Welcome: {$g['welcome_enabled']} | Ativo: {$g['ativo']}\n";
}

echo "\n=== ULTIMAS ENTRADAS NA FILA DE WELCOME (community_welcome_queue) ===\n";
try {
    $stmt2 = $conn->query("SELECT * FROM community_welcome_queue ORDER BY id DESC LIMIT 15");
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        print_r($r);
    }
} catch (Exception $e) {
    echo "Erro queue: " . $e->getMessage() . "\n";
}

echo "\n=== INTROS ATIVAS ===\n";
try {
    $stmt3 = $conn->query("SELECT id, ativo, text_en FROM community_welcome_intros WHERE ativo = 1");
    $intros = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo "Total intros ativas: " . count($intros) . "\n";
    foreach ($intros as $in) {
        echo "- [{$in['id']}] {$in['text_en']}\n";
    }
} catch (Exception $e) {
    echo "Erro intros: " . $e->getMessage() . "\n";
}

echo "\n=== PERGUNTAS ATIVAS ===\n";
try {
    $stmt4 = $conn->query("SELECT id, ativo, text_en FROM community_welcome_questions WHERE ativo = 1");
    $qs = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    echo "Total perguntas ativas: " . count($qs) . "\n";
    foreach ($qs as $q) {
        echo "- [{$q['id']}] {$q['text_en']}\n";
    }
} catch (Exception $e) {
    echo "Erro perguntas: " . $e->getMessage() . "\n";
}
