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
 *  - Atualiza status_aluno para 'Comunidade' no banco
 *  - Registra tudo em mentoria_auto_logs (com detalhes verbosos)
 *  - Logs no output mostrando CADA passo (nenhuma ação silenciosa)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$token_secreto = '83x9aZ2pLQw1';
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

try {
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
// Diagnóstico: mostra alunos candidatos (Ativos, não pagos)
// Convenção do sistema: dias_faltando = DATEDIFF(vencimento, HOJE)
// > 0: faltam dias para vencer
// = 0: vence hoje
// < 0: atrasado (ex: -1 = 1 dia vencido)
// ----------------------------------------------------------------
$stmtDiag = $conn->query("
    SELECT nome, status_aluno, status_pagamento, proximo_vencimento,
           DATEDIFF(proximo_vencimento, CURRENT_DATE) AS dias_faltando
    FROM mentoria_alunos
    WHERE status_aluno = 'Ativo'
      AND status_pagamento <> 'Pago'
    ORDER BY dias_faltando ASC
");
$diagAlunos = $stmtDiag->fetchAll(PDO::FETCH_ASSOC);

echo "📊 Candidatos a kick (Ativos, não pagos):\n";
if (empty($diagAlunos)) {
    echo "   — Nenhum aluno ativo inadimplente no momento.\n";
} else {
    foreach ($diagAlunos as $d) {
        $df = (int)$d['dias_faltando'];
        $icone = ($df <= -1) ? "⏰" : (($df === 0) ? "⚠️" : "🔜");
        echo "   {$icone} {$d['nome']} | status_pag={$d['status_pagamento']} | vence={$d['proximo_vencimento']} | dias_faltando={$df}\n";
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
    // - Ativos, não pagos e que NÃO enviaram comprovante recentemente
    // - Vencimento JÁ PASSOU (dias_faltando <= -1 ou DATEDIFF(vencimento, HOJE) <= -1)
    //   NOTA: no dia do vencimento (dias_faltando = 0) apenas o aviso de vencimento é enviado.
    //   O kick só ocorre na virada de meia-noite seguinte (dias_faltando <= -1).
    // ----------------------------------------------------------------
    $stmtTodos = $conn->query("
        SELECT ma.*,
               DATEDIFF(ma.proximo_vencimento, CURRENT_DATE) AS dias_faltando
        FROM mentoria_alunos ma
        WHERE ma.status_aluno = 'Ativo'
          AND ma.status_pagamento NOT IN ('Pago', 'Comprovante Enviado')
          AND DATEDIFF(ma.proximo_vencimento, CURRENT_DATE) <= -1
          AND ma.responsavel_financeiro_id IS NULL
    ");
    $todosAtrasados = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

    $alunos = array_values(array_filter($todosAtrasados, function($a) use ($lang) {
        $aLang = !empty($a['lang_id']) ? $a['lang_id'] : 'en';
        return ($aLang === $lang);
    }));

    if (empty($alunos)) {
        echo "   ✅ Nenhum aluno elegível para kick em [{$lang}].\n\n";
        continue;
    }

    echo "   👥 " . count($alunos) . " aluno(s) elegível(is):\n";
    foreach ($alunos as $a) {
        $df = (int)$a['dias_faltando'];
        echo "      → {$a['nome']} (pag={$a['status_pagamento']}, dias_faltando={$df})\n";
    }

    // ----------------------------------------------------------------
    // Busca a mensagem dias_antes=0 para confirmar que o aviso foi enviado
    // ----------------------------------------------------------------
    $stmtMsgsAll = $conn->query("
        SELECT id, cenario, lang_id FROM mentoria_mensagens
        WHERE dias_antes = 0 AND ativo = 1
    ");
    $allMsgs0 = $stmtMsgsAll->fetchAll(PDO::FETCH_ASSOC);

    $msgRow = null;
    foreach ($allMsgs0 as $m) {
        if (!empty($m['lang_id']) && $m['lang_id'] === $lang) {
            $msgRow = $m;
            break;
        }
    }
    if (!$msgRow) {
        foreach ($allMsgs0 as $m) {
            if (empty($m['lang_id']) || $m['lang_id'] === 'en') {
                $msgRow = $m;
                break;
            }
        }
    }

    if (!$msgRow) {
        echo "   ⚠️  Nenhuma mensagem com dias_antes=0 ativa encontrada para [{$lang}].\n";
        echo "       O kick só ocorre se o aviso de suspensão foi disparado. Pulando idioma.\n\n";
        continue;
    }

    echo "   📨 Mensagem de referência (vencimento): ID={$msgRow['id']} cenário=\"{$msgRow['cenario']}\"\n";

    // Busca o template de Mensagem de Suspensão (dias_antes = -1) para envio no privado pós-kick
    $stmtSuspensao = $conn->query("
        SELECT id, cenario, texto, lang_id FROM mentoria_mensagens
        WHERE dias_antes = -1 AND ativo = 1
    ");
    $allMsgsSuspensao = $stmtSuspensao->fetchAll(PDO::FETCH_ASSOC);
    $msgSuspensaoRow = null;
    foreach ($allMsgsSuspensao as $ms) {
        if (!empty($ms['lang_id']) && $ms['lang_id'] === $lang) {
            $msgSuspensaoRow = $ms;
            break;
        }
    }
    if (!$msgSuspensaoRow) {
        foreach ($allMsgsSuspensao as $ms) {
            if (empty($ms['lang_id']) || $ms['lang_id'] === 'en') {
                $msgSuspensaoRow = $ms;
                break;
            }
        }
    }

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
        $alunoId       = $aluno['id'];
        $alunoNome     = $aluno['nome'];
        $diasFaltando  = (int)$aluno['dias_faltando'];
        $telefone      = preg_replace('/\D/', '', $aluno['telefone'] ?? '');
        if (strlen($telefone) <= 11) {
            $telefone = "55" . $telefone;
        }
        $alunoJid = $telefone . '@s.whatsapp.net';
        $logTipo  = 'kick_inadimplente_' . $lang;

        echo "   👤 Avaliando: {$alunoNome} | JID: {$alunoJid} | dias_faltando={$diasFaltando}\n";

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

        // Verifica se a mensagem de aviso (dias_antes=0) já foi previamente enviada para este aluno
        $stmtCheck = $conn->prepare("
            SELECT id, data_disparo FROM mentoria_logs
            WHERE aluno_id = ? AND mensagem_id = ?
            ORDER BY data_disparo DESC LIMIT 1
        ");
        $stmtCheck->execute([$alunoId, $msgRow['id']]);
        $logAviso = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $mensagemEnviada = !empty($logAviso);

        if (!$mensagemEnviada && !$forcar) {
            echo "      ⏳ Mensagem de suspensão/aviso (ID={$msgRow['id']}) ainda NÃO consta como enviada para este aluno.\n";
            echo "         O kick requer que o aviso prévio tenha sido disparado. Pulando.\n\n";
            continue;
        }

        if ($mensagemEnviada) {
            echo "      ✅ Aviso de suspensão confirmado no histórico (disparado em {$logAviso['data_disparo']}).\n";
        } else {
            echo "      ⚡ Modo FORCE: pulando exigência de histórico do aviso prévio.\n";
        }

        echo "      🚪 Chamando removerDoGrupo({$ourClassesJid}, [{$alunoJid}])...\n";

        try {
            $resRemove = removerDoGrupo($ourClassesJid, [$alunoJid]);

            $httpCode = $resRemove['httpCode'] ?? 0;
            $success  = ($resRemove['success'] ?? false) || $httpCode === 200;
            $erro     = $resRemove['error'] ?? json_encode($resRemove['data'] ?? $resRemove);

            echo "      📡 Resposta API: HTTP {$httpCode} | success=" . ($success ? 'true' : 'false') . "\n";

            if ($success) {
                // Atualiza status do aluno para 'Comunidade' (mantém acesso aos grupos abertos e comunidade, apenas perde Our Classes)
                $conn->prepare("
                    UPDATE mentoria_alunos SET status_aluno = 'Comunidade' WHERE id = ?
                ")->execute([$alunoId]);

                // Registra no log do auto-kick
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
                        'dias_faltando'  => $diasFaltando,
                        'http_code'      => $httpCode,
                        'finished_at'    => date('Y-m-d H:i:s'),
                    ])
                ]);

                // --------------------------------------------------------
                // Envia imediatamente a mensagem de Suspensão no privado
                // --------------------------------------------------------
                if ($msgSuspensaoRow) {
                    $default_pix_footer = getSetting('mentoria_pix_footer', "🔑 Chave PIX: 01811018157\nCarlos");
                    $pixKey = ($lang === 'en') ? 'mentoria_pix_footer' : 'mentoria_pix_footer_' . $lang;
                    $pix_footer = getSetting($pixKey, $default_pix_footer);

                    $primeiroNome = trim(explode(' ', $alunoNome)[0]);
                    $textoSuspensao = str_replace('{nome}', $primeiroNome, $msgSuspensaoRow['texto']);
                    $textoSuspensao .= "\n\n" . trim($pix_footer);

                    echo "      💬 Disparando mensagem de suspensão no privado para {$alunoNome}...\n";
                    $resWhats = enviarWhatsApp($telefone, $textoSuspensao, 'mentoria_kick_privado');
                    $whatsCode = $resWhats['httpCode'] ?? 0;

                    if ($whatsCode >= 200 && $whatsCode < 300) {
                        $conn->prepare("
                            INSERT INTO mentoria_logs (aluno_id, mensagem_id, data_disparo) 
                            VALUES (?, ?, ?)
                        ")->execute([$alunoId, $msgSuspensaoRow['id'], $hoje]);
                        echo "      ✅ Mensagem de suspensão enviada com sucesso no privado (HTTP {$whatsCode}).\n";
                    } else {
                        echo "      ⚠️ Falha ao entregar mensagem no privado (HTTP {$whatsCode}).\n";
                    }
                }

                $totalKicked++;
                echo "      ✅ REMOVIDO com sucesso. status_aluno atualizado para 'Comunidade'.\n";

                // --------------------------------------------------------
                // Cascata para DEPENDENTES deste titular
                // --------------------------------------------------------
                $stmtDeps = $conn->prepare("
                    SELECT id, nome, lang_id, telefone, status_aluno, status_pagamento 
                    FROM mentoria_alunos 
                    WHERE responsavel_financeiro_id = ?
                ");
                $stmtDeps->execute([$alunoId]);
                $dependentes = $stmtDeps->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($dependentes)) {
                    echo "      👨‍👩‍👧 Encontrado(s) " . count($dependentes) . " dependente(s) deste titular. Processando cascata...\n";

                    foreach ($dependentes as $dep) {
                        $depId   = (int)$dep['id'];
                        $depNome = $dep['nome'];
                        $depLang = !empty($dep['lang_id']) ? $dep['lang_id'] : $lang;

                        // Atualiza status do dependente no banco para Comunidade / Suspenso
                        $conn->prepare("
                            UPDATE mentoria_alunos 
                            SET status_aluno = 'Comunidade', 
                                status_pagamento = 'Suspenso' 
                            WHERE id = ?
                        ")->execute([$depId]);

                        // Busca o JID do Our Classes do idioma específico do dependente
                        $depConfig = ($depLang === $lang) ? $config : getMentoriaConfig($depLang);
                        $depOurClassesJid = $depConfig['groups']['our_classes']['jid'] ?? null;

                        $depTel = preg_replace('/\D/', '', $dep['telefone'] ?? '');
                        if (strlen($depTel) <= 11 && strlen($depTel) > 0) {
                            $depTel = "55" . $depTel;
                        }
                        $depJid = !empty($depTel) ? ($depTel . '@s.whatsapp.net') : null;

                        if ($depOurClassesJid && $depJid) {
                            echo "         🚪 Removendo dependente {$depNome} ({$depLang}) do Our Classes ({$depOurClassesJid})...\n";
                            try {
                                $resDepRemove = removerDoGrupo($depOurClassesJid, [$depJid]);
                                $depHttp = $resDepRemove['httpCode'] ?? 0;
                                $depOk   = ($resDepRemove['success'] ?? false) || $depHttp === 200;

                                $conn->prepare("
                                    INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes)
                                    VALUES (?, ?, ?, ?)
                                ")->execute([
                                    'kick_dependente_' . $depLang, $hoje, $depJid,
                                    json_encode([
                                        'status'         => $depOk ? 'kicked' : 'failed',
                                        'dependente_id'  => $depId,
                                        'dependente_nome'=> $depNome,
                                        'titular_id'     => $alunoId,
                                        'titular_nome'   => $alunoNome,
                                        'lang'           => $depLang,
                                        'grupo_jid'      => $depOurClassesJid,
                                        'http_code'      => $depHttp,
                                        'finished_at'    => date('Y-m-d H:i:s'),
                                    ])
                                ]);

                                if ($depOk) {
                                    $totalKicked++;
                                    echo "         ✅ Dependente {$depNome} removido do grupo da aula com sucesso.\n";
                                } else {
                                    echo "         ⚠️ Falha ao remover dependente {$depNome} do grupo (HTTP {$depHttp}). Status no banco já foi atualizado para Comunidade.\n";
                                }
                            } catch (Exception $eDep) {
                                echo "         💥 Exceção ao remover dependente: " . $eDep->getMessage() . "\n";
                            }
                        } else {
                            echo "         ℹ️ Dependente {$depNome}: status atualizado para Comunidade (sem JID ou grupo configurado para remoção).\n";
                        }
                    }
                }
                echo "\n";

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

} catch (Throwable $e) {
    http_response_code(200);
    echo "\n\n💥 ERRO FATAL / EXCEÇÃO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "\n";
}
?>
