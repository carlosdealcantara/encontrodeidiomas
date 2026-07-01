<?php
/**
 * ============================================================
 * MOTOR DOS MEETUPS - CRON JOB DIÁRIO (RESUMO DO DIA)
 * ============================================================
 * Este arquivo deve ser chamado pelo servidor (Hostinger)
 * apenas 1 VEZ AO DIA (ex: 09:00 AM).
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

// MODO DE CONTENÇÃO: verifica flag no banco
if (getSystemSetting($conn, 'wpp_meetups_daily_ativo', '0') !== '1') {
    echo "<h2>⛔ Modo de Contenção Ativo</h2><p>Resumo diário de meetups está desativado. Ative em Admin → Modo de Contenção.</p>";
    exit;
}

// Desliga o buffer do PHP para mostrar o texto na tela em tempo real
while (ob_get_level()) { ob_end_flush(); }
ob_implicit_flush(1);

echo "<h2>[DEBUG ATIVADO] Iniciando Varredura do Resumo do Dia</h2>";
echo "Conectado ao banco. Buscando data atual...<br>";

$hoje = new DateTime();
$diaDaSemanaAtual = (int)$hoje->format('N'); // 1 = Segunda, 7 = Domingo
$dataDisparo = $hoje->format('Y-m-d');

// 1. Pega o template do "Resumo do Dia" (Busca por nome mágico)
echo "Buscando template 'Resumo do Dia'...<br>";
$stmtTemplate = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario = 'Resumo do Dia' LIMIT 1");
$templateDiario = $stmtTemplate->fetch();

if (!$templateDiario) {
    die("Nenhum template chamado 'Resumo do Dia' encontrado ou ativo. Abortando.");
}
echo "Template encontrado (ID: {$templateDiario['id']}).<br>";

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
echo "Encontros de hoje encontrados: " . count($meetings) . ".<br>";

// 3. Monta a lista global de todos os encontros de hoje (todos os grupos recebem a mesma lista completa)
$listaGlobalEncontros = [];
$languageIdsHoje = [];
foreach ($meetings as $m) {
    $listaGlobalEncontros[] = "{$m['flag_emoji']} {$m['name_en']} | {$m['language_name']}";
    $languageIdsHoje[] = $m['language_id'];
}
$listaFormatadaGlobal = implode("\n", $listaGlobalEncontros);

// 4. Pega grupos ativos
echo "Buscando grupos ativos...<br>";
$stmtGroups = $conn->query("SELECT * FROM meetup_whatsapp_groups WHERE ativo = 1");
$groups = $stmtGroups->fetchAll();
echo "Total de grupos ativos: " . count($groups) . ". Iniciando loop de disparos...<hr>";

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
        echo "Grupo '{$g['nome']}' QUALIFICADO. Checando anti-duplicidade...<br>";
        
        // Verifica anti-duplicidade (já enviou o resumo hoje para este grupo?)
        // Como o Resumo não tem meeting_id específico, usamos meeting_id = 0
        $stmtCheck = $conn->prepare("SELECT id FROM meetup_whatsapp_logs WHERE grupo_id = ? AND template_id = ? AND data_disparo = ? AND meeting_id = 0");
        $stmtCheck->execute([$g['id'], $templateDiario['id'], $dataDisparo]);
        
        if ($stmtCheck->rowCount() === 0 || isset($_GET['force'])) {
            echo "&nbsp;&nbsp;-> Tudo limpo! Tentando conectar na API Evolution para '{$g['nome']}'...<br>";
            flush();
            
            // Troca a variável mágica {LISTA_ENCONTROS}
            $textoFinal = str_replace('{LISTA_ENCONTROS}', $listaFormatadaGlobal, $templateDiario['template_texto']);
            
            // Envia para o motor unificado do Baileys
            $inicioCurl = microtime(true);
            $result = enviarWhatsApp($g['group_id'], $textoFinal, 'meetup_cron_diario');
            $httpcode = $result['httpCode'];
            $tempoGasto = round(microtime(true) - $inicioCurl, 2);
            $response = json_encode($result);
            
            // Só loga no banco se a API respondeu OK
            if ($httpcode >= 200 && $httpcode < 300) {
                // Log usando meeting_id = 0 (valor fixo para o Resumo do Dia)
                $stmtLog = $conn->prepare("INSERT INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo) VALUES (?, 0, ?, ?)");
                $stmtLog->execute([$g['id'], $templateDiario['id'], $dataDisparo]);
                
                echo "&nbsp;&nbsp;-> ✅ Sucesso! Enviado em {$tempoGasto}s (Status: {$httpcode}). Log registrado.<br>";
                $sucessos++;
            } else {
                echo "&nbsp;&nbsp;-> ❌ Erro na API HTTP {$httpcode}. (Demorou {$tempoGasto}s) Resposta: " . htmlspecialchars($response) . "<br>";
                // Se deu erro fatal (ex: 400), pausamos por 5 segundos para dar tempo do Node.js da Evolution API se recuperar
                if ($httpcode >= 400) {
                    echo "&nbsp;&nbsp;-> ⚠️ Pausando 5 segundos para a API respirar após o erro...<br>";
                    sleep(5);
                }
            }
        } else {
            echo "&nbsp;&nbsp;-> ⏭️ Pulando Grupo '{$g['nome']}': Resumo do dia já enviado hoje.<br>";
        }
    }
}

echo "<h3>Varredura concluída! {$sucessos} Resumos do Dia enviados hoje.</h3>";
?>
