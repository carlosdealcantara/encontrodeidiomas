<?php
/**
 * CRON: Cancelamento Classes (Deadline)
 * Frequência: Quanto mais frequente, melhor (ex: a cada 5 min).
 * Lógica: Dispara em qualquer run onde o deadline já passou e a aula ainda
 * não começou. O mentoria_auto_logs é o deduplicador — impede envio duplo.
 *
 * DIAGNÓSTICO: Cada execução gera uma linha em `mentoria_cron_execucoes`
 * independente de ter feito algo ou não. Se o cron não rodar, não haverá
 * entradas — isso por si só já identifica que o problema é na Hostinger.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$conn = connectDB();
$hoje = date('Y-m-d');
$diaSemana = date('N');

// Garante que a tabela de diagnóstico existe (self-bootstrapping, sem migration manual)
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS mentoria_cron_execucoes (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        cron_name   VARCHAR(100)  NOT NULL,
        executado_em DATETIME     NOT NULL,
        schedule_id INT           DEFAULT NULL,
        deadline_time DATETIME    DEFAULT NULL,
        class_time  DATETIME      DEFAULT NULL,
        diff_segundos INT         DEFAULT NULL,
        condicao_ativa TINYINT(1) DEFAULT NULL COMMENT '1=entrou no bloco de acao, 0=condição nao ativa',
        acao        VARCHAR(50)   DEFAULT NULL COMMENT 'deadline_nao_passou|aula_ja_comecou|dedup_skipped|quorum_ok|cancel_sent',
        attendees_count INT       DEFAULT NULL,
        notas       TEXT          DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Não deve travar o cron por falha de DDL
    error_log("mentoria_cron_execucoes DDL error: " . $e->getMessage());
}

/**
 * Grava uma linha de diagnóstico em mentoria_cron_execucoes.
 * É o rastro de cada execução — presente ou ausente, isso já diz muita coisa.
 */
function logCronExec(
    PDO $conn,
    string $cron,
    ?int $schedule_id,
    ?DateTime $deadlineTime,
    ?DateTime $classTime,
    ?int $diff,
    bool $condicaoAtiva,
    string $acao,
    ?int $attendees = null,
    string $notas = ''
): void {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO mentoria_cron_execucoes
             (cron_name, executado_em, schedule_id, deadline_time, class_time,
              diff_segundos, condicao_ativa, acao, attendees_count, notas)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $cron,
            $schedule_id,
            $deadlineTime ? $deadlineTime->format('Y-m-d H:i:s') : null,
            $classTime    ? $classTime->format('Y-m-d H:i:s')    : null,
            $diff,
            (int) $condicaoAtiva,
            $acao,
            $attendees,
            $notas,
        ]);
    } catch (Exception $e) {
        error_log("logCronExec error: " . $e->getMessage());
    }
}

