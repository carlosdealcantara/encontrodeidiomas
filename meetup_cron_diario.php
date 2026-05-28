<?php
/**
 * ============================================================
 * MOTOR DOS MEETUPS - CRON JOB DIÁRIO (RESUMO DO DIA)
 * ============================================================
 * Este arquivo deve ser chamado pelo servidor (Hostinger)
 * apenas 1 VEZ AO DIA (ex: 09:00 AM).
 */

require_once __DIR__ . '/config.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$EVOLUTION_API_URL = "http://136.248.92.126:8080/message/sendText/meetups";
$EVOLUTION_API_KEY = "SenhaMeetups2026";

$conn = connectDB();

echo "<h2>Iniciando Varredura do Resumo do Dia (Cron Job Diário)</h2>";

$hoje = new DateTime();
$diaDaSemanaAtual = (int)$hoje->format('N'); // 1 = Segunda, 7 = Domingo
$dataDisparo = $hoje->format('Y-m-d');

// 1. Pega o template do "Resumo do Dia" (Busca por nome mágico)
$stmtTemplate = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario = 'Resumo do Dia' LIMIT 1");
$templateDiario = $stmtTemplate->fetch();

if (!$templateDiario) {
    die("Nenhum template chamado 'Resumo do Dia' encontrado ou ativo. Abortando.");
}

// 2. Pega encontros ativos para HOJE
$stmtMeetings = $conn->prepare("
    SELECT m.*, l.name as language_name, l.name_en, l.flag_emoji 
    FROM meetings m
    JOIN languages l ON m.language_id = l.id
    WHERE m.active = 1 AND m.day_of_week = ?
    ORDER BY m.time_hour ASC
");
$stmtMeetings->execute([$diaDaSemanaAtual]);
$meetings = $stmtMeetings->fetchAll();

if(count($meetings) === 0) {
    die("Nenhum encontro ativo para hoje ($diaDaSemanaAtual). Abortando.");
}

// 3. Monta a lista global de todos os encontros de hoje (todos os grupos recebem a mesma lista completa)
$listaGlobalEncontros = [];
$languageIdsHoje = [];
foreach ($meetings as $m) {
    $listaGlobalEncontros[] = "{$m['flag_emoji']} {$m['language_name']}";
    $languageIdsHoje[] = $m['language_id'];
}
$listaFormatadaGlobal = implode("\n", $listaGlobalEncontros);

// 4. Pega grupos ativos
$stmtGroups = $conn->query("SELECT * FROM meetup_whatsapp_groups WHERE ativo = 1");
$groups = $stmtGroups->fetchAll();

$sucessos = 0;

foreach ($groups as $g) {
    $podeEnviar = false;
    
    if ($g['categoria'] === 'multi_idioma') {
        $podeEnviar = true;
    } else if ($g['categoria'] === 'especifico') {
        // Se o idioma específico deste grupo está na lista de encontros de hoje
        if (in_array($g['language_id'], $languageIdsHoje)) {
            $podeEnviar = true;
        }
    }
    
    // Se o grupo tem direito de receber o resumo hoje
    if ($podeEnviar) {
        
        // Verifica anti-duplicidade (já enviou o resumo hoje para este grupo?)
        // Como o Resumo não tem meeting_id específico, procuramos meeting_id IS NULL
        $stmtCheck = $conn->prepare("SELECT id FROM meetup_whatsapp_logs WHERE grupo_id = ? AND template_id = ? AND data_disparo = ? AND meeting_id IS NULL");
        $stmtCheck->execute([$g['id'], $templateDiario['id'], $dataDisparo]);
        
        if ($stmtCheck->rowCount() === 0) {
            
            // Troca a variável mágica {LISTA_ENCONTROS}
            $textoFinal = str_replace('{LISTA_ENCONTROS}', $listaFormatadaGlobal, $templateDiario['template_texto']);
            
            // Envia para a API
            $payload = json_encode([
                "number" => $g['group_id'],
                "options" => [
                    "delay" => 1200,
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
            
            $response = curl_exec($ch); 
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Só loga no banco se a API respondeu OK
            if ($httpcode >= 200 && $httpcode < 300) {
                // Log sem meeting_id específico, pois é um resumo de vários (meeting_id = NULL)
                $stmtLog = $conn->prepare("INSERT INTO meetup_whatsapp_logs (grupo_id, template_id, data_disparo) VALUES (?, ?, ?)");
                $stmtLog->execute([$g['id'], $templateDiario['id'], $dataDisparo]);
                
                echo "<p>✅ Resumo do Dia enviado para o Grupo '{$g['nome']}'. (Status API: {$httpcode})</p>";
                $sucessos++;
            } else {
                echo "<p style='color:red;'>❌ Erro ao enviar Resumo do Dia para '{$g['nome']}'. HTTP: {$httpcode} | Resposta: " . htmlspecialchars($response) . "</p>";
            }
        } else {
            echo "<p>⏭️ Pulando Grupo '{$g['nome']}': Resumo do dia já enviado hoje.</p>";
        }
    }
}

echo "<h3>Varredura concluída! {$sucessos} Resumos do Dia enviados hoje.</h3>";
?>
