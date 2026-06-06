<?php
/**
 * EXPERIMENTO CONTROLADO: Fila Assíncrona com Cadência (15 Segundos)
 * Objetivo: Dar respiro ao servidor (Garbage Collector) entre cada cálculo
 * criptográfico de grupo, isolando os erros de timeout (hardware) dos erros HTTP 400 (@lid).
 */

require_once '../config.php';

// Proteção para rodar via navegador ou terminal
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== 'teste2026x') {
    http_response_code(403);
    die('Acesso negado. Use ?key=teste2026x na URL.');
}

// Para forçar o PHP a enviar o output para a tela em tempo real no navegador
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<style>body{font-family: monospace; background: #111; color: #eee; padding: 20px;}
    .success {color: #0f0;} .timeout {color: #fa0;} .protocol {color: #f55;}
    </style>";
}

$EVOLUTION_API_URL = "http://136.248.92.126:8080/message/sendText/meetups";
$EVOLUTION_API_KEY = "SenhaMeetups2026";
$mensagemTeste = "🔧 [Diagnóstico Automático da Administração] Teste de cadência do sistema.";

echo "<h2>🚀 Iniciando Teste de Cadência (15 Segundos)</h2>\n";
echo "Aliviando a CPU/RAM do servidor entre cada disparo...<br><br>\n";
echo "<strong>⚠️ ATENÇÃO:</strong> O teste está limitado a apenas 3 grupos por segurança.<br>\n";
echo "<strong>Mensagem a ser enviada:</strong> <em>{$mensagemTeste}</em><br><br>\n";
flush();
ob_flush();

try {
    // Busca apenas 3 grupos como amostra de segurança para não incomodar todos
    $stmt = $conn->query("SELECT nome, group_id FROM meetup_whatsapp_groups WHERE group_id IS NOT NULL AND group_id != '' LIMIT 3");
    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($grupos) === 0) {
        die("Nenhum grupo encontrado no banco de dados.");
    }

    $total = count($grupos);
    $atual = 0;

    foreach ($grupos as $grupo) {
        $atual++;
        $nomeGrupo = $grupo['nome'];
        $groupId = $grupo['group_id'];

        echo "[$atual/$total] Processando: <strong>{$nomeGrupo}</strong> ({$groupId})<br>\n";
        flush();
        ob_flush();

        $payload = json_encode([
            "number" => $groupId,
            "textMessage" => ["text" => $mensagemTeste]
        ]);

        $ch = curl_init($EVOLUTION_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Timeout de 30 segundos: se demorar mais que isso, o servidor congelou por RAM
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "apikey: $EVOLUTION_API_KEY"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        curl_close($ch);

        // Análise do Resultado do Grupo (Isolado)
        if ($response === false || $httpcode === 0 || $curlError) {
            // Falha de Conexão ou Congelamento por falta de CPU/RAM (Timeout)
            echo "<span class='timeout'>[FALHA - TIMEOUT]</span> O servidor não aguentou gerar a criptografia neste grupo. Tempo: {$duration}s. (Erro: $curlError)<br><br>\n";
        } elseif ($httpcode === 400 || $httpcode === 500) {
            // A API rejeitou rapidamente. É o bug do protocolo com o @lid!
            $respObj = json_decode($response, true);
            $motivo = $respObj['response']['message'][0] ?? 'Erro interno na API (Provável protocolo @lid)';
            echo "<span class='protocol'>[FALHA - PROTOCOLO {$httpcode}]</span> A API recusou o pacote criptográfico ativo. Tempo: {$duration}s. Motivo: {$motivo}<br><br>\n";
        } elseif ($httpcode === 200 || $httpcode === 201) {
            // Sucesso Total!
            echo "<span class='success'>[SUCESSO]</span> Mensagem entregue! Tempo: {$duration}s.<br><br>\n";
        } else {
            // Outro tipo de erro HTTP não catalogado
            echo "<span>[DESCONHECIDO]</span> HTTP $httpcode. Tempo: {$duration}s. Resposta: $response<br><br>\n";
        }

        // Se não for o último grupo, aplica a cadência (Respiro do Garbage Collector)
        if ($atual < $total) {
            echo "<em>Aguardando 15 segundos para limpar a memória (Garbage Collector)...</em><br><br>\n";
            flush();
            ob_flush();
            sleep(15);
        }
    }

    echo "<h3>✅ Teste de Fila Concluído!</h3>\n";

} catch (Exception $e) {
    echo "<span class='protocol'>[ERRO CRÍTICO]</span> Falha no script: " . $e->getMessage() . "<br>\n";
}
?>
