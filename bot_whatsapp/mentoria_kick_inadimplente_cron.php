<?php
/**
 * ============================================================
 * CRON: Auto-kick de Inadimplentes do Our Classes
 * ============================================================
 * Frequência: 1x/dia às 00:00 BRT (chamado pelo master_cron.php)
 *
 * Condições para kickar um aluno:
 *  1. status_aluno = 'Ativo' (ainda no grupo)
 *  2. status_pagamento <> 'Pago' (não pagou)
 *  3. proximo_vencimento <= HOJE (vencimento chegou ou passou)
 *  4. A mensagem dias_antes=0 foi enviada HOJE (confirma que é o dia do kick)
 *
 * Ações:
 *  - Remove do grupo Our Classes via API Baileys
 *  - Atualiza status_aluno para 'Suspenso' no banco
 *  - Registra tudo em mentoria_auto_logs (com detalhes verbosos)
 *  - Logs no output mostrando CADA passo (nenhuma ação silenciosa)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

@ini_set('display_errors', 0);

$token_secreto = '83x9aZ2pLQw1';
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$conn = connectDB();

// Garante que a tabela de logs exista
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS mentoria_auto_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(50) NOT NULL,
            data_execucao DATE NOT NULL,
            membro_jid VARCHAR(50) NULL,
            detalhes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (tipo, data_execucao)
        )
    ");
} catch (Exception $e) {}

$hoje        = date('Y-m-d');
$totalKicked = 0;
$forcar      = isset($_GET['force']);

// ----------------------------------------------------------------
// Anti-duplicidade global por data
// ----------------------------------------------------------------
$logTipoGlobal = 'kick_inadimplente_run';
$checkGlobal   = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = ? AND data_execucao = ?");
$checkGlobal->execute([$logTipoGlobal, $hoje]);
if ($checkGlobal->rowCount() > 0 && !$forcar) {
    echo "⏭️ Kick de inadimplentes já rodou hoje ({$hoje}). Use ?force=1 para forçar.\n";
    exit;
}

echo "\n🔔 Iniciando Auto-kick de Inadimplentes do Our Classes...\n";
echo "📅 Data de referência: {$hoje}\n";
echo "⚙️  Modo: " . ($forcar ? "FORÇADO (ignora logs anteriores)" : "Normal") . "\n\n";

// ----------------------------------------------------------------
// Diagnóstico: mostra alunos que poderiam ser kickados (sem filtro de status)
// ----------------------------------------------------------------
$stmtDiag = $conn->query("
    SELECT nome, status_aluno, status_pagamento, proximo_vencimento,
           DATEDIFF(CURRENT_DATE, proximo_vencimento) AS dias_atrasados
    FROM mentoria_alunos
    WHERE status_aluno = 'Ativo'
      AND status_pagamento <> 'Pago'
    ORDER BY dias_atrasados DESC
");
$diagAlunos = $stmtDiag->fetchAll(PDO::FETCH_ASSOC);

echo "📊 Candidatos a kick (Ativos, não pagos):\n";
if (empty($diagAlunos)) {
    echo "   — Nenhum aluno ativo inadimplente no momento.\n";
} else {
    foreach ($diagAlunos as $d) {
        $atraso = (int)$d['dias_atrasados'];
        $icone  = $atraso >= 0 ? "⏰" : "🔜";
        echo "   {$icone} {$d['nome']} | status_pag={$d['status_pagamento']} | vence={$d['proximo_vencimento']} | atraso={$atraso}d\n";
    }
}
echo "\n";

// ----------------------------------------------------------------
// Busca idiomas ativos
// ----------------------------------------------------------------
$langs = $conn->query("SELECT lang_id FROM mentoria_langs WHERE ativo = 1 ORDER BY lang_id ASC")
              ->fetchAll(PDO::FETCH_COLUMN);

if (empty($langs)) {
    die("⚠️ Nenhum idioma ativo encontrado em mentoria_langs.\n");
}

foreach ($langs as $lang) {
    echo "🌐 Processando idioma: [{$lang}]\n";
    echo "   ─────────────────────────────────────\n";

    // ----------------------------------------------------------------
    // Busca alunos elegíveis para kick neste idioma:
    // - Ativos, não pagos
    // - Vencimento JÁ PASSOU (>= 1 dia de atraso)
    //   NOTA: no dia do vencimento (dias_atrasados = 0) apenas o aviso é enviado.
    //   O kick só ocorre na virada de meia-noite do dia seguinte (dias_atrasados >= 1).
    // ----------------------------------------------------------------
    $stmtAlunos = $conn->prepare("
        SELECT ma.*,
               DATEDIFF(CURRENT_DATE, ma.proximo_vencimento) AS dias_em_atraso
        FROM mentoria_alunos ma
        WHERE ma.status_aluno = 'Ativo'
          AND ma.status_pagamento <> 'Pago'
          AND DATEDIFF(CURRENT_DATE, ma.proximo_vencimento) >= 1
          AND (
              ma.lang_id = ?
              OR (? = 'en' AND (ma.lang_id IS NULL OR ma.lang_id = ''))
          )
    ");
    $stmtAlunos->execute([$lang, $lang]);
    $alunos = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);

    if (empty($alunos)) {
        echo "   ✅ Nenhum aluno elegível para kick em [{$lang}].\n\n";
        continue;
    }

    echo "   👥 " . count($alunos) . " aluno(s) elegível(is):\n";
    foreach ($alunos as $a) {
        echo "      → {$a['nome']} (pag={$a['status_pagamento']}, atraso={$a['dias_em_atraso']}d)\n";
    }

    // ----------------------------------------------------------------
    // Busca a mensagem dias_antes=0 para confirmar que o aviso foi enviado
    // ----------------------------------------------------------------
    $stmtMsg = $conn->prepare("
        SELECT id, cenario FROM mentoria_mensagens
        WHERE dias_antes = 0 AND ativo = 1
          AND (lang_id = ? OR lang_id = 'en')
        ORDER BY CASE WHEN lang_id = ? THEN 0 ELSE 1 END ASC
        LIMIT 1
    ");
    $stmtMsg->execute([$lang, $lang]);
    $msgRow = $stmtMsg->fetch(PDO::FETCH_ASSOC);

    if (!$msgRow) {
        echo "   ⚠️  Nenhuma mensagem com dias_antes=0 ativa encontrada para [{$lang}].\n";
        echo "       O kick só ocorre se o aviso de suspensão foi disparado. Pulando idioma.\n\n";
        continue;
    }

    echo "   📨 Mensagem de referência: ID={$msgRow['id']} cenário=\"{$msgRow['cenario']}\"\n";

    // ----------------------------------------------------------------
    // Busca o JID do Our Classes para este idioma via API Baileys
    // ----------------------------------------------------------------
    echo "   🔗 Consultando JID do Our Classes na API Baileys...\n";
    $config = getMentoriaConfig($lang);
    $ourClassesJid = $config['groups']['our_classes']['jid'] ?? null;

    if (!$ourClassesJid) {
        echo "   ❌ Grupo Our Classes NÃO configurado para [{$lang}] (retorno vazio da API).\n";
        echo "       Verifique se o Baileys está online e se o JID está salvo no painel.\n\n";
        continue;
    }

    echo "   🏠 Our Classes JID: {$ourClassesJid}\n\n";

    // ----------------------------------------------------------------
    // Processa cada aluno
    // ----------------------------------------------------------------
    foreach ($alunos as $aluno) {
        $alunoId   = $aluno['id'];
        $alunoNome = $aluno['nome'];
        $atraso    = (int)$aluno['dias_em_atraso'];
        $telefone  = preg_replace('/\D/', '', $aluno['telefone'] ?? '');
        if (strlen($telefone) <= 11) {
            $telefone = "55" . $telefone;
        }
        $alunoJid = $telefone . '@s.whatsapp.net';
        $logTipo  = 'kick_inadimplente_' . $lang;

        echo "   👤 Avaliando: {$alunoNome} | JID: {$alunoJid}\n";

        // Anti-duplicidade por aluno
        $checkAluno = $conn->prepare("
            SELECT id FROM mentoria_auto_logs
            WHERE tipo = ? AND data_execucao = ? AND membro_jid = ?
        ");
        $checkAluno->execute([$logTipo, $hoje, $alunoJid]);
        if ($checkAluno->rowCount() > 0 && !$forcar) {
            echo "      ⏭️  Já foi processado hoje. Pulando.\n\n";
            continue;
        }

        // Verifica se a mensagem dias_antes=0 foi enviada hoje
        $stmtCheck = $conn->prepare("
            SELECT id FROM mentoria_logs
            WHERE aluno_id = ? AND mensagem_id = ? AND data_disparo = ?
        ");
        $stmtCheck->execute([$alunoId, $msgRow['id'], $hoje]);
        $mensagemEnviadaHoje = ($stmtCheck->rowCount() > 0);

        if (!$mensagemEnviadaHoje && !$forcar) {
            echo "      ⏳ Mensagem de suspensão (ID={$msgRow['id']}) ainda NÃO foi enviada hoje.\n";
            echo "         O cron de cobrança precisa rodar antes. Pulando.\n\n";
            continue;
        }

        if ($mensagemEnviadaHoje) {
            echo "      ✅ Aviso de suspensão confirmado no log (enviado hoje).\n";
        } else {
            echo "      ⚡ Modo FORCE: pulando verificação de mensagem.\n";
        }

        echo "      🚪 Chamando removerDoGrupo({$ourClassesJid}, [{$alunoJid}])...\n";

        try {
            $resRemove = removerDoGrupo($ourClassesJid, [$alunoJid]);

            $httpCode = $resRemove['httpCode'] ?? 0;
            $success  = ($resRemove['success'] ?? false) || $httpCode === 200;
            $erro     = $resRemove['error'] ?? json_encode($resRemove['data'] ?? $resRemove);

            echo "      📡 Resposta API: HTTP {$httpCode} | success=" . ($success ? 'true' : 'false') . "\n";

            if ($success) {
                // Atualiza status do aluno para 'Suspenso'
                $conn->prepare("
                    UPDATE mentoria_alunos SET status_aluno = 'Suspenso' WHERE id = ?
                ")->execute([$alunoId]);

                // Registra no log
                $conn->prepare("
                    INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $logTipo, $hoje, $alunoJid,
                    json_encode([
                        'status'         => 'kicked',
                        'aluno_id'       => $alunoId,
                        'aluno_nome'     => $alunoNome,
                        'lang'           => $lang,
                        'grupo'          => 'our_classes',
                        'grupo_jid'      => $ourClassesJid,
                        'dias_em_atraso' => $atraso,
                        'http_code'      => $httpCode,
                        'finished_at'    => date('Y-m-d H:i:s'),
                    ])
                ]);

                $totalKicked++;
                echo "      ✅ REMOVIDO com sucesso. status_aluno atualizado para 'Suspenso'.\n\n";

            } else {
                // Registra falha
                $conn->prepare("
                    INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $logTipo, $hoje, $alunoJid,
                    json_encode([
                        'status'     => 'failed',
                        'aluno_id'   => $alunoId,
                        'aluno_nome' => $alunoNome,
                        'lang'       => $lang,
                        'http_code'  => $httpCode,
                        'error'      => $erro,
                        'failed_at'  => date('Y-m-d H:i:s'),
                    ])
                ]);

                echo "      ❌ FALHA ao remover. Resposta: {$erro}\n";
                echo "         Possíveis causas: aluno já não estava no grupo, API offline, JID errado.\n\n";
            }

        } catch (Exception $e) {
            echo "      💥 EXCEÇÃO: " . $e->getMessage() . "\n\n";
        }
    }
}

// ----------------------------------------------------------------
// Marca que o script rodou hoje
// ----------------------------------------------------------------
try {
    $conn->prepare("
        INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes)
        VALUES (?, ?, ?)
    ")->execute([
        $logTipoGlobal, $hoje,
        json_encode(['total_kickados' => $totalKicked, 'finished_at' => date('Y-m-d H:i:s')])
    ]);
} catch (Exception $e) {
    echo "⚠️ Erro ao registrar log global: " . $e->getMessage() . "\n";
}

echo "═══════════════════════════════════════\n";
echo "🏁 Concluído! Total removidos do Our Classes hoje: {$totalKicked}.\n";
?>
