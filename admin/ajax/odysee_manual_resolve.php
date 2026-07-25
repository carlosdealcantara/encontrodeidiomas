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
    $manual_url = trim($_POST['manual_url'] ?? '');

    if ($queue_id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID inválido"]);
        exit;
    }

    if (empty($manual_url) || strpos($manual_url, 'odysee.com') === false) {
        echo json_encode(["status" => "error", "message" => "A URL fornecida não parece ser uma URL válida do Odysee"]);
        exit;
    }

    $conn = connectDB();

    // Busca a tarefa para verificar se existe e pegar o language_id
    $stmt = $conn->prepare("SELECT language_id, status FROM odysee_publish_queue WHERE id = ?");
    $stmt->execute([$queue_id]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarefa) {
        echo json_encode(["status" => "error", "message" => "Tarefa não encontrada"]);
        exit;
    }

    // Aciona API do clck.ru
    $api_url = "https://clck.ru/--";
    
    $url_curta = null;
    $max_tentativas = 3;
    $res_body = null;
    $http_code = null;
    
    for ($t = 0; $t < $max_tentativas; $t++) {
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['url' => $manual_url]);
        
        $res_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 && $res_body && strpos(trim($res_body), 'http') === 0) {
            $url_curta = trim($res_body);
            break; // Sucesso, sai do loop
        }
        
        // Se falhou, espera 1.5s
        if ($t < $max_tentativas - 1) {
            usleep(1500000); 
        }
    }

    if ($url_curta) {
        // Atualiza odysee_publish_queue para DONE e salva a URL encurtada
        $stmtUpd1 = $conn->prepare("UPDATE odysee_publish_queue SET status = 'done', odysee_url = ? WHERE id = ?");
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
            $webhook_url = "https://dev.viaei.com/ajax/webhook_odysee_success.php";
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
        
        echo json_encode(["status" => "success", "new_url" => $url_curta, "message" => "URL processada, encurtada e notificada com sucesso!"]);
    } else {
        $errorPreview = $res_body ? substr(htmlspecialchars($res_body), 0, 100) : "Timeout ou falha na API do clck.ru";
        echo json_encode(["status" => "error", "message" => "clck.ru falhou ao encurtar (HTTP {$http_code}): " . $errorPreview]);
    }
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Erro PHP: " . $e->getMessage() . " na linha " . $e->getLine()]);
}
