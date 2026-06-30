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

    // Fetch t.ly token from settings
    $stmtToken = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tly_api_key'");
    $stmtToken->execute();
    $tokenRow = $stmtToken->fetch(PDO::FETCH_ASSOC);
    $token = $tokenRow['setting_value'] ?? null;

    if (!$token) {
        echo json_encode(["status" => "error", "message" => "Token t.ly não encontrado nas configurações (settings)"]);
        exit;
    }

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

    // Se já for curta (t.ly), não precisa
    if (strpos($url_longa, 't.ly') !== false) {
        echo json_encode(["status" => "error", "message" => "A URL já está encurtada com t.ly"]);
        exit;
    }

    // Aciona API do t.ly
    $api_url = "https://t.ly/api/v1/link/shorten";
    $payload = json_encode(["long_url" => $url_longa]);
    
    $url_curta = null;
    $max_tentativas = 3;
    $res_body = null;
    $http_code = null;
    
    for ($t = 0; $t < $max_tentativas; $t++) {
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 && $res_body) {
            $data = json_decode($res_body, true);
            if (isset($data['short_url'])) {
                $url_curta = trim($data['short_url']);
                break; // Sucesso, sai do loop
            }
        }
        
        // Se falhou, espera 1.5s
        if ($t < $max_tentativas - 1) {
            usleep(1500000); 
        }
    }

    if ($url_curta) {
        // Atualiza odysee_publish_queue
        $stmtUpd1 = $conn->prepare("UPDATE odysee_publish_queue SET odysee_url = ? WHERE id = ?");
        $stmtUpd1->execute([$url_curta, $queue_id]);
        
        // Atualiza meetup_replays
        $stmtUpd2 = $conn->prepare("
            UPDATE meetup_replays 
            SET link = ? 
            WHERE language_id = ? 
            AND semana = DATE_FORMAT(NOW(), '%x-W%v')
        ");
        $stmtUpd2->execute([$url_curta, $tarefa['language_id']]);
        
        // Dispara notificação para os hosts
        if (defined('SITE_URL')) {
            $webhook_url = SITE_URL . "/ajax/webhook_odysee_success.php";
        } else {
            $webhook_url = "https://dev.encontrodeidiomas.com.br/ajax/webhook_odysee_success.php";
        }
        
        $chWH = curl_init($webhook_url);
        curl_setopt($chWH, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chWH, CURLOPT_POST, true);
        curl_setopt($chWH, CURLOPT_POSTFIELDS, json_encode([
            "apikey" => "SenhaMeetups2026",
            "lang_id" => $tarefa['language_id']
        ]));
        curl_setopt($chWH, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($chWH, CURLOPT_TIMEOUT, 5);
        curl_exec($chWH);
        curl_close($chWH);
        
        echo json_encode(["status" => "success", "new_url" => $url_curta, "message" => "URL encurtada com t.ly com sucesso!"]);
    } else {
        $errorPreview = $res_body ? substr(htmlspecialchars($res_body), 0, 100) : "Timeout ou falha na API do t.ly";
        echo json_encode(["status" => "error", "message" => "t.ly falhou (HTTP {$http_code}): " . $errorPreview]);
    }
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Erro PHP: " . $e->getMessage() . " na linha " . $e->getLine()]);
}
