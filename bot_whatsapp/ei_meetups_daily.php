<?php
/**
 * ============================================================
 * MOTOR DOS MEETUPS - CRON JOB DIÁRIO (RESUMO DO DIA)
 * ============================================================
 * Este arquivo deve ser chamado pelo servidor (Hostinger)
 * apenas 1 VEZ AO DIA (ex: 09:00 AM).
 *
 * Suporte a Comunidade Brasil / Global:
 *  - Busca TODOS os templates de Resumo do Dia ativos.
 *  - Para cada grupo, seleciona apenas os templates compatíveis
 *    com a comunidade do grupo (brasil / global / ambos).
 *  - {SITE_LINK} é resolvido automaticamente por comunidade.
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

// 1. Pega TODOS os templates do "Resumo do Dia" ativos
//    O cruzamento por comunidade é feito no loop de grupos abaixo
echo "Buscando templates 'Resumo do Dia'...<br>";
$stmtTemplate = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario = 'Resumo do Dia'");
$templatesDiario = $stmtTemplate->fetchAll();

if (empty($templatesDiario)) {
    die("Nenhum template chamado 'Resumo do Dia' encontrado ou ativo. Abortando.");
}
echo count($templatesDiario) . " template(s) 'Resumo do Dia' encontrado(s).<br>";

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

if (count($meetings) === 0) {
    die("Nenhum encontro ativo para hoje ($diaDaSemanaAtual). Abortando.");
}
echo "Encontros de hoje encontrados: " . count($meetings) . ".<br>";

// 3. Monta as listas de encontros de hoje (Regra Unilateral)
$listaEncontrosBrasil = [];
$listaEncontrosGlobal = [];
$languageIdsHojeBrasil = [];
$languageIdsHojeGlobal = [];

foreach ($meetings as $m) {
    $com = $m['comunidade'] ?? 'brasil';
    $isGlobal = ($com === 'global');
    
    // Lista para Brasil (recebe todos)
    $prefix = $isGlobal ? '🌐 ' : '';
    $listaEncontrosBrasil[] = "{$prefix}{$m['flag_emoji']} {$m['name_en']} | {$m['language_name']}";
    $languageIdsHojeBrasil[] = $m['language_id'];
    
    // Lista para Global (recebe APENAS globais, formatado em inglês)
    if ($isGlobal) {
        $listaEncontrosGlobal[] = "{$m['flag_emoji']} {$m['name_en']}";
        $languageIdsHojeGlobal[] = $m['language_id'];
    }
}
$listaFormatadaBrasil = implode("\n", $listaEncontrosBrasil);
$listaFormatadaGlobal = implode("\n", $listaEncontrosGlobal);

// 4. Pega grupos ativos com bot presente
echo "Buscando grupos ativos...<br>";
$stmtGroups = $conn->query("SELECT * FROM meetup_whatsapp_groups WHERE ativo = 1 AND bot_presente = 1");
$groups = $stmtGroups->fetchAll();
echo "Total de grupos ativos: " . count($groups) . ". Iniciando loop de disparos...<hr>";

$sucessos = 0;

foreach ($groups as $g) {
    $podeEnviar     = false;
    $comunidadeGrupo = $g['comunidade'] ?? 'brasil';
    $isGrupoGlobal = ($comunidadeGrupo === 'global');
    
    $listaFormatadaAUsar = $isGrupoGlobal ? $listaFormatadaGlobal : $listaFormatadaBrasil;
    $languageIdsHojeAUsar = $isGrupoGlobal ? $languageIdsHojeGlobal : $languageIdsHojeBrasil;

    if ($g['categoria'] === 'multi_idioma') {
        // Se for multi-idioma, só pode enviar se houver encontros na lista destinada a ele
        if (!empty($languageIdsHojeAUsar)) {
            $podeEnviar = true;
        }
    } elseif ($g['categoria'] === 'especifico' && !empty($g['language_ids'])) {
        // Se algum dos idiomas deste grupo tem encontro hoje, na lista destinada a ele
        $ids = json_decode($g['language_ids'], true);
        if (is_array($ids)) {
            $intersect = array_intersect($ids, $languageIdsHojeAUsar);
            if (!empty($intersect)) {
                $podeEnviar = true;
            }
        }
    }

    if (!$podeEnviar) continue;

    echo "Grupo '{$g['nome']}' ({$comunidadeGrupo}) QUALIFICADO. Checando templates compatíveis...<br>";

    // URL do site por comunidade
    $siteLink = ($comunidadeGrupo === 'global') ? 'viaEi.com/en/online' : 'viaEi.com/online';

    // Itera sobre os templates compatíveis com a comunidade deste grupo
    foreach ($templatesDiario as $templateDiario) {
        $comunidadeTemplate = $templateDiario['comunidade_alvo'] ?? 'brasil';
        $compativel = ($comunidadeTemplate === 'ambos') || ($comunidadeTemplate === $comunidadeGrupo);

        if (!$compativel) {
            echo "&nbsp;&nbsp;-&gt; Template ID {$templateDiario['id']} ({$comunidadeTemplate}): incompatível com grupo {$comunidadeGrupo}. Pulando.<br>";
            continue;
        }

        // Anti-duplicidade: já enviou este template hoje para este grupo?
        $stmtCheck = $conn->prepare("SELECT id FROM meetup_whatsapp_logs WHERE grupo_id = ? AND template_id = ? AND data_disparo = ? AND meeting_id = 0");
        $stmtCheck->execute([$g['id'], $templateDiario['id'], $dataDisparo]);

        if ($stmtCheck->rowCount() > 0 && !isset($_GET['force'])) {
            echo "&nbsp;&nbsp;-&gt; ⏭️ Pulando Template ID {$templateDiario['id']}: Resumo já enviado hoje para '{$g['nome']}'.<br>";
            continue;
        }

        echo "&nbsp;&nbsp;-&gt; Template ID {$templateDiario['id']} ({$comunidadeTemplate}): Tudo limpo! Enviando...<br>";
        flush();

        // Substitui variáveis mágicas
        $textoFinal = str_replace('{LISTA_ENCONTROS}', $listaFormatadaAUsar, $templateDiario['template_texto']);
        $textoFinal = str_replace('{SITE_LINK}',       $siteLink,             $textoFinal);
        $textoFinal = str_replace('{HOST_LINK}',       'viaEi.com/equipe/',   $textoFinal);

        // Envia para o motor do Baileys
        $inicioCurl = microtime(true);
        $result     = enviarWhatsApp($g['group_id'], $textoFinal, 'meetup_cron_diario');
        $httpcode   = $result['httpCode'];
        $tempoGasto = round(microtime(true) - $inicioCurl, 2);
        $response   = json_encode($result);

        if ($httpcode >= 200 && $httpcode < 300) {
            $stmtLog = $conn->prepare("INSERT INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo) VALUES (?, 0, ?, ?)");
            $stmtLog->execute([$g['id'], $templateDiario['id'], $dataDisparo]);

            echo "&nbsp;&nbsp;-&gt; ✅ Sucesso! Enviado em {$tempoGasto}s (Status: {$httpcode}). Log registrado.<br>";
            $sucessos++;
        } else {
            echo "&nbsp;&nbsp;-&gt; ❌ Erro na API HTTP {$httpcode}. (Demorou {$tempoGasto}s) Resposta: " . htmlspecialchars($response) . "<br>";
            if ($httpcode >= 400) {
                echo "&nbsp;&nbsp;-&gt; ⚠️ Pausando 5 segundos para a API respirar após o erro...<br>";
                sleep(5);
            }
        }
    } // fim foreach templatesDiario
}

echo "<h3>Varredura concluída! {$sucessos} Resumos do Dia enviados hoje.</h3>";
?>
