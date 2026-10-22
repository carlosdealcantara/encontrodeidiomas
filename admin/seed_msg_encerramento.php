<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();

    $cenario = "Encerramento de Ciclo (Portas Abertas)";
    $lang = "en";
    $dias = -999;
    
    $texto = "Olá, {nome}! Tudo bem?\n\nPassando para agradecer muito pela sua confiança durante todo esse período em que estivemos juntos na mentoria! 🙏✨\n\nHoje encerramos este ciclo de acesso ao Our Classes, mas o nosso contato e a sua presença na comunidade continuam firmes.\n\nContinue praticando com o pessoal nos encontros abertos e acompanhando o nosso Pod com as dicas gratuitas que o teacher compartilha por lá. E lembre-se: se no futuro você quiser retornar à mentoria para uma nova fase, as portas estarão sempre abertas para você!\n\nDesejamos muito sucesso na sua jornada com o inglês! Um grande abraço! 🚀";

    // Verifica se já existe
    $check = $conn->prepare("SELECT id FROM mentoria_mensagens WHERE cenario = ? AND lang_id = ?");
    $check->execute([$cenario, $lang]);
    $existente = $check->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        $stmt = $conn->prepare("UPDATE mentoria_mensagens SET texto = ?, dias_antes = ?, ativo = 1 WHERE id = ?");
        $stmt->execute([$texto, $dias, $existente['id']]);
        echo json_encode(["status" => "updated", "id" => $existente['id']], JSON_UNESCAPED_UNICODE);
    } else {
        $stmt = $conn->prepare("INSERT INTO mentoria_mensagens (cenario, dias_antes, texto, ativo, ativo_telegram, lang_id) VALUES (?, ?, ?, 1, 1, ?)");
        $stmt->execute([$cenario, $dias, $texto, $lang]);
        $novoId = $conn->lastInsertId();
        echo json_encode(["status" => "inserted", "id" => $novoId], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