$stmt = $conn->prepare("SELECT * FROM class_schedule WHERE day_of_week = ? AND is_active = 1");
$stmt->execute([$diaSemana]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = new DateTime();

// Pré-carrega config do Baileys UMA VEZ fora do loop (evita 9s extra por schedule)
// Se falhar, usa array vazio e os templates padrão serão usados como fallback.
$mentoriaConfig = [];
try {
    $mentoriaConfig = getMentoriaConfig();
} catch (Exception $e) {
    error_log("quorum_cron: falha ao carregar getMentoriaConfig: " . $e->getMessage());
}

// Se não houver nenhum schedule para hoje, registra mesmo assim — confirma que o cron rodou
if (empty($schedules)) {
    logCronExec($conn, 'quorum', null, null, null, null, false, 'sem_schedule',
        null, "Nenhum schedule ativo para dia_semana=$diaSemana");
}

foreach ($schedules as $s) {
    $classTime    = new DateTime($hoje . ' ' . $s['start_time']);
    $deadlineTime = clone $classTime;
    $deadlineTime->modify('-1 hour');

    $diff    = $now->getTimestamp() - $deadlineTime->getTimestamp();
    $isTest  = isset($_GET['test_now']);

    // Dispara se o deadline já passou E a aula ainda não começou (ou se for teste manual).
    // Não há janela de tempo fixa: o mentoria_auto_logs (tipo='class_cancel') impede envio duplo.
    // Isso funciona como fila: qualquer run do cron depois do deadline tenta enviar,
    // mas só o primeiro que ainda não encontrar o log de duplicidade vai de fato disparar.
    $condicaoAtiva = ($diff >= 0 && $now < $classTime) || $isTest;

    if (!$condicaoAtiva) {
        // Ainda não chegou o deadline, ou a aula já passou — registra e pula
        $motivo = ($diff < 0) ? 'deadline_nao_passou' : 'aula_ja_comecou';
        logCronExec(
            $conn, 'quorum', (int)$s['id'], $deadlineTime, $classTime, $diff,
            false, $motivo, null,
            "now={$now->format('H:i:s')} deadline={$deadlineTime->format('H:i:s')} class={$classTime->format('H:i:s')}"
        );
        echo "Sessão " . $s['start_time'] . ": $motivo (diff={$diff}s).\n";
        continue;
    }

    // Verifica anti-duplicidade e adquire trava inicial ('processing')
    if (!isset($_GET['force'])) {
        $check = $conn->prepare("SELECT id, detalhes FROM mentoria_auto_logs WHERE tipo = 'class_cancel' AND data_execucao = ? AND membro_jid = ?");
        $check->execute([$hoje, (string)$s['id']]);
        $rowCheck = $check->fetch(PDO::FETCH_ASSOC);
        if ($rowCheck) {
            $det = json_decode($rowCheck['detalhes'] ?? '', true);
            $status = $det['status'] ?? 'sent';
            // Se já foi enviado com sucesso, pula
            if ($status === 'sent') {
                logCronExec(
                    $conn, 'quorum', (int)$s['id'], $deadlineTime, $classTime, $diff,
                    true, 'dedup_skipped', null, 'class_cancel ja confirmado hoje'
                );
                echo "Sessão " . $s['start_time'] . ": cancelamento já enviado e confirmado anteriormente (dedup).\n";
                continue;
            }
        }
    }

    // Conta confirmações
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM class_attendances WHERE schedule_id = ? AND aula_date = ?");
    $stmtCount->execute([$s['id'], $hoje]);
    $attendees = (int) $stmtCount->fetchColumn();

    $sessionType = $s['session_type'] ?? 'teacher_class';
    $minQuorum   = ($sessionType === 'student_practice') ? 2 : 1;

    if ($attendees < $minQuorum) {
        $lang = !empty($s['lang_id']) ? $s['lang_id'] : 'en';
        $cfg = ($lang === 'en') ? $mentoriaConfig : getMentoriaConfig($lang);
        $tplKey  = ($sessionType === 'student_practice') ? 'practice_cancel' : 'class_cancel';
        $defaultTpl = ($lang === 'es')
            ? (($sessionType === 'student_practice')
                ? "❌ *Sesión de Práctica Cancelada*\n\nLamentablemente, no alcanzamos el mínimo de confirmaciones para la sesión de práctica de las {horario} hoy. Las inscripciones están cerradas y la sesión queda cancelada. ¡Nos vemos en la próxima! 👋"
                : "❌ *Clase Cancelada*\n\nLamentablemente, no tuvimos confirmaciones para la clase de las {horario} hoy. Las inscripciones están cerradas y la clase queda cancelada. ¡Nos vemos en la próxima! 👋")
            : (($sessionType === 'student_practice')
                ? "❌ *Practice Session Cancelled*\n\nUnfortunately, we didn't get enough confirmations for the {horario} practice session today. Registrations are now closed and the session is cancelled. See you next time! 👋"
                : "❌ *Class Cancelled*\n\nUnfortunately, we didn't get any confirmations for the {horario} session today. Registrations are now closed and the class is cancelled. See you next time! 👋");
        $tpl = $cfg['templates'][$tplKey] ?? $defaultTpl;
        $msg = str_replace('{horario}', formatTime12h($classTime), $tpl);

        // 1. PRIMEIRA CONFIRMAÇÃO (Trava Inicial): registra 'processing' para impedir outros crons concorrentes
        $travou = registrarInicioDisparo($conn, 'class_cancel', $hoje, (string)$s['id'], 15);
        if (!$travou && !isset($_GET['force'])) {
            echo "Sessão " . $s['start_time'] . ": disparo em andamento por outro processo ou já concluído. Pulando.\n";
            continue;
        }

        // Executa o envio
        $resEnvio = enviarWhatsApp($s['group_jid'], $msg, 'class_cancel');

        // 2. SEGUNDA CONFIRMAÇÃO (Conclusão): confirma o envio ou reporta falha
        if ($resEnvio['success'] || ($resEnvio['httpCode'] >= 200 && $resEnvio['httpCode'] < 300)) {
            registrarConclusaoDisparo($conn, 'class_cancel', $hoje, (string)$s['id'], ['httpCode' => $resEnvio['httpCode']]);
            
            // Deleta as confirmações de presença para que não contabilize pontos na aula cancelada
            try {
                $conn->prepare("DELETE FROM class_attendances WHERE schedule_id = ? AND aula_date = ?")->execute([$s['id'], $hoje]);
            } catch (Exception $e) {
                error_log("quorum_cron: falha ao deletar presenças: " . $e->getMessage());
            }

            logCronExec(
                $conn, 'quorum', (int)$s['id'], $deadlineTime, $classTime, $diff,
                true, 'cancel_sent', $attendees,
                "minQuorum=$minQuorum tipo=$sessionType"
            );
            echo "Sessão " . $s['start_time'] . " cancelada com sucesso. Mensagem enviada e confirmada.\n";
        } else {
            // Falhou de fato na API: marca como 'failed' para permitir que o próximo run tente enviar
            registrarFalhaDisparo($conn, 'class_cancel', $hoje, (string)$s['id'], $resEnvio['error'] ?? 'HTTP ' . $resEnvio['httpCode']);
            echo "❌ Erro ao enviar cancelamento da sessão " . $s['start_time'] . ": " . ($resEnvio['error'] ?? 'Erro desconhecido') . ". Marcado para retentativa.\n";
        }

    } else {
        logCronExec(
            $conn, 'quorum', (int)$s['id'], $deadlineTime, $classTime, $diff,
            true, 'quorum_ok', $attendees,
            "minQuorum=$minQuorum tipo=$sessionType"
        );
        echo "Sessão " . $s['start_time'] . " confirmada com $attendees presentes.\n";
    }
}
echo "Deadline check finished.";
