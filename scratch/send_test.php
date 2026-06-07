<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Get 3 groups that are NOT the ones we used recently
// Exclude: 13477461732-1553354997, 120363225749665362, 120363148227096134
$exclude = ["13477461732-1553354997@g.us", "120363225749665362@g.us", "120363148227096134@g.us"];
$exclude_str = implode("','", $exclude);

$sql = "SELECT group_id, nome FROM tb_grupos WHERE group_id NOT IN ('$exclude_str') LIMIT 3";
$result = $conn->query($sql);

$groups = [];
while ($row = $result->fetch_assoc()) {
    $groups[] = $row['group_id'];
    echo "Selecionado: " . $row['nome'] . " (" . $row['group_id'] . ")\n";
}

$mensagem = "Em breve aqui os replays dos encontros de idiomas da semana!";

if (count($groups) > 0) {
    echo "Enviando mensagem para " . count($groups) . " grupos...\n";
    $payload = [
        'groups' => $groups,
        'textMessage' => ['text' => $mensagem]
    ];
    $response = sendBaileysRequest('/send-bulk', $payload, 'POST');
    print_r($response);
} else {
    echo "Nenhum grupo encontrado.\n";
}

$conn->close();
