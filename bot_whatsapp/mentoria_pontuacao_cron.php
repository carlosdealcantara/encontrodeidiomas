<?php
/**
 * CRON: Ranking Unificado da Mentoria (Student of the Day + Social) — Multi-idioma
 * Frequência: 1x/dia à meia-noite
 * Calcula e posta o ranking separadamente para cada idioma ativo.
 */
require_once __DIR__ . '/../config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$dry_run = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';
if ($dry_run) {
    echo "🔍 MODO DRY-RUN ATIVADO: Nenhuma mensagem será disparada no WhatsApp e logs não serão marcados como enviados.\n\n";
}

$conn = connectDB();

$conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_auto_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        data_execucao DATE NOT NULL,
        membro_jid VARCHAR(100) NULL,
        detalhes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_execucao (tipo, data_execucao, membro_jid)
    )
");

$conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_daily_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_jid VARCHAR(100) NOT NULL,
        member_name VARCHAR(255) NOT NULL,
        score_date DATE NOT NULL,
        dedication_pts INT DEFAULT 0,
        social_msgs INT DEFAULT 0,
        social_reacts INT DEFAULT 0,
        lang_id VARCHAR(10) DEFAULT 'en',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_member_date (member_jid, score_date),
        INDEX idx_score_date (score_date),
        INDEX idx_lang_date (lang_id, score_date)
    )
");

// Adiciona coluna lang_id caso não exista (migração segura)
try { $conn->exec("ALTER TABLE mentoria_daily_scores ADD COLUMN lang_id VARCHAR(10) DEFAULT 'en' AFTER social_reacts"); } catch (Exception $e) {}
try { $conn->exec("ALTER TABLE mentoria_desafio_streaks ADD COLUMN member_name VARCHAR(255) NULL"); } catch (Exception $e) {}

$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');

// Busca todos os idiomas ativos
$langs = $conn->query("SELECT lang_id FROM mentoria_langs WHERE ativo = 1 ORDER BY lang_id ASC")->fetchAll(PDO::FETCH_COLUMN);

if (empty($langs)) {
    die("Nenhum idioma ativo encontrado em mentoria_langs.\n");
}

$GROUP_EMOJIS = [
    'pronunciation' => '🗣️',
    'desafio'       => '📚',
    'music'         => '🎶',
    'games'         => '🧩',
    'vocabulary'    => '📒',
    'protocolo_1001'=> '🔒'
];

// Pré-carrega atividade uma única vez (independe de idioma)
$activity = fetchBaileysActivity($ontem);

