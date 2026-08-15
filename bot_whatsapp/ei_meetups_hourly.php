<?php
/**
 * ============================================================
 * MOTOR DOS MEETUPS - CRON JOB
 * ============================================================
 * Chamado pelo servidor (Hostinger) a cada 5 minutos.
 *
 * BLOCO A: Templates 'por_encontro' — disparam por meeting × grupo
 * BLOCO B: Templates 'diario'       — disparam 1x/dia por grupo,
 *          X minutos antes do PRIMEIRO encontro do dia.
 *          Ex: "Convite para Host" com escopo='diario' e frequencia='semanal'
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
if (getSystemSetting($conn, 'wpp_meetups_hourly_ativo', '0') !== '1') {
    echo "<h2>⛔ Modo de Contenção Ativo</h2><p>Avisos de meetup estão desativados. Ative em Admin → Modo de Contenção.</p>";
    exit;
}

if (isset($_GET['clear_logs']) && $_GET['clear_logs'] == '1') {
    $conn->exec("DELETE FROM meetup_whatsapp_logs WHERE data_disparo = CURRENT_DATE()");
    echo "<h3>Logs de hoje apagados com sucesso.</h3>";
}

echo "<h2>Iniciando Varredura do Motor de Meetups (Cron Job)</h2>";

$hoje             = new DateTime();
$diaDaSemanaAtual = (int)$hoje->format('N'); // 1=Seg, 7=Dom
$horaAtualReal    = (int)$hoje->format('H');
$minutosAtual     = (int)$hoje->format('i');
$totalMinAtual    = $horaAtualReal * 60 + $minutosAtual;
$semanaIso        = (int)$hoje->format('W');
$dataDisparo      = $hoje->format('Y-m-d');

// 1. Pega templates ativos (exceto Resumo do Dia, que roda em outro script)
$stmtTemplates = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario != 'Resumo do Dia'");
$templates = $stmtTemplates->fetchAll();

if (count($templates) === 0) {
    die("Nenhum template ativo. Abortando.");
}

// 2. Pega encontros ativos para HOJE, ordenados por horário crescente
// Usa IFNULL(time_minute, 0) para compatibilidade caso a coluna não exista
try {
    $stmtMeetings = $conn->prepare("
        SELECT m.*, l.name as language_name, l.flag_emoji, l.instagram_link, l.greeting,
               IFNULL(m.time_minute, 0) as time_minute_safe
        FROM meetings m
        JOIN languages l ON m.language_id = l.id
        WHERE m.active = 1 AND m.day_of_week = ?
        ORDER BY (m.time_hour * 60 + IFNULL(m.time_minute, 0)) ASC
    ");
    $stmtMeetings->execute([$diaDaSemanaAtual]);
} catch (PDOException $e) {
    // Fallback: coluna time_minute não existe na tabela meetings
    $stmtMeetings = $conn->prepare("
        SELECT m.*, l.name as language_name, l.flag_emoji, l.instagram_link, l.greeting,
               0 as time_minute_safe
        FROM meetings m
        JOIN languages l ON m.language_id = l.id
        WHERE m.active = 1 AND m.day_of_week = ?
        ORDER BY m.time_hour ASC
    ");
    $stmtMeetings->execute([$diaDaSemanaAtual]);
}
$meetings = $stmtMeetings->fetchAll();

if (count($meetings) === 0) {
    die("Nenhum encontro ativo para hoje ($diaDaSemanaAtual). Abortando.");
}

// 3. Pega grupos ativos com bot presente
$stmtGroups = $conn->query("SELECT * FROM meetup_whatsapp_groups WHERE ativo = 1 AND bot_presente = 1");
$groups = $stmtGroups->fetchAll();

// 4. Separa templates por escopo
$templatesPorEncontro = [];
$templatesDiario      = [];
foreach ($templates as $t) {
    $escopo = $t['escopo'] ?? 'por_encontro';
    if ($escopo === 'diario') {
        $templatesDiario[] = $t;
    } else {
        $templatesPorEncontro[] = $t;
    }
}

$sucessos = 0;

// ============================================================
// BLOCO A: Templates POR ENCONTRO (comportamento original)
// Dispara para cada combinação meeting × grupo elegível
// ============================================================
echo "<h3>🔵 Bloco A: Templates por Encontro</h3>";

foreach ($meetings as $m) {
    $totalMinEncontro = (int)$m['time_hour'] * 60 + (int)$m['time_minute_safe'];

    foreach ($templatesPorEncontro as $t) {
        $minutosAntes = (int)$t['minutos_antes'];
        $totalMinAlvo = $totalMinEncontro - $minutosAntes;

        // Tolerância de ±4 min (cron de 5 em 5 minutos)
        if (abs($totalMinAtual - $totalMinAlvo) > 4) continue;

        // Substitui variáveis na mensagem
        $textoFinal = $t['template_texto'];
        $textoFinal = str_replace('{IDIOMA}',        strtoupper($m['language_name']),          $textoFinal);
        $textoFinal = str_replace('{idioma}',        $m['language_name'],                       $textoFinal);
        $textoFinal = str_replace('{EMOJI_FLAG}',    $m['flag_emoji'],                          $textoFinal);
        $textoFinal = str_replace('{EMOJI_FLAGS}',   str_repeat($m['flag_emoji'], 5),           $textoFinal);
        $textoFinal = str_replace('{SAUDACAO}',      $m['greeting'] ?? 'Welcome!',              $textoFinal);
        $linkLimpo  = str_replace(['https://', 'http://'], '', $m['meet_link'] ?? '');
        $textoFinal = str_replace('{MEET_LINK}',     $linkLimpo ?: 'Link não definido',         $textoFinal);
        $textoFinal = str_replace('{INSTAGRAM_LINK}',$m['instagram_link'] ?: 'Sem link',        $textoFinal);
        $textoFinal = str_replace('{HOST_LINK}',     'https://viaei.com/equipe/',               $textoFinal);

        foreach ($groups as $g) {
            $podeEnviar = ($g['categoria'] === 'multi_idioma');
            if (!$podeEnviar && $g['categoria'] === 'especifico' && !empty($g['language_ids'])) {
                $ids = json_decode($g['language_ids'], true);
                if (is_array($ids) && in_array($m['language_id'], $ids)) {
                    $podeEnviar = true;
                }
            }

            if (!$podeEnviar) continue;

            // Verificação semanal (para templates marcados como semanal dentro do escopo por_encontro)
            $frequencia = $t['frequencia'] ?? 'diario';
            if ($frequencia === 'semanal' && !isset($_GET['force'])) {
                $stmtCheck = $conn->prepare("SELECT id FROM meetup_whatsapp_logs WHERE grupo_id = ? AND template_id = ? AND semana_iso = ?");
                $stmtCheck->execute([$g['id'], $t['id'], $semanaIso]);
                if ($stmtCheck->rowCount() > 0) {
                    echo "<p>⏭️ Pulando '{$g['nome']}': [{$t['cenario']}] já enviada esta semana.</p>";
                    continue;
                }
            }

            if (isset($_GET['force'])) {
                $conn->prepare("DELETE FROM meetup_whatsapp_logs WHERE grupo_id = ? AND meeting_id = ? AND template_id = ? AND data_disparo = ?")->execute([$g['id'], $m['id'], $t['id'], $dataDisparo]);
            }

            try {
                $stmtLog = $conn->prepare("INSERT IGNORE INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo, semana_iso) VALUES (?, ?, ?, ?, ?)");
                $stmtLog->execute([$g['id'], $m['id'], $t['id'], $dataDisparo, $semanaIso]);
                $logId = $conn->lastInsertId();
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "Unknown column 'semana_iso'") !== false) {
                    $stmtLog = $conn->prepare("INSERT IGNORE INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo) VALUES (?, ?, ?, ?)");
                    $stmtLog->execute([$g['id'], $m['id'], $t['id'], $dataDisparo]);
                    $logId = $conn->lastInsertId();
                } else { throw $e; }
            }

            if ($logId == 0) {
                echo "<p>⏭️ Pulando '{$g['nome']}': [{$t['cenario']}] já enviada hoje para {$m['language_name']}.</p>";
            } else {
                $result   = enviarWhatsApp($g['group_id'], $textoFinal, 'meetup_cron');
                $httpcode = $result['httpCode'];
                if ($httpcode >= 200 && $httpcode < 300) {
                    echo "<p>✅ [{$t['cenario']}] → '{$g['nome']}' ({$m['language_name']}) | HTTP {$httpcode}</p>";
                    $sucessos++;
                } else {
                    $conn->exec("DELETE FROM meetup_whatsapp_logs WHERE id = $logId");
                    echo "<p style='color:red;'>❌ [{$t['cenario']}] → '{$g['nome']}'. HTTP: {$httpcode} | " . htmlspecialchars(json_encode($result)) . "</p>";
                    sleep(5);
                }
            }
        }
    }
}

// ============================================================
// BLOCO B: Templates DIÁRIOS (ex: Convite para Host)
// Dispara 1x/dia por grupo, X min antes do PRIMEIRO encontro
// {BANDEIRAS_DO_DIA} = emojis de todos os idiomas do dia
//   → grupos multi_idioma: todas as bandeiras
//   → grupos específicos:  apenas a bandeira do idioma deles (se houver encontro hoje)
// ============================================================
if (!empty($templatesDiario)) {
    echo "<h3>🟠 Bloco B: Templates Diários</h3>";

    // Primeiro encontro do dia (já ordenado ASC por horário)
    $primeiroMin = (int)$meetings[0]['time_hour'] * 60 + (int)$meetings[0]['time_minute_safe'];

    // Constrói mapas de bandeiras
    $flagsPorIdioma = []; // [language_id => flag_emoji]
    $flagsDodia     = []; // lista única de emojis para multi_idioma
    foreach ($meetings as $m) {
        $flagsPorIdioma[$m['language_id']] = $m['flag_emoji'];
        if (!in_array($m['flag_emoji'], $flagsDodia)) {
            $flagsDodia[] = $m['flag_emoji'];
        }
    }
    $bandeirasTodas = implode('', $flagsDodia);

    foreach ($templatesDiario as $t) {
        $minutosAntes = (int)$t['minutos_antes'];
        $totalMinAlvo = $primeiroMin - $minutosAntes;

        // Tolerância de ±4 min (cron de 5 em 5 minutos)
        if (abs($totalMinAtual - $totalMinAlvo) > 4) continue;

        foreach ($groups as $g) {
            // Define bandeiras e elegibilidade por tipo de grupo
            if ($g['categoria'] === 'multi_idioma') {
                $bandeirasGrupo = $bandeirasTodas;
                $podeEnviar     = true;
            } elseif ($g['categoria'] === 'especifico') {
                $bandeirasGrupo = $flagsPorIdioma[$g['language_id']] ?? null;
                // Só envia se há encontro do idioma do grupo hoje
                $podeEnviar = $bandeirasGrupo !== null;
            } else {
                continue;
            }

            if (!$podeEnviar) continue;

            // Verificação semanal (padrão para templates diários)
            $frequencia = $t['frequencia'] ?? 'semanal';
            if ($frequencia === 'semanal' && !isset($_GET['force'])) {
                $stmtCheck = $conn->prepare("SELECT id FROM meetup_whatsapp_logs WHERE grupo_id = ? AND template_id = ? AND semana_iso = ?");
                $stmtCheck->execute([$g['id'], $t['id'], $semanaIso]);
                if ($stmtCheck->rowCount() > 0) {
                    echo "<p>⏭️ Pulando '{$g['nome']}': [{$t['cenario']}] já enviada esta semana (Semana $semanaIso).</p>";
                    continue;
                }
            }

            // Substitui variáveis na mensagem
            $textoFinal = $t['template_texto'];
            $textoFinal = str_replace('{BANDEIRAS_DO_DIA}', $bandeirasGrupo,              $textoFinal);
            $textoFinal = str_replace('{HOST_LINK}',        'https://viaei.com/equipe/',  $textoFinal);

            // Anti-duplicidade: usa meeting_id = 0 (não é por encontro)
            if (isset($_GET['force'])) {
                $conn->prepare("DELETE FROM meetup_whatsapp_logs WHERE grupo_id = ? AND template_id = ? AND data_disparo = ?")->execute([$g['id'], $t['id'], $dataDisparo]);
            }

            try {
                $stmtLog = $conn->prepare("INSERT IGNORE INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo, semana_iso) VALUES (?, 0, ?, ?, ?)");
                $stmtLog->execute([$g['id'], $t['id'], $dataDisparo, $semanaIso]);
                $logId = $conn->lastInsertId();
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "Unknown column 'semana_iso'") !== false) {
                    $stmtLog = $conn->prepare("INSERT IGNORE INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo) VALUES (?, 0, ?, ?)");
                    $stmtLog->execute([$g['id'], $t['id'], $dataDisparo]);
                    $logId = $conn->lastInsertId();
                } else { throw $e; }
            }

            if ($logId == 0) {
                echo "<p>⏭️ Pulando '{$g['nome']}': [{$t['cenario']}] já enviada hoje.</p>";
            } else {
                $result   = enviarWhatsApp($g['group_id'], $textoFinal, 'meetup_cron');
                $httpcode = $result['httpCode'];
                if ($httpcode >= 200 && $httpcode < 300) {
                    echo "<p>✅ [{$t['cenario']}] → '{$g['nome']}' | HTTP {$httpcode}</p>";
                    $sucessos++;
                } else {
                    $conn->exec("DELETE FROM meetup_whatsapp_logs WHERE id = $logId");
                    echo "<p style='color:red;'>❌ [{$t['cenario']}] → '{$g['nome']}'. HTTP: {$httpcode} | " . htmlspecialchars(json_encode($result)) . "</p>";
                    sleep(5);
                }
            }
        }
    }
}

echo "<h3>Varredura concluída! {$sucessos} mensagens enviadas.</h3>";
?>
