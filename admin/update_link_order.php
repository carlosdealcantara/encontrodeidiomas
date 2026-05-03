<?php
session_start();
require_once '../config.php';

// Proteção
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Acesso negado');
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['order']) && is_array($data['order'])) {
    $conn = connectDB();
    
    // A ordem recebida é do topo para o fundo (ex: [ID_TOP, ID_NEXT, ..., ID_BOTTOM])
    // Como usamos Prioridade DESC, o primeiro deve ter o MAIOR número.
    $count = count($data['order']);
    
    foreach ($data['order'] as $index => $id) {
        $priority = $count - $index; // Ex: se tem 5 links, o primeiro ganha 5, o segundo 4...
        $stmt = $conn->prepare("UPDATE useful_links SET order_index = ? WHERE id = ?");
        $stmt->execute([$priority, (int)$id]);
    }
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
}
