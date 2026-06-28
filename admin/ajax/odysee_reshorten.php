<?php
session_start();
require_once dirname(dirname(__DIR__)) . '/config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Não autorizado"]);
    exit;
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => "error", "message" => "Método não permitido"]);
        exit;
    }

    $queue_id = (int)($_POST['queue_id'] ?? 0);
    if ($queue_id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID inválido"]);
        exit;
    }

    $conn = connectDB();

    // Busca a tarefa para pegar a URL atual e o idioma
    $stmt = $conn->prepare("SELECT language_id, odysee_url, status FROM odysee_publish_queue WHERE id = ?");
    $stmt->execute([$queue_id]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarefa) {
        echo json_encode(["status" => "error", "message" => "Tarefa não encontrada"]);
        exit;
    }

    if ($tarefa['status'] !== 'done') {
        echo json_encode(["status" => "error", "message" => "A tarefa ainda não foi concluída"]);
        exit;
    }

    $url_longa = trim($tarefa['odysee_url'] ?? '');

    // Se for texto de erro, URL base sem o link canônico do Odysee ou vazia, precisamos buscar o slug real
    if (empty($url_longa) || strpos($url_longa, 'odysee.com') === false) {
        $stmtSlug = $conn->prepare("
            SELECT q.odysee_slug, l.odysee_channel_name 
            FROM odysee_publish_queue q
            JOIN languages l ON q.language_id = l.id
            WHERE q.id = ?
        ");
        $stmtSlug->execute([$queue_id]);
        $slugData = $stmtSlug->fetch(PDO::FETCH_ASSOC);
        if ($slugData && !empty($slugData['odysee_slug']) && !empty($slugData['odysee_channel_name'])) {
            $channelName = trim($slugData['odysee_channel_name']);
            if (strpos($channelName, '@') !== 0) {
                $channelName = '@' . $channelName;
            }
            $url_longa = "https://odysee.com/{$channelName}/{$slugData['odysee_slug']}";
        } else {
            echo json_encode(["status" => "error", "message" => "URL inválida para encurtar e sem slug disponível"]);
            exit;
        }
    }

    // Se já for curta (is.gd), não precisa
    if (strpos($url_longa, 'is.gd') !== false) {
        echo json_encode(["status" => "error", "message" => "A URL já está encurtada"]);
        exit;
    }

    // Aciona API do is.gd
    $api_url = "https://is.gd/create.php?format=simple&url=" . urlencode($url_longa);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res_url = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $res_url && strpos($res_url, 'http') === 0) {
        $url_curta = trim($res_url);
        
        // Atualiza odysee_publish_queue
        $stmtUpd1 = $conn->prepare("UPDATE odysee_publish_queue SET odysee_url = ? WHERE id = ?");
        $stmtUpd1->execute([$url_curta, $queue_id]);
        
        // Atualiza meetup_replays (resumo dos hosts)
        $stmtUpd2 = $conn->prepare("
            UPDATE meetup_replays 
            SET link = ? 
            WHERE language_id = ? 
            AND link = ?
        ");
        $stmtUpd2->execute([$url_curta, $tarefa['language_id'], $tarefa['odysee_url']]);
        
        echo json_encode(["status" => "success", "new_url" => $url_curta, "message" => "URL encurtada com sucesso!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "is.gd falhou: " . htmlspecialchars(substr($res_url ?? '', 0, 50))]);
    }
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Erro PHP: " . $e->getMessage() . " na linha " . $e->getLine()]);
}
