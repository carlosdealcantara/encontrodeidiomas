<?php
/**
 * Correção no banco para os replays e fila do Francês nesta semana (2026-W40)
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') {
    http_response_code(403);
    die(json_encode(['error' => 'Acesso negado']));
}

try {
    $conn = connectDB();
    $semana_atual = date('o-\WW');

    // 1. Restaurar Encontro 1 de Francês (Segunda 28/09):
    // Link já postado no Odysee (clck.ru/3WAxkT), título: "une journée malheureuse"
    // Participantes mantidos ou 07
    $conn->exec("
        UPDATE meetup_replays 
        SET titulo = 'une journée malheureuse', link = 'https://clck.ru/3WAxkT'
        WHERE language_id = 3 AND semana = '{$semana_atual}' AND parte = 1
    ");

    // 2. Gravar Encontro 2 de Francês (Quinta 02/10):
    // Título preenchido pelo host: "Tout est question d'amour !", Participantes: "07", Link vazio (aguardando upload)
    $stmt2 = $conn->prepare("
        INSERT INTO meetup_replays (language_id, semana, parte, numero, link, titulo)
        VALUES (3, ?, 2, '07', '', 'Tout est question d\'amour !')
        ON DUPLICATE KEY UPDATE numero = '07', titulo = 'Tout est question d\'amour !'
    ");
    $stmt2->execute([$semana_atual]);

    // 3. Atualizar a fila de publicação do Odysee para o Encontro 2 (ID 174)
    // Passar para status='pending' com o título do host para o worker subir o vídeo
    $conn->exec("
        UPDATE odysee_publish_queue 
        SET titulo_final = 'Tout est question d\'amour !', status = 'pending', retry_count = 0, error_message = NULL
        WHERE id = 174
    ");

    // Retorna estado atualizado
    $replays = $conn->query("SELECT * FROM meetup_replays WHERE language_id = 3 AND semana = '{$semana_atual}' ORDER BY parte ASC")->fetchAll(PDO::FETCH_ASSOC);
    $queue = $conn->query("SELECT id, status, titulo_final, odysee_url, replay_parte, semana FROM odysee_publish_queue WHERE language_id = 3 AND semana = '{$semana_atual}' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'replays' => $replays,
        'queue' => $queue
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