foreach ($langs as $lang) {
    echo "\n🌐 Processando ranking para idioma: [{$lang}]\n";

    // Anti-duplicidade por idioma (ignorado em modo dry_run)
    $logType = 'ranking_unificado_' . $lang;
    $check   = $conn->prepare("SELECT id, detalhes FROM mentoria_auto_logs WHERE tipo = ? AND data_execucao = ?");
    $check->execute([$logType, $ontem]);
    $rowCheck = $check->fetch(PDO::FETCH_ASSOC);
    if ($rowCheck && !isset($_GET['force']) && !$dry_run) {
        $det    = json_decode($rowCheck['detalhes'] ?? '', true);
        $status = $det['status'] ?? 'sent';
        if ($status === 'sent') {
            echo "  ⏭️ Ranking [{$lang}] já postado para esta data ($ontem). Use &force=1 para forçar.\n";
            continue;
        }
    }

    $config = getMentoriaConfig($lang);

    $targetGroup = $config['groups']['the_lounge']['jid'] ?? null;
    if (!$targetGroup) {
        echo "  ❌ Grupo alvo (The Lounge) não configurado para [{$lang}]. Pulando.\n";
        continue;
    }

    $adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";

    // -------------------------------------------------------
    // COLETA DE ATIVIDADE SOCIAL (grupos deste idioma)
    // -------------------------------------------------------
    $memberStats  = [];
    $rankingMsgs  = [];
    $rankingReacts= [];

    if (!empty($config['groups'])) {
        foreach ($config['groups'] as $groupKey => $groupData) {
            $groupJid = $groupData['jid'] ?? '';
            if (!$groupJid) continue;

            $groupMembers = fetchGroupMembers($groupJid);
            $groupAdmins  = [];
            foreach ($groupMembers as $m) {
                if (!empty($m['admin'])) $groupAdmins[] = preg_replace('/:\d+@/', '@', $m['id']);
            }

            if (isset($activity[$groupJid])) {
                foreach ($activity[$groupJid] as $memberJid => $data) {
                    $cleanMemberJid = preg_replace('/:\d+@/', '@', $memberJid);
                    if ($cleanMemberJid === preg_replace('/:\d+@/', '@', $adminJid)) continue;
                    if (in_array($cleanMemberJid, $groupAdmins)) continue;
                    if (str_ends_with($memberJid, '@g.us')) continue;

                    $nome = trim($data['name'] ?? 'Unknown');
                    if ($nome === 'Unknown' || empty($nome)) {
                        $stmtName = $conn->prepare("SELECT nome FROM mentoria_alunos WHERE telefone = ? AND nome IS NOT NULL AND nome != '' AND (lang_id = ? OR lang_id IS NULL) LIMIT 1");
                        $phoneOnly = preg_replace('/\D/', '', explode('@', $memberJid)[0]);
                        $stmtName->execute([$phoneOnly, $lang]);
                        $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
                        if ($rowName) {
                            $nome = $rowName['nome'];
                        } else {
                            $stmtName2 = $conn->prepare("SELECT member_name FROM mentoria_desafio_streaks WHERE member_jid = ? AND member_name IS NOT NULL AND member_name != '' LIMIT 1");
                            $stmtName2->execute([$memberJid]);
                            $rowName2 = $stmtName2->fetch(PDO::FETCH_ASSOC);
                            if ($rowName2) $nome = $rowName2['member_name'];
                        }
                    }

                    if (stripos($nome, 'Staff') !== false || stripos($nome, 'Test') !== false) continue;

                    // Track Social
                    if (!isset($rankingMsgs[$memberJid])) {
                        $rankingMsgs[$memberJid] = ['name' => $nome, 'score' => 0];
                    } else {
                        if (($rankingMsgs[$memberJid]['name'] === 'Unknown' || $rankingMsgs[$memberJid]['name'] === 'Desconhecido')
                            && $nome !== 'Unknown' && $nome !== 'Desconhecido') {
                            $rankingMsgs[$memberJid]['name'] = $nome;
                        }
                    }
                    $rankingMsgs[$memberJid]['score'] += ($data['messages'] ?? 0) + ($data['images_sent'] ?? 0) + ($data['audios_sent'] ?? 0);

                    if (!isset($rankingReacts[$memberJid])) {
                        $rankingReacts[$memberJid] = ['name' => $nome, 'score' => 0];
                    } else {
                        if (($rankingReacts[$memberJid]['name'] === 'Unknown' || $rankingReacts[$memberJid]['name'] === 'Desconhecido')
                            && $nome !== 'Unknown' && $nome !== 'Desconhecido') {
                            $rankingReacts[$memberJid]['name'] = $nome;
                        }
                    }
                    $rankingReacts[$memberJid]['score'] += $data['reactions_given'] ?? 0;
                }
            }
        }
    }

    // -------------------------------------------------------
    // DEDICAÇÃO: Base Desafio (5 pts — quem completou o desafio)
    // -------------------------------------------------------
    // Para filtrar por idioma: verifica se o membro está nos grupos deste idioma
    $desafioJid    = $config['groups']['desafio']['jid'] ?? null;
    $desafioMembers = $desafioJid ? fetchGroupMembers($desafioJid) : [];
    $desafioMemberJids = array_column($desafioMembers, 'id');

    $stmtStreak = $conn->prepare("SELECT member_jid, member_name FROM mentoria_desafio_streaks WHERE last_completed_date = ?");
    $stmtStreak->execute([$ontem]);
    $streakCompleters = $stmtStreak->fetchAll(PDO::FETCH_ASSOC);

    foreach ($streakCompleters as $completer) {
        $mJid      = $completer['member_jid'];
        $cleanMJid = preg_replace('/:\d+@/', '@', $mJid);

        // Ignora admin
        if ($cleanMJid === preg_replace('/:\d+@/', '@', $adminJid)) continue;

        // Só processa se este membro pertence ao grupo de desafio deste idioma
        // (evita contabilizar alunos de outro idioma)
        if ($desafioJid && !empty($desafioMemberJids) && !in_array($mJid, $desafioMemberJids)) continue;

        $mName = $completer['member_name'] ?? 'Unknown';
        if ($mName === 'Unknown' || empty(trim($mName))) {
            if (isset($rankingMsgs[$mJid]) && $rankingMsgs[$mJid]['name'] !== 'Unknown') {
                $mName = $rankingMsgs[$mJid]['name'];
            } elseif (isset($rankingReacts[$mJid]) && $rankingReacts[$mJid]['name'] !== 'Unknown') {
                $mName = $rankingReacts[$mJid]['name'];
            } else {
                $stmtName = $conn->prepare("SELECT nome FROM mentoria_alunos WHERE telefone = ? AND nome IS NOT NULL AND nome != '' LIMIT 1");
                $phoneOnly = preg_replace('/\D/', '', explode('@', $mJid)[0]);
                $stmtName->execute([$phoneOnly]);
                $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
                if ($rowName) $mName = $rowName['nome'];
            }
        }

        if (stripos($mName, 'Staff') !== false || stripos($mName, 'Test') !== false) continue;

        if (!isset($memberStats[$mJid])) {
            $memberStats[$mJid] = ['name' => $mName, 'total_pts' => 0, 'emojis' => []];
        }
        $memberStats[$mJid]['total_pts'] += 5;
        if (!in_array('📚', $memberStats[$mJid]['emojis'])) {
            $memberStats[$mJid]['emojis'][] = '📚';
        }
        if ($memberStats[$mJid]['name'] === 'Unknown' && $mName !== 'Unknown' && trim($mName) !== '') {
            $memberStats[$mJid]['name'] = $mName;
        }
    }

    // -------------------------------------------------------
    // DEDICAÇÃO: Pontos Manuais (!1 a !5) filtrados por idioma
    // -------------------------------------------------------

    // Obtém todos os JIDs dos grupos deste idioma para filtrar
    $langGroupJids = array_values(array_filter(array_column($config['groups'] ?? [], 'jid')));

    $manualPoints = [];
    if (!empty($langGroupJids)) {
        $stmtPts = $conn->prepare("
            SELECT member_jid, member_name, group_key, SUM(points) as group_pts
            FROM mentoria_dedicated_pts
            WHERE date = ? AND group_jid IN (" . implode(',', array_fill(0, count($langGroupJids), '?')) . ")
            GROUP BY member_jid, group_key
        ");
        $paramsManual = array_merge([$ontem], $langGroupJids);
        $stmtPts->execute($paramsManual);
        $manualPoints = $stmtPts->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($manualPoints as $row) {
        $mJid      = $row['member_jid'];
        $cleanMJid = preg_replace('/:\d+@/', '@', $mJid);
        if ($cleanMJid === preg_replace('/:\d+@/', '@', $adminJid)) continue;

        $mName = $row['member_name'] ?: 'Unknown';
        if ($mName === 'Unknown') {
            if (isset($rankingMsgs[$mJid]) && $rankingMsgs[$mJid]['name'] !== 'Unknown') {
                $mName = $rankingMsgs[$mJid]['name'];
            } elseif (isset($rankingReacts[$mJid]) && $rankingReacts[$mJid]['name'] !== 'Unknown') {
                $mName = $rankingReacts[$mJid]['name'];
            } else {
                $stmtName = $conn->prepare("SELECT nome FROM mentoria_alunos WHERE telefone = ? AND nome IS NOT NULL AND nome != '' LIMIT 1");
                $phoneOnly = preg_replace('/\D/', '', explode('@', $mJid)[0]);
                $stmtName->execute([$phoneOnly]);
                $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
                if ($rowName) $mName = $rowName['nome'];
            }
        }

        if (stripos($mName, 'Staff') !== false || stripos($mName, 'Test') !== false) continue;

        if (!isset($memberStats[$mJid])) {
            $memberStats[$mJid] = ['name' => $mName, 'total_pts' => 0, 'emojis' => []];
        }
        $memberStats[$mJid]['total_pts'] += (int)$row['group_pts'];
        $emoji = $GROUP_EMOJIS[$row['group_key']] ?? '⭐';
        if (!in_array($emoji, $memberStats[$mJid]['emojis'])) {
            $memberStats[$mJid]['emojis'][] = $emoji;
        }
    }

    // -------------------------------------------------------
    // AULA / ATTENDANCE (20 pts) — filtrado pelo lang_id
    // -------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT a.member_jid, a.member_name, s.session_type,
               (SELECT COUNT(*) FROM class_attendances WHERE schedule_id = a.schedule_id AND aula_date = a.aula_date) as quorum
        FROM class_attendances a
        LEFT JOIN class_schedule s ON a.schedule_id = s.id
        WHERE a.aula_date = ? AND (s.lang_id = ? OR s.lang_id IS NULL)
    ");
    $stmt->execute([$ontem, $lang]);
    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attendees as $att) {
        $mJid = $att['member_jid'];
        if ($mJid === $adminJid) continue;
        if (!isset($memberStats[$mJid])) {
            $memberStats[$mJid] = ['name' => $att['member_name'], 'total_pts' => 0, 'emojis' => []];
        }
        $memberStats[$mJid]['total_pts'] += 20;
        array_unshift($memberStats[$mJid]['emojis'], '🖥️');
    }

    // -------------------------------------------------------
    // STUDENT OF THE DAY
    // -------------------------------------------------------
    $memberStats = array_filter($memberStats, fn($m) => $m['total_pts'] > 0);
    uasort($memberStats, fn($a, $b) => $b['total_pts'] <=> $a['total_pts']);

    // Defaults de string "sem participantes" por idioma
    $noParticipantsStr = ($lang === 'es') ? "Sin participantes ayer." : "No participants yesterday.";
    $noOtherStr        = ($lang === 'es') ? "Sin otros participantes ayer." : "No other participants yesterday.";

    $studentOfTheDayStr = '';
    $othersStr          = '';

    if (!empty($memberStats)) {
        $maxPts  = reset($memberStats)['total_pts'];
        $winners = array_filter($memberStats, fn($m) => $m['total_pts'] === $maxPts);
        $losers  = array_filter($memberStats, fn($m) => $m['total_pts'] < $maxPts);

        if (count($winners) === 1) {
            $w = reset($winners);
            $studentOfTheDayStr = "🏆 *{$w['name']}* — " . implode('', $w['emojis']) . " — *{$w['total_pts']} pts*";
        } else {
            $tieLabel  = ($lang === 'es') ? "🏆 *¡Empate!*" : "🏆 *It's a tie!*";
            $tiedNames = [];
            foreach ($winners as $w) {
                $tiedNames[] = "*{$w['name']}* — " . implode('', $w['emojis']) . " — *{$w['total_pts']} pts*";
            }
            $studentOfTheDayStr = $tieLabel . "\n" . implode("\n", $tiedNames);
        }

        $startPos = count($winners) + 1;
        $i        = $startPos;
        foreach ($losers as $jid => $data) {
            $emojisStr  = implode('', $data['emojis']);
            $nomeStr    = trim($data['name']) ?: 'Unknown';
            $othersStr .= "{$i}. *{$nomeStr}* — {$emojisStr} — {$data['total_pts']} pts\n";
            $i++;
        }
    } else {
        $studentOfTheDayStr = $noParticipantsStr;
    }

    $othersStr = $othersStr ?: $noOtherStr;

    // Legenda
    $defaultLegends = [
        'es' => "🖥️ Asistió a Clase (20 pts)\n🗣️ Lectura en voz alta (5 pts)\n📚 Desafío (5 pts)\n🎶 Music Lab (4 pts)\n🧩 Juegos (2 pts)\n👏 Compromiso con la sesión (5 pts)\n📒 ¡Nueva palabra! (1 pt)",
        'en' => "🖥️ Attended Class (20 pts)\n🗣️ Reading out loud (5 pts)\n📚 Challenge (5 pts)\n🎶 Music Lab (4 pts)\n🧩 Games (2 pts)\n👏 Session commitment (5 pts)\n📒 New word! (1 pt)",
    ];
    $defaultLegend = $defaultLegends[$lang] ?? $defaultLegends['en'];
    $legendStr     = !empty($config['templates']['ranking_legend']) ? $config['templates']['ranking_legend'] : $defaultLegend;

    // -------------------------------------------------------
    // SOCIAL (Word Slingers & Emoji Gang)
    // -------------------------------------------------------
    $rankingMsgs   = array_filter($rankingMsgs,   fn($item) => $item['score'] > 0);
    $rankingReacts = array_filter($rankingReacts, fn($item) => $item['score'] > 0);

    uasort($rankingMsgs,   fn($a, $b) => $b['score'] <=> $a['score']);
    uasort($rankingReacts, fn($a, $b) => $b['score'] <=> $a['score']);

    $top5Msgs   = array_slice($rankingMsgs,   0, 5, true);
    $top5Reacts = array_slice($rankingReacts, 0, 5, true);

    $medals = ['🥇', '🥈', '🥉'];

    $noMsgsStr   = ($lang === 'es') ? "Sin mensajes ayer.\n" : "No messages yesterday.\n";
    $noReactsStr = ($lang === 'es') ? "Sin reacciones ayer.\n" : "No reactions yesterday.\n";
    $msgsLabel   = ($lang === 'es') ? "mensajes" : "messages";
    $reactsLabel = ($lang === 'es') ? "reacciones" : "reactions";

    $msgList = '';
    $i       = 0;
    foreach ($top5Msgs as $jid => $data) {
        $rankStr  = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
        $nomeStr  = trim($data['name']) ?: 'Unknown';
        $msgList .= $rankStr . " *{$nomeStr}* — {$data['score']} {$msgsLabel}\n";
        $i++;
    }

    $reactList = '';
    $i         = 0;
    foreach ($top5Reacts as $jid => $data) {
        $rankStr    = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
        $nomeStr    = trim($data['name']) ?: 'Unknown';
        $reactList .= $rankStr . " *{$nomeStr}* — {$data['score']} {$reactsLabel}\n";
        $i++;
    }

    $wordSlingersList = $msgList    ?: $noMsgsStr;
    $emojiGangList    = $reactList  ?: $noReactsStr;

    // -------------------------------------------------------
    // SALVAR PONTOS DO DIA NO BANCO (com lang_id)
    // -------------------------------------------------------
    foreach ($memberStats as $jid => $data) {
        $msgs   = $rankingMsgs[$jid]['score']   ?? 0;
        $reacts = $rankingReacts[$jid]['score'] ?? 0;
        $stmtSave = $conn->prepare("
            INSERT INTO mentoria_daily_scores (member_jid, member_name, score_date, dedication_pts, social_msgs, social_reacts, lang_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                member_name    = VALUES(member_name),
                dedication_pts = VALUES(dedication_pts),
                social_msgs    = VALUES(social_msgs),
                social_reacts  = VALUES(social_reacts),
                lang_id        = VALUES(lang_id)
        ");
        $stmtSave->execute([$jid, $data['name'], $ontem, $data['total_pts'], $msgs, $reacts, $lang]);
    }

    // Salva quem só pontuou em social
    $allJids = array_unique(array_merge(array_keys($rankingMsgs), array_keys($rankingReacts)));
    foreach ($allJids as $jid) {
        if (isset($memberStats[$jid])) continue;
        $msgs   = $rankingMsgs[$jid]['score']   ?? 0;
        $reacts = $rankingReacts[$jid]['score'] ?? 0;
        $name   = $rankingMsgs[$jid]['name']    ?? $rankingReacts[$jid]['name'] ?? 'Unknown';
        $stmtSave = $conn->prepare("
            INSERT INTO mentoria_daily_scores (member_jid, member_name, score_date, dedication_pts, social_msgs, social_reacts, lang_id)
            VALUES (?, ?, ?, 0, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                member_name    = VALUES(member_name),
                social_msgs    = VALUES(social_msgs),
                social_reacts  = VALUES(social_reacts),
                lang_id        = VALUES(lang_id)
        ");
        $stmtSave->execute([$jid, $name, $ontem, $msgs, $reacts, $lang]);
    }

    // -------------------------------------------------------
    // MONTAGEM FINAL DAS MENSAGENS
    // -------------------------------------------------------
    $dateFormatted = ($lang === 'es')
        ? date('j \d\e F \d\e Y') // ex: 28 de septiembre de 2026
        : date('F jS, Y');         // ex: September 28th, 2026

    $defaultStudent  = ($lang === 'es')
        ? "📅 {date}\n\n⭐ *ESTUDIANTE DEL DÍA*\n\n{student_of_the_day}\n\n*Otros estudiantes:*\n{other_students}\n\n📖 *Leyenda:*\n{legend}"
        : "📅 {date}\n\n⭐ *STUDENT OF THE DAY*\n\n{student_of_the_day}\n\n*Other students:*\n{other_students}\n\n📖 *Legend:*\n{legend}";
    $defaultMessenger = ($lang === 'es')
        ? "📅 {date}\n\n💬 *TOP MENSAJERO*\n_¿Quién envió más mensajes hoy?_\n\n{top_messenger_list}"
        : "📅 {date}\n\n💬 *TOP MESSENGER*\n_Who sent the most messages today?_\n\n{top_messenger_list}";
    $defaultReactor  = ($lang === 'es')
        ? "📅 {date}\n\n❤️ *TOP REACCIONADOR*\n_¿Quién dio más reacciones hoy?_\n\n{top_reactor_list}"
        : "📅 {date}\n\n❤️ *TOP REACTOR*\n_Who gave the most reactions today?_\n\n{top_reactor_list}";

    $tpl1 = !empty($config['templates']['ranking_student'])   ? $config['templates']['ranking_student']   : $defaultStudent;
    $tpl2 = !empty($config['templates']['ranking_messenger']) ? $config['templates']['ranking_messenger'] : $defaultMessenger;
    $tpl3 = !empty($config['templates']['ranking_reactor'])   ? $config['templates']['ranking_reactor']   : $defaultReactor;

    $msg1 = str_replace(
        ['{date}', '{student_of_the_day}', '{other_students}', '{legend}'],
        [$dateFormatted, $studentOfTheDayStr, $othersStr, $legendStr],
        $tpl1
    );
    $msg2 = str_replace(
        ['{date}', '{top_messenger_list}'],
        [$dateFormatted, $wordSlingersList],
        $tpl2
    );
    $msg3 = str_replace(
        ['{date}', '{top_reactor_list}'],
        [$dateFormatted, $emojiGangList],
        $tpl3
    );

    // Verifica se há mensagens sociais reais para enviar (evita mandar mensagens vazias como "Sin mensajes ayer")
    $hasMsgs   = !empty($top5Msgs);
    $hasReacts = !empty($top5Reacts);

    if ($dry_run) {
        echo "  [DRY-RUN] Mensagem 1 (Estudante do Dia) seria enviada para {$targetGroup}:\n" . str_repeat('-', 40) . "\n{$msg1}\n" . str_repeat('-', 40) . "\n";
        if ($hasMsgs) {
            echo "  [DRY-RUN] Mensagem 2 (Top Mensagens) seria enviada para {$targetGroup}:\n" . str_repeat('-', 40) . "\n{$msg2}\n" . str_repeat('-', 40) . "\n";
        } else {
            echo "  ℹ️ [DRY-RUN] Mensagem 2 (Top Mensagens) ignorada pois não houve mensagens ontem.\n";
        }
        if ($hasReacts) {
            echo "  [DRY-RUN] Mensagem 3 (Top Reações) seria enviada para {$targetGroup}:\n" . str_repeat('-', 40) . "\n{$msg3}\n" . str_repeat('-', 40) . "\n";
        } else {
            echo "  ℹ️ [DRY-RUN] Mensagem 3 (Top Reações) ignorada pois não houve reações ontem.\n";
        }
        continue;
    }

    // Trava inicial antes de disparar
    $travou = registrarInicioDisparo($conn, $logType, $ontem, null, 15);
    if (!$travou && !isset($_GET['force'])) {
        echo "  ⚠️ Ranking [{$lang}] já está sendo processado por outra instância ou já foi concluído.\n";
        continue;
    }

    $sentCount = 0;
    $result1 = enviarWhatsApp($targetGroup, $msg1, "mentoria_ranking_student_{$lang}");
    $sentCount++;

    if ($hasMsgs) {
        sleep(1);
        $result2 = enviarWhatsApp($targetGroup, $msg2, "mentoria_ranking_messenger_{$lang}");
        $sentCount++;
    } else {
        echo "  ℹ️ Mensagem 2 (Top Mensagens) ignorada: sem mensagens ontem no idioma [{$lang}].\n";
    }

    if ($hasReacts) {
        sleep(1);
        $result3 = enviarWhatsApp($targetGroup, $msg3, "mentoria_ranking_reactor_{$lang}");
        $sentCount++;
    } else {
        echo "  ℹ️ Mensagem 3 (Top Reações) ignorada: sem reações ontem no idioma [{$lang}].\n";
    }

    $allSuccessful = ($result1['success'] || ($result1['httpCode'] >= 200 && $result1['httpCode'] < 300));

    if ($allSuccessful) {
        registrarConclusaoDisparo($conn, $logType, $ontem, null, [
            'stats'     => $memberStats,
            'httpCode'  => $result1['httpCode'],
            'lang'      => $lang,
            'messages_sent' => $sentCount
        ]);
        echo "  ✅ Rankings [{$lang}] enviados com sucesso ({$sentCount} mensagem(ns))!\n";
    } else {
        registrarFalhaDisparo($conn, $logType, $ontem, null, $result1['error'] ?? 'HTTP ' . $result1['httpCode']);
        echo "  ❌ Erro ao enviar ranking [{$lang}]: HTTP " . $result1['httpCode'] . " (" . ($result1['error'] ?? 'desconhecido') . ")\n";
    }
}

echo "\n🏁 Ranking Unificado Multi-idioma concluído.\n";
