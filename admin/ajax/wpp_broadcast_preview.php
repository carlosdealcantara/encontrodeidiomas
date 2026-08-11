<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$categoria = $_POST['categoria'] ?? 'todos';
$language_id = isset($_POST['language_id']) && !empty($_POST['language_id']) ? (int)$_POST['language_id'] : null;

$conn = connectDB();

try {
    if ($categoria === 'todos') {
        // Todos os grupos ativos (multi ou específicos)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM meetup_whatsapp_groups WHERE ativo = 1 AND bot_presente = 1");
        $stmt->execute();
    } elseif ($categoria === 'multi_idioma') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM meetup_whatsapp_groups WHERE ativo = 1 AND bot_presente = 1 AND categoria = 'multi_idioma'");
        $stmt->execute();
    } elseif ($categoria === 'especifico') {
        if ($language_id) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM meetup_whatsapp_groups WHERE ativo = 1 AND bot_presente = 1 AND categoria = 'especifico' AND language_id = ?");
            $stmt->execute([$language_id]);
        } else {
            // Se específico mas sem idioma, mostra o total de específicos
            $stmt = $conn->prepare("SELECT COUNT(*) FROM meetup_whatsapp_groups WHERE ativo = 1 AND bot_presente = 1 AND categoria = 'especifico'");
            $stmt->execute();
        }
    } else {
        echo json_encode(['count' => 0]);
        exit;
    }

    $count = $stmt->fetchColumn();
    echo json_encode(['count' => $count]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados']);
}
