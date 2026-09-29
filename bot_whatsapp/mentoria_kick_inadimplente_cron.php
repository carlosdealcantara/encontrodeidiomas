<?php
/**
 * ============================================================
 * CRON: Auto-kick de Inadimplentes do Our Classes
 * ============================================================
 * Frequência: 1x/dia às 00:00 BRT (chamado pelo master_cron.php)
 *
 * Lógica:
 *  - Busca alunos Ativos com status_pagamento 'Suspenso' (dias_antes <= 0,
 *    ou seja, vencimento já passou ou é hoje) que tiveram a mensagem de
 *    "Suspensão" (dias_antes = 0) enviada hoje pelo motor de cobrança.
 *  - Para cada um, aciona a remoção via API Baileys do grupo Our Classes
 *    (por idioma) e atualiza o status_aluno para 'Suspenso' no banco.
 *  - Registro completo em mentoria_auto_logs para idempotência.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

// Garante output imediato para debugging
@ob_end_clean();
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

$hoje       = date('Y-m-d');
$totalKicked = 0;

// Anti-duplicidade global por data
$logTipoGlobal = 'kick_inadimplente_run';
$checkGlobal = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = ? AND data_execucao = ?");
$checkGlobal->execute([$logTipoGlobal, $hoje]);
if ($checkGlobal->rowCount() > 0 && !isset($_GET['force'])) {
    echo "⏭️ Verificação de kick de inadimplentes já rodou hoje ($hoje). Use ?force=1 para forçar.\n";
    exit;
}

echo "\n🔔 Iniciando Auto-kick de Inadimplentes do Our Classes...\n";
echo "📅 Data de referência: $hoje\n\n";

// Busca todos os idiomas ativos
$langs = $conn->query("SELECT lang_id FROM mentoria_langs WHERE ativo = 1 ORDER BY lang_id ASC")
              ->fetchAll(PDO::FETCH_COLUMN);

if (empty($langs)) {
    die("⚠️ Nenhum idioma ativo encontrado em mentoria_langs.\n");
}

foreach ($langs as $lang) {
    echo "\n🌐 Processando idioma: [{$lang}]\n";

    // Busca alunos deste idioma que estão Ativos mas com pagamento Suspenso
    // E cujo vencimento já passou (dias até vencimento <= 0)
    $stmtAlunos = $conn->prepare("
        SELECT ma.*
        FROM mentoria_alunos ma
        WHERE ma.status_aluno = 'Ativo'
          AND ma.status_pagamento = 'Suspenso'
          AND (ma.lang_id = ? OR (? = 'en' AND (ma.lang_id IS NULL OR ma.lang_id = '')))
          AND DATEDIFF(ma.proximo_vencimento, CURRENT_DATE) <= 0
    ");
    $stmtAlunos->execute([$lang, $lang]);
    $alunos = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);

    if (empty($alunos)) {
        echo "  ✅ Nenhum aluno inadimplente para kickar no idioma [{$lang}].\n";
        continue;
    }

    // Busca a mensagem de "Suspensão" (dias_antes = 0) para verificar se foi enviada
    // Prioriza o idioma do aluno, fallback para 'en'
    $stmtMsg = $conn->prepare("
        SELECT id FROM mentoria_mensagens
        WHERE dias_antes = 0 AND ativo = 1
          AND (lang_id = ? OR lang_id = 'en')
        ORDER BY CASE WHEN lang_id = ? THEN 0 ELSE 1 END ASC
        LIMIT 1
    ");
    $stmtMsg->execute([$lang, $lang]);
    $msgRow = $stmtMsg->fetch(PDO::FETCH_ASSOC);

    // Busca o JID do Our Classes para este idioma via API Baileys
    $config = getMentoriaConfig($lang);
    $ourClassesJid = $config['groups']['our_classes']['jid'] ?? null;

    if (!$ourClassesJid) {
        echo "  ⚠️ Grupo Our Classes não configurado para o idioma [{$lang}]. Pulando.\n";
        continue;
    }

    foreach ($alunos as $aluno) {
        $alunoId   = $aluno['id'];
        $alunoNome = $aluno['nome'];
        $telefone  = preg_replace('/\D/', '', $aluno['telefone'] ?? '');
        if (strlen($telefone) <= 11) {
            $telefone = "55" . $telefone;
        }

        // Monta o JID do WhatsApp do aluno
        $alunoJid = $telefone . '@s.whatsapp.net';

        $logTipo = 'kick_inadimplente_' . $lang;

        // Verifica se este aluno já foi kickado hoje
        $checkAluno = $conn->prepare("
            SELECT id FROM mentoria_auto_logs
            WHERE tipo = ? AND data_execucao = ? AND membro_jid = ?
        ");
        $checkAluno->execute([$logTipo, $hoje, $alunoJid]);
        if ($checkAluno->rowCount() > 0 && !isset($_GET['force'])) {
            echo "  ⏭️ {$alunoNome}: já foi kickado hoje. Pulando.\n";
            continue;
        }

        // Verifica se a mensagem de suspensão (dias_antes=0) foi enviada hoje
        // Se a mensagem não existe ou não foi enviada, ainda fazemos o kick
        // pois o template já foi ativado pelo usuário
        $mensagemEnviada = false;
        if ($msgRow) {
            $stmtCheck = $conn->prepare("
                SELECT id FROM mentoria_logs
                WHERE aluno_id = ? AND mensagem_id = ? AND data_disparo = ?
            ");
            $stmtCheck->execute([$alunoId, $msgRow['id'], $hoje]);
            $mensagemEnviada = ($stmtCheck->rowCount() > 0);
        }

        if (!$mensagemEnviada && !isset($_GET['force'])) {
            echo "  ⏳ {$alunoNome}: mensagem de suspensão ainda não foi enviada hoje. O kick ocorrerá após o disparo da cobrança.\n";
            continue;
        }

        echo "  🚪 Removendo {$alunoNome} (JID: {$alunoJid}) do Our Classes [{$lang}]...\n";

        try {
            $resRemove = removerDoGrupo($ourClassesJid, [$alunoJid]);

            if (($resRemove['success'] ?? false) || ($resRemove['httpCode'] ?? 0) === 200) {
                // Atualiza status do aluno para 'Suspenso' no banco
                $conn->prepare("
                    UPDATE mentoria_alunos
                    SET status_aluno = 'Suspenso'
                    WHERE id = ?
                ")->execute([$alunoId]);

                // Registra na tabela de logs
                $conn->prepare("
                    INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $logTipo,
                    $hoje,
                    $alunoJid,
                    json_encode([
                        'status'     => 'sent',
                        'aluno_id'   => $alunoId,
                        'aluno_nome' => $alunoNome,
                        'lang'       => $lang,
                        'grupo'      => 'our_classes',
                        'finished_at'=> date('Y-m-d H:i:s'),
                    ])
                ]);

                $totalKicked++;
                echo "  ✅ {$alunoNome} removido do Our Classes com sucesso.\n";
            } else {
                $erro = $resRemove['error'] ?? "HTTP {$resRemove['httpCode']}";
                echo "  ❌ Falha ao remover {$alunoNome}: {$erro}\n";
            }
        } catch (Exception $e) {
            echo "  ❌ Exceção ao remover {$alunoNome}: " . $e->getMessage() . "\n";
        }
    }
}

// Marca que a verificação global rodou hoje (anti-duplicidade)
try {
    $conn->prepare("
        INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes)
        VALUES (?, ?, ?)
    ")->execute([
        $logTipoGlobal,
        $hoje,
        json_encode(['total_kickados' => $totalKicked, 'finished_at' => date('Y-m-d H:i:s')])
    ]);
} catch (Exception $e) {
    echo "⚠️ Erro ao registrar log global: " . $e->getMessage() . "\n";
}

echo "\n🏁 Auto-kick de Inadimplentes concluído! Total removidos do Our Classes: {$totalKicked}.\n";
?>
