<?php
/**
 * ============================================================
 * MOTOR DOS MEETUPS - CRON JOB
 * ============================================================
 * Este arquivo deve ser chamado pelo servidor (Hostinger)
 * a cada hora, ou 15 min.
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
    $conn->exec("DELETE FROM meetup_whatsapp_logs WHERE data_disparo = CURRENT_DATE()");
    echo "<h3>Logs de hoje apagados com sucesso.</h3>";
}

echo "<h2>Iniciando Varredura do Motor de Meetups (Cron Job)</h2>";

$hoje = new DateTime();
$diaDaSemanaAtual = (int)$hoje->format('N'); // 1 = Segunda, 7 = Domingo
$horaAtualReal = (int)$hoje->format('H'); // 0 a 23

$dataDisparo = $hoje->format('Y-m-d');

// 1. Pega os templates ativos (excluindo o Resumo do Dia, que roda em outro script)
$stmtTemplates = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario != 'Resumo do Dia'");
$templates = $stmtTemplates->fetchAll();

if(count($templates) === 0) {
    die("Nenhum template ativo. Abortando.");
}

// 2. Pega encontros ativos para HOJE
$stmtMeetings = $conn->prepare("
    SELECT m.*, l.name as language_name, l.flag_emoji, l.instagram_link, l.greeting 
    FROM meetings m
    JOIN languages l ON m.language_id = l.id
    WHERE m.active = 1 AND m.day_of_week = ?
");
$stmtMeetings->execute([$diaDaSemanaAtual]);
$meetings = $stmtMeetings->fetchAll();

if(count($meetings) === 0) {
    die("Nenhum encontro ativo para hoje ($diaDaSemanaAtual). Abortando.");
}

// 3. Pega grupos ativos
$stmtGroups = $conn->query("SELECT * FROM meetup_whatsapp_groups WHERE ativo = 1");
$groups = $stmtGroups->fetchAll();

$sucessos = 0;

foreach ($meetings as $m) {
    $horaEncontro = (int)$m['time_hour'];
    
    foreach ($templates as $t) {
        $minutosAntes = (int)$t['minutos_antes'];
        
        // Calcula a hora em que esta mensagem DEVERIA ser enviada
        // Vamos arredondar para horas cheias para simplificar o cron de hora em hora
        // Se minutosAntes = 120 (2 horas), horaEnvio = horaEncontro - 2
        $horasAntes = round($minutosAntes / 60);
        $horaAlvo = $horaEncontro - $horasAntes;
        
        // Se a hora alvo bate com a hora atual (com uma tolerância se o cron rodar +- minutos)
        // Como é cron de hora em hora (ou 15m), verificamos se a hora bate
        if ($horaAtualReal === (int)$horaAlvo) {
            
            // Prepara a mensagem
            $textoFinal = $t['template_texto'];
            $textoFinal = str_replace('{IDIOMA}', strtoupper($m['language_name']), $textoFinal);
            $textoFinal = str_replace('{idioma}', $m['language_name'], $textoFinal);
            $textoFinal = str_replace('{EMOJI_FLAG}', $m['flag_emoji'], $textoFinal);
            $textoFinal = str_replace('{EMOJI_FLAGS}', str_repeat($m['flag_emoji'], 5), $textoFinal);
            $textoFinal = str_replace('{SAUDACAO}', $m['greeting'] ?? 'Welcome!', $textoFinal);
            $textoFinal = str_replace('{MEET_LINK}', $m['meet_link'] ?: 'Link não definido', $textoFinal);
            $textoFinal = str_replace('{INSTAGRAM_LINK}', $m['instagram_link'] ?: 'Sem link', $textoFinal);
            
            // Filtra os grupos que devem receber ESTA mensagem DESTE idioma
            foreach ($groups as $g) {
                $podeEnviar = false;
                
                if ($g['categoria'] === 'multi_idioma') {
                    $podeEnviar = true;
                } else if ($g['categoria'] === 'especifico' && $g['language_id'] == $m['language_id']) {
                    $podeEnviar = true;
                }
                
                if ($podeEnviar) {
                    // Verifica anti-duplicidade
                    $stmtCheck = $conn->prepare("SELECT id FROM meetup_whatsapp_logs WHERE grupo_id = ? AND meeting_id = ? AND template_id = ? AND data_disparo = ?");
                    $stmtCheck->execute([$g['id'], $m['id'], $t['id'], $dataDisparo]);
                    
                    if ($stmtCheck->rowCount() === 0) {
                        // PROTEÇÃO ANTI-COLAPSO: Ignorar grupos com ID antigo (com hífen)
                        if (strpos($g['group_id'], '-') !== false) {
                            echo "<p>⚠️ Pulo de Segurança: O grupo '{$g['nome']}' usa um ID antigo (com hífen) que trava a Evolution API. Pulando.</p>";
                            continue;
                        }
                        
                        // Envia para o motor unificado do Baileys
                        $result = enviarWhatsApp($g['group_id'], $textoFinal, 'meetup_cron');
                        $httpcode = $result['httpCode'];
                        $response = json_encode($result);
                        
                        // Só loga no banco se a API respondeu OK
                        if ($httpcode >= 200 && $httpcode < 300) {
                            $stmtLog = $conn->prepare("INSERT INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo) VALUES (?, ?, ?, ?)");
                            $stmtLog->execute([$g['id'], $m['id'], $t['id'], $dataDisparo]);
                            
                            echo "<p>✅ [{$t['cenario']}] enviada para o Grupo '{$g['nome']}' (Idioma: {$m['language_name']}). (Status API: {$httpcode})</p>";
                            $sucessos++;
                        } else {
                            echo "<p style='color:red;'>❌ Erro ao enviar [{$t['cenario']}] para '{$g['nome']}'. HTTP: {$httpcode} | Resposta: " . htmlspecialchars($response) . "</p>";
                            sleep(5);
                        }
                    } else {
                        echo "<p>⏭️ Pulando Grupo '{$g['nome']}': [{$t['cenario']}] já enviada hoje para o Meetup de {$m['language_name']}.</p>";
                    }
                }
            }
        }
    }
}

echo "<h3>Varredura concluída! {$sucessos} mensagens enviadas hoje.</h3>";
?>
