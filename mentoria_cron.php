<?php
/**
 * ============================================================
 * MOTOR DA MENTORIA - CRON JOB
 * ============================================================
 * Este arquivo deve ser chamado pelo servidor (Hostinger) diariamente.
 * Exemplo de URL de chamada: https://seusite.com.br/mentoria_cron.php?token=SEU_TOKEN_SECRETO
 */

require_once __DIR__ . '/config.php';

// Segurança: Proteja este arquivo para que curiosos não disparem as mensagens
$token_secreto = '83x9aZ2pLQw1'; // Altere se desejar
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}// Configurações da Evolution API (Máquina da Oracle)
$EVOLUTION_API_URL = "http://136.248.92.126:8080/message/sendText/meetups";
$EVOLUTION_API_KEY = "SenhaMeetups2026";

$conn = connectDB();

// Limpar logs para teste
if (isset($_GET['clear_logs']) && $_GET['clear_logs'] == '1') {
    $conn->exec("DELETE FROM mentoria_logs WHERE data_disparo = CURRENT_DATE");
    echo "<div style='background: #ffeb3b; padding: 15px; margin-bottom: 20px;'>⚠️ Logs de hoje foram APAGADOS! O sistema tentará reenviar todas as mensagens pendentes de hoje.</div>";
}

// Cria a tabela de logs se não existir (para evitar disparos duplicados no mesmo dia)
$conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        aluno_id INT NOT NULL,
        mensagem_id INT NOT NULL,
        data_disparo DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

echo "<h2>Iniciando Varredura do Motor (Cron Job)</h2>";

// Inteligência Cíclica: Se um aluno está 'Pago', mas faltam 3 dias (ou menos) para o seu próximo ciclo, 
// o sistema automaticamente 'reabre' a cobrança mudando o status para 'Pendente'.
$conn->exec("
    UPDATE mentoria_alunos 
    SET status_pagamento = 'Pendente' 
    WHERE status_aluno = 'Ativo' 
    AND status_pagamento = 'Pago' 
    AND DATEDIFF(proximo_vencimento, CURRENT_DATE) <= 3
");

// Pega o rodapé padrão PIX
$pix_footer = getSetting('mentoria_pix_footer', "🔑 Chave PIX: 01811018157\nCarlos");

// Pega todas as mensagens ATIVAS
$stmtMsgs = $conn->query("SELECT * FROM mentoria_mensagens WHERE ativo = 1");
$mensagensAtivas = $stmtMsgs->fetchAll();
if(count($mensagensAtivas) === 0) {
    die("Nenhuma mensagem ativada no painel. Abortando.");
}

// Mapeia mensagens ativas por 'dias_antes' para acesso rápido
$mensagensMap = [];
foreach($mensagensAtivas as $m) {
    $mensagensMap[$m['dias_antes']] = $m;
}

// Busca todos os alunos ativos que precisam pagar
// Não manda para Inativos, Vitalícios, Isentos ou quem já está Pago
$stmtAlunos = $conn->query("
    SELECT * FROM mentoria_alunos 
    WHERE status_aluno = 'Ativo' 
    AND status_pagamento IN ('Pendente', 'Suspenso')
");
$alunos = $stmtAlunos->fetchAll();

$hoje = new DateTime();
$hoje->setTime(0,0,0);
$dataDisparo = $hoje->format('Y-m-d');

$sucessos = 0;

foreach ($alunos as $aluno) {
    $vencimento = new DateTime($aluno['proximo_vencimento']);
    $vencimento->setTime(0,0,0);
    
    // Calcula a diferença em dias
    // Se hoje é dia 1 e vence dia 4, diff é +3. 
    // Se hoje é dia 5 e vence dia 4, diff é -1.
    $diff = $hoje->diff($vencimento);
    $diasFaltando = (int)$diff->format('%R%a'); 
    
    // Existe uma mensagem programada para este número exato de dias?
    if (isset($mensagensMap[$diasFaltando])) {
        $msgConfig = $mensagensMap[$diasFaltando];
        $msgId = $msgConfig['id'];
        $alunoId = $aluno['id'];
        
        // Verifica se JÁ ENVIAMOS essa mensagem para esse aluno HOJE (prevenção de duplicidade)
        $stmtCheck = $conn->prepare("SELECT id FROM mentoria_logs WHERE aluno_id = ? AND mensagem_id = ? AND data_disparo = ?");
        $stmtCheck->execute([$alunoId, $msgId, $dataDisparo]);
        
        if ($stmtCheck->rowCount() === 0) {
            // == PREPARA O TEXTO ==
            // 1. Substitui a variável {nome} pelo nome real do aluno
            $textoFinal = str_replace('{nome}', trim(explode(' ', $aluno['nome'])[0]), $msgConfig['texto']);
            
            // 2. Anexa o Rodapé Global PIX com 2 quebras de linha para espaçamento perfeito
            $textoFinal .= "\n\n" . trim($pix_footer);
            
            // == ENVIA PARA A ORACLE (cURL) ==
            // Se o telefone não tiver o código do país (55), vamos adicionar (assumindo Brasil)
            $telefoneLimpo = preg_replace('/\D/', '', $aluno['telefone']);
            if (strlen($telefoneLimpo) <= 11) {
                $telefoneLimpo = "55" . $telefoneLimpo;
            }
            
            // Payload no formato exato que a Evolution API exige
            $payload = json_encode([
                "number" => $telefoneLimpo,
                "options" => [
                    "delay" => 1500,
                    "presence" => "composing"
                ],
                "textMessage" => [
                    "text" => $textoFinal
                ]
            ]);
            
            $ch = curl_init($EVOLUTION_API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "apikey: " . $EVOLUTION_API_KEY
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            
            // Executa a requisição DE VERDADE
            $response = curl_exec($ch); 
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Loga no banco para não mandar duas vezes, APENAS SE DEU SUCESSO (Status 200 ou 201)
            if ($httpcode >= 200 && $httpcode < 300) {
                $stmtLog = $conn->prepare("INSERT INTO mentoria_logs (aluno_id, mensagem_id, data_disparo) VALUES (?, ?, ?)");
                $stmtLog->execute([$alunoId, $msgId, $dataDisparo]);
                echo "<p>✅ Mensagem ({$msgConfig['cenario']}) enviada para {$aluno['nome']} (Status API: {$httpcode})</p>";
                $sucessos++;
            } else {
                echo "<p>❌ Erro ao enviar para {$aluno['nome']}. A API da Oracle retornou o Status: {$httpcode}. Resposta: " . htmlspecialchars($response) . "</p>";
            }
        } else {
            echo "<p>⏭️ Pulando {$aluno['nome']}: Mensagem ({$msgConfig['cenario']}) já foi enviada hoje.</p>";
        }
    }
}

echo "<h3>Varredura concluída! {$sucessos} mensagens enviadas hoje.</h3>";
?>
