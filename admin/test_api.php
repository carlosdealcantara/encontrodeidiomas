<?php
// Ferramenta Científica de Diagnóstico de Grupos
$EVOLUTION_API_URL = "http://136.248.92.126:8080/message/sendText/meetups";
$EVOLUTION_API_KEY = "SenhaMeetups2026";

if (!isset($_GET['group_id'])) {
    die("Uso correto: acesse a URL passando ?group_id=ID_DO_GRUPO");
}

$groupId = trim($_GET['group_id']);
$payload = json_encode([
    "number" => $groupId,
    "options" => ["delay" => 1200],
    "textMessage" => ["text" => "🔧 Teste de Diagnóstico do Sistema."]
]);

echo "<h2>Laboratório de Teste: Evolution API</h2>";
echo "<h3>Alvo: $groupId</h3>";
echo "<b>1. Payload que será enviado:</b><pre>$payload</pre>";

// Checar status da API ANTES do envio
$ch_status = curl_init("http://136.248.92.126:8080/instance/connectionState/meetups");
curl_setopt($ch_status, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_status, CURLOPT_HTTPHEADER, ["apikey: " . $EVOLUTION_API_KEY]);
$status_antes = curl_exec($ch_status);
curl_close($ch_status);
echo "<b>2. Status da API ANTES do envio:</b><pre>" . htmlspecialchars($status_antes) . "</pre>";

echo "<b>3. Disparando mensagem...</b><br>";
flush();

$ch = curl_init($EVOLUTION_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "apikey: " . $EVOLUTION_API_KEY
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$inicio = microtime(true);
$response = curl_exec($ch);
$tempo = round(microtime(true) - $inicio, 2);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "<p style='color:red;'>Erro cURL (Timeout ou Queda): " . curl_error($ch) . " (Tempo de espera: {$tempo}s)</p>";
} else {
    echo "<p>HTTP Code retornado: <b>$httpcode</b> (Tempo de resposta: {$tempo}s)</p>";
    echo "<b>Resposta Bruta do Servidor:</b><pre>" . htmlspecialchars($response) . "</pre>";
}
curl_close($ch);

// Checar status da API DEPOIS do envio para ver se ela "morreu"
$ch_status2 = curl_init("http://136.248.92.126:8080/instance/connectionState/meetups");
curl_setopt($ch_status2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_status2, CURLOPT_HTTPHEADER, ["apikey: " . $EVOLUTION_API_KEY]);
$status_depois = curl_exec($ch_status2);
curl_close($ch_status2);
echo "<b>4. Status da API DEPOIS do envio:</b><pre>" . htmlspecialchars($status_depois) . "</pre>";
?>
