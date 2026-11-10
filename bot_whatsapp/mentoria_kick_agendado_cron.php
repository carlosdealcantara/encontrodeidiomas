<?php
/**
 * ============================================================
 * CRON: Encerramento Agendado de Acesso ao Our Classes
 * ============================================================
 * Frequência: 1x/dia às 00:00 BRT (chamado pelo master_cron.php)
 *
 * Caso de uso:
 *   Aluno anunciou cancelamento antecipado mas ainda tem o mês pago.
 *   O status foi mudado para 'Comunidade' (para não receber cobranças)
 *   e uma data de encerramento foi agendada no campo
 *   `data_encerramento_agendado` da tabela mentoria_alunos.
 *
 * Condições para executar o kick:
 *   1. data_encerramento_agendado = HOJE
 *   2. Campo não nulo (agendamento ativo)
 *
 * Ações:
 *   - Remove do grupo Our Classes via API Baileys
 *   - Limpa o campo data_encerramento_agendado (NULL) após execução
 *   - Se msg_encerramento_id estiver preenchido, envia mensagem no privado
 *   - Registra em mentoria_auto_logs
 *   - Notifica no Telegram com resumo da ação
 *
 * Não há exigência de aviso prévio enviado (diferente do kick de inadimplente),
 * pois o aluno já foi comunicado pessoalmente sobre o encerramento.
 * ============================================================
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

    // Garante que as colunas de agendamento existam (migração automática)
    try {
        $conn->exec("ALTER TABLE mentoria_alunos ADD COLUMN data_encerramento_agendado DATE NULL DEFAULT NULL");
    } catch (Exception $e) {} // ignora se já existir

    try {
        $conn->exec("ALTER TABLE mentoria_alunos ADD COLUMN msg_encerramento_id INT NULL DEFAULT NULL");
    } catch (Exception $e) {} // ignora se já existir

    $hoje        = date('Y-m-d');
    $forcar      = isset($_GET['force']);
    $totalKicked = 0;

    echo "\n🗓️  Iniciando Encerramento Agendado de Acesso...\n";
    echo "📅 Data de referência: {$hoje}\n";
    echo "⚙️  Modo: " . ($forcar ? "FORÇADO" : "Normal") . "\n\n";

    // ----------------------------------------------------------------
    // Busca alunos com encerramento agendado para HOJE
    // ----------------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT ma.*,
               mm.texto AS msg_encerramento_texto,
               mm.cenario AS msg_encerramento_cenario
        FROM mentoria_alunos ma
        LEFT JOIN mentoria_mensagens mm ON mm.id = ma.msg_encerramento_id AND mm.ativo = 1
        WHERE ma.data_encerramento_agendado = ?
    ");
    $stmt->execute([$hoje]);
    $agendados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($agendados)) {
        echo "✅ Nenhum encerramento agendado para hoje ({$hoje}).\n";
        exit;
    }

    echo "📋 " . count($agendados) . " encerramento(s) agendado(s) para hoje:\n";
    foreach ($agendados as $a) {
        echo "   → {$a['nome']} (status: {$a['status_aluno']}, lang: {$a['lang_id']})\n";
    }
    echo "\n";

    // ----------------------------------------------------------------
    // Processa cada aluno agendado
    // ----------------------------------------------------------------
    foreach ($agendados as $aluno) {
        $alunoId   = (int)$aluno['id'];
        $alunoNome = $aluno['nome'];
        $lang      = !empty($aluno['lang_id']) ? $aluno['lang_id'] : 'en';
        $logTipo   = 'kick_agendado_' . $lang;

        $telefone = preg_replace('/\D/', '', $aluno['telefone'] ?? '');
        if (strlen($telefone) <= 11 && strlen($telefone) > 0) {
            $telefone = "55" . $telefone;
        }
        $alunoJid = !empty($telefone) ? ($telefone . '@s.whatsapp.net') : null;

        echo "👤 Processando: {$alunoNome} | JID: " . ($alunoJid ?? 'N/A') . " | Idioma: {$lang}\n";

        // Anti-duplicidade por aluno no dia
        if (!$forcar) {
            $checkDupe = $conn->prepare("
                SELECT id FROM mentoria_auto_logs
                WHERE tipo = ? AND data_execucao = ? AND membro_jid = ?
            ");
            $checkDupe->execute([$logTipo, $hoje, $alunoJid]);
            if ($checkDupe->rowCount() > 0) {
                echo "   ⏭️  Já processado hoje. Pulando.\n\n";
                continue;
            }
        }

        // Busca o JID do Our Classes para este idioma
        echo "   🔗 Consultando JID do Our Classes na API Baileys...\n";
        $config = getMentoriaConfig($lang);
        $ourClassesJid = $config['groups']['our_classes']['jid'] ?? null;

        if (!$ourClassesJid) {
            echo "   ❌ Grupo Our Classes NÃO configurado para [{$lang}]. Pulando.\n\n";

            // Mesmo sem conseguir remover do grupo, limpa o agendamento e finaliza ciclo financeiro
            $conn->prepare("
                UPDATE mentoria_alunos
                SET data_encerramento_agendado = NULL,
                    msg_encerramento_id = NULL,
                    status_pagamento = 'Pendente'
                WHERE id = ?
            ")->execute([$alunoId]);

            $conn->prepare("
                INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes)
                VALUES (?, ?, ?, ?)
            ")->execute([
                $logTipo, $hoje, $alunoJid,
                json_encode([
                    'status'     => 'failed_no_group_jid',
                    'aluno_id'   => $alunoId,
                    'aluno_nome' => $alunoNome,
                    'lang'       => $lang,
                    'failed_at'  => date('Y-m-d H:i:s'),
                ])
            ]);
            continue;
        }

        echo "   🏠 Our Classes JID: {$ourClassesJid}\n";

        // Verifica se o aluno ainda está no grupo (status Ativo) ou já saiu
        if ($aluno['status_aluno'] === 'Ativo') {
            echo "   ℹ️  Aluno ainda consta como Ativo. Será removido e status atualizado para Comunidade.\n";
        } else {
            echo "   ℹ️  Aluno já está como '{$aluno['status_aluno']}'. Tentando remover do grupo mesmo assim.\n";
        }

        // Remove do grupo
        echo "   🚪 Chamando removerDoGrupo({$ourClassesJid}, [{$alunoJid}])...\n";

        try {
            $resRemove  = removerDoGrupo($ourClassesJid, [$alunoJid]);
            $httpCode   = $resRemove['httpCode'] ?? 0;
            $success    = ($resRemove['success'] ?? false) || $httpCode === 200;
            $erroDetalhe = $resRemove['error'] ?? json_encode($resRemove['data'] ?? $resRemove);

            echo "   📡 Resposta API: HTTP {$httpCode} | success=" . ($success ? 'true' : 'false') . "\n";

            if ($success) {
                // Atualiza status para Comunidade, encerra pagamento vigente para Pendente e limpa agendamento
                $conn->prepare("
                    UPDATE mentoria_alunos
                    SET status_aluno = 'Comunidade',
                        status_pagamento = 'Pendente',
                        data_encerramento_agendado = NULL,
                        msg_encerramento_id = NULL
                    WHERE id = ?
                ")->execute([$alunoId]);

                // Rastreamento de Churn / Periodos de Atividade
                try {
                    require_once __DIR__ . '/../includes/periodos_helper.php';
                    registrar_saida_periodo($conn, $alunoId, 'cancelamento', 'Encerramento agendado executado');
                } catch (Exception $ePeriodo) { /* nao interrompe o cron */ }

                // Registra no log
                $conn->prepare("
                    INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $logTipo, $hoje, $alunoJid,
                    json_encode([
                        'status'        => 'kicked',
                        'aluno_id'      => $alunoId,
                        'aluno_nome'    => $alunoNome,
                        'lang'          => $lang,
                        'grupo'         => 'our_classes',
                        'grupo_jid'     => $ourClassesJid,
                        'http_code'     => $httpCode,
                        'finished_at'   => date('Y-m-d H:i:s'),
                    ])
                ]);

                $totalKicked++;
                echo "   ✅ REMOVIDO com sucesso. Agendamento limpo.\n";

                // --------------------------------------------------------
                // Envia mensagem de encerramento no privado (se configurada)
                // --------------------------------------------------------
                if (!empty($aluno['msg_encerramento_texto']) && !empty($alunoJid)) {
                    $primeiroNome = trim(explode(' ', $alunoNome)[0]);
                    $textoEnvio   = str_replace('{nome}', $primeiroNome, $aluno['msg_encerramento_texto']);

                    echo "   💬 Disparando mensagem de encerramento no privado para {$alunoNome}...\n";
                    $resWhats  = enviarWhatsApp($telefone, $textoEnvio, 'mentoria_encerramento_privado');
                    $whatsCode = $resWhats['httpCode'] ?? 0;

                    if ($whatsCode >= 200 && $whatsCode < 300) {
                        $conn->prepare("
                            INSERT INTO mentoria_logs (aluno_id, mensagem_id, data_disparo)
                            VALUES (?, ?, ?)
                        ")->execute([$alunoId, $aluno['msg_encerramento_id'], $hoje]);
                        echo "   ✅ Mensagem de encerramento enviada (HTTP {$whatsCode}).\n";
                    } else {
                        echo "   ⚠️  Falha ao enviar mensagem de encerramento (HTTP {$whatsCode}).\n";
                    }
                } else {
                    echo "   ℹ️  Sem mensagem de encerramento configurada para este aluno. Nenhum envio realizado.\n";
                }

                // --------------------------------------------------------
                // Notifica no Telegram
                // --------------------------------------------------------
                $telegramToken  = $_ENV['TELEGRAM_COBRANCA_BOT_TOKEN'] ?? getenv('TELEGRAM_COBRANCA_BOT_TOKEN');
                $telegramChatId = $_ENV['TELEGRAM_COBRANCA_CHAT_ID'] ?? getenv('TELEGRAM_COBRANCA_CHAT_ID');
                if ($telegramToken && $telegramChatId) {
                    $msgTelegram  = "🗓️ *ENCERRAMENTO AGENDADO EXECUTADO*\n";
                    $msgTelegram .= "─────────────────────────────\n";
                    $msgTelegram .= "👤 Aluno: *{$alunoNome}*\n";
                    $msgTelegram .= "🌐 Mentoria: *" . strtoupper($lang) . "*\n";
                    if (!empty($telefone)) {
                        $msgTelegram .= "📱 WhatsApp: `+{$telefone}`\n";
                        $msgTelegram .= "─────────────────────────────\n";
                        $msgTelegram .= "🔗 [Abrir Chat](https://wa.me/{$telefone})";
                    }

                    $urlTel = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
                    $chTel  = curl_init($urlTel);
                    curl_setopt($chTel, CURLOPT_POST, 1);
                    curl_setopt($chTel, CURLOPT_POSTFIELDS, http_build_query([
                        'chat_id'                  => $telegramChatId,
                        'text'                     => $msgTelegram,
                        'parse_mode'               => 'Markdown',
                        'disable_web_page_preview' => true
                    ]));
                    curl_setopt($chTel, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chTel, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($chTel, CURLOPT_TIMEOUT, 5);
                    curl_exec($chTel);
                    curl_close($chTel);
                    echo "   📨 Notificação Telegram enviada.\n";
                }

            } else {
                // Falha na remoção — registra mas NÃO limpa o agendamento para tentar novamente
                $conn->prepare("
                    INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $logTipo . '_failed', $hoje, $alunoJid,
                    json_encode([
                        'status'     => 'failed',
                        'aluno_id'   => $alunoId,
                        'aluno_nome' => $alunoNome,
                        'lang'       => $lang,
                        'http_code'  => $httpCode,
                        'error'      => $erroDetalhe,
                        'failed_at'  => date('Y-m-d H:i:s'),
                    ])
                ]);

                echo "   ❌ FALHA ao remover (HTTP {$httpCode}). Agendamento mantido para nova tentativa.\n";
                echo "      Possíveis causas: aluno já não estava no grupo, API offline, JID incorreto.\n";
            }

        } catch (Exception $e) {
            echo "   💥 EXCEÇÃO: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    echo "═══════════════════════════════════════\n";
    echo "🏁 Concluído! Total removidos hoje: {$totalKicked}.\n";

} catch (Throwable $e) {
    http_response_code(200);
    echo "\n\n💥 ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "\n";
}
?>
