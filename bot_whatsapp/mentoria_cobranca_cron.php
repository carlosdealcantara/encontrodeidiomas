<?php
/**
 * ============================================================
 * MOTOR DA MENTORIA - CRON JOB DE COBRANÇAS
 * ============================================================
 * Restaurado do commit 1c793edb21ce289f8ea3d49748bda2dd19a3e907
 * Chamado pelo master_cron.php diariamente.
 */

require_once __DIR__ . '/../config.php';

$token_secreto = '83x9aZ2pLQw1';
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$conn = connectDB();

if (isset($_GET['clear_logs']) && $_GET['clear_logs'] == '1') {
    $conn->exec("DELETE FROM mentoria_logs WHERE data_disparo = CURRENT_DATE");
    echo "<div style='background: #ffeb3b; padding: 15px; margin-bottom: 20px;'>⚠️ Logs de hoje foram APAGADOS! O sistema tentará reenviar todas as mensagens pendentes de hoje.</div>";
}

$conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        aluno_id INT NOT NULL,
        mensagem_id INT NOT NULL,
        data_disparo DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

echo "<h2>Iniciando Varredura do Motor de Cobranças (Cron Job)</h2>";

$conn->exec("
    UPDATE mentoria_alunos 
    SET status_pagamento = 'Pendente' 
    WHERE status_aluno = 'Ativo' 
    AND status_pagamento = 'Pago' 
    AND DATEDIFF(proximo_vencimento, CURRENT_DATE) <= 3
");

$pix_footer = getSetting('mentoria_pix_footer', "🔑 Chave PIX: 01811018157\nCarlos");

$stmtMsgs = $conn->query("SELECT * FROM mentoria_mensagens WHERE ativo = 1");
$mensagensAtivas = $stmtMsgs->fetchAll();
if(count($mensagensAtivas) === 0) {
    die("Nenhuma mensagem ativada no painel. Abortando.");
}

$mensagensMap = [];
foreach($mensagensAtivas as $m) {
    $mensagensMap[$m['dias_antes']] = $m;
}

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
    if(strpos($aluno['proximo_vencimento'], '-0001') !== false || substr($aluno['proximo_vencimento'], 0, 4) == '1900') {
        continue;
    }
    
    $vencimento = new DateTime($aluno['proximo_vencimento']);
    $vencimento->setTime(0,0,0);
    
    $diff = $hoje->diff($vencimento);
    $diasFaltando = (int)$diff->format('%R%a'); 
    
    if (isset($mensagensMap[$diasFaltando])) {
        $msgConfig = $mensagensMap[$diasFaltando];
        $msgId = $msgConfig['id'];
        $alunoId = $aluno['id'];
        
        $stmtCheck = $conn->prepare("SELECT id FROM mentoria_logs WHERE aluno_id = ? AND mensagem_id = ? AND data_disparo = ?");
        $stmtCheck->execute([$alunoId, $msgId, $dataDisparo]);
        
        if ($stmtCheck->rowCount() === 0) {
            $textoFinal = str_replace('{nome}', trim(explode(' ', $aluno['nome'])[0]), $msgConfig['texto']);
            $textoFinal .= "\n\n" . trim($pix_footer);
            
            $telefoneLimpo = preg_replace('/\D/', '', $aluno['telefone']);
            if (strlen($telefoneLimpo) <= 11) {
                $telefoneLimpo = "55" . $telefoneLimpo;
            }
            
            $result = enviarWhatsApp($telefoneLimpo, $textoFinal, 'mentoria_cron');
            $httpcode = $result['httpCode'];
            $response = json_encode($result);
            
            if ($httpcode >= 200 && $httpcode < 300) {
                $stmtLog = $conn->prepare("INSERT INTO mentoria_logs (aluno_id, mensagem_id, data_disparo) VALUES (?, ?, ?)");
                $stmtLog->execute([$alunoId, $msgId, $dataDisparo]);
                echo "<p>✅ Mensagem ({$msgConfig['cenario']}) enviada para {$aluno['nome']} (Status API: {$httpcode})</p>";
                $sucessos++;
            } else {
                echo "<p>❌ Erro ao enviar para {$aluno['nome']}. API retornou Status: {$httpcode}. Resposta: " . htmlspecialchars($response) . "</p>";
            }
        } else {
            echo "<p>⏭️ Pulando {$aluno['nome']}: Mensagem ({$msgConfig['cenario']}) já foi enviada hoje.</p>";
        }
    }
}

echo "<h3>Varredura de Cobrança concluída! {$sucessos} mensagens enviadas hoje.</h3>";
?>
