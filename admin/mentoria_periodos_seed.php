<?php
/**
 * Script de Retroalimentação Histórica (Seed) para a tabela `mentoria_periodos`.
 * Executado uma única vez (ou sob demanda, idempotente).
 * 
 * Protegido por autenticação de sessão ou token.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/periodos_helper.php';

$token_secreto = '83x9aZ2pLQw1';
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$has_token = isset($_GET['token']) && $_GET['token'] === $token_secreto;

if (!$is_admin && !$has_token && php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Acesso negado.");
}

$conn = connectDB();
garantir_tabela_periodos($conn);

echo "<pre style='font-family: monospace; background: #0f172a; color: #f1f5f9; padding: 20px; border-radius: 8px;'>";
echo "=== SEED HISTÓRICO: mentoria_periodos ===\n\n";

// 1. Busca todos os alunos
$stmtAlunos = $conn->query("SELECT id, nome, status_aluno, data_inicio, proximo_vencimento FROM mentoria_alunos ORDER BY id ASC");
$alunos = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);

// 2. Mapeamento de logs históricos de auto-kick para cruzar datas automáticas
$stmtLogs = $conn->query("
    SELECT tipo, data_execucao, detalhes, created_at 
    FROM mentoria_auto_logs 
    WHERE tipo LIKE 'kick_%' 
    ORDER BY id ASC
");
$logsKicks = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// Mapa de kicks por aluno_id: [aluno_id => ['data_fim' => '...', 'tipo' => '...']]
$kicksPorAluno = [];
foreach ($logsKicks as $l) {
    if (empty($l['detalhes'])) continue;
    $det = json_decode($l['detalhes'], true);
    if (!is_array($det)) continue;

    $aId = null;
    if (!empty($det['aluno_id'])) {
        $aId = (int)$det['aluno_id'];
    } elseif (!empty($det['dependente_id'])) {
        $aId = (int)$det['dependente_id'];
    }

    if ($aId) {
        $dataFim = !empty($det['finished_at']) ? substr($det['finished_at'], 0, 10) : $l['data_execucao'];
        $tipoSaida = (strpos($l['tipo'], 'kick_agendado') !== false) ? 'cancelamento' : 'kick_automatico';
        
        $kicksPorAluno[$aId] = [
            'data_fim' => $dataFim,
            'tipo_saida' => $tipoSaida,
            'log_origem' => $l['tipo']
        ];
    }
}

$totalProcessados = 0;
$totalCriados = 0;
$totalIgnorados = 0;

foreach ($alunos as $a) {
    $alunoId = (int)$a['id'];
    $nome = $a['nome'];
    $status = $a['status_aluno'];
    $dataInicio = !empty($a['data_inicio']) ? $a['data_inicio'] : date('Y-m-d');

    $totalProcessados++;

    // Verifica se este aluno já tem algum período cadastrado
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM mentoria_periodos WHERE aluno_id = ?");
    $stmtCheck->execute([$alunoId]);
    if ((int)$stmtCheck->fetchColumn() > 0) {
        echo "⏭️  [#{$alunoId}] {$nome} - Já possui períodos registrados. Ignorado.\n";
        $totalIgnorados++;
        continue;
    }

    if ($status === 'Ativo') {
        // Aluno ativo: período aberto
        $stmtIns = $conn->prepare("
            INSERT INTO mentoria_periodos (aluno_id, data_inicio, data_fim) 
            VALUES (?, ?, NULL)
        ");
        $stmtIns->execute([$alunoId, $dataInicio]);
        echo "🟢 [#{$alunoId}] {$nome} (Ativo) -> Período aberto iniciado em {$dataInicio}\n";
        $totalCriados++;

    } elseif ($status === 'Vitalício') {
        // Aluno vitalício: período concluído/vitalício
        $dataFim = !empty($a['proximo_vencimento']) ? $a['proximo_vencimento'] : date('Y-m-d');
        $stmtIns = $conn->prepare("
            INSERT INTO mentoria_periodos (aluno_id, data_inicio, data_fim, tipo_saida, observacao_saida) 
            VALUES (?, ?, ?, 'vitalicio', 'Converteu em Vitalício')
        ");
        $stmtIns->execute([$alunoId, $dataInicio, $dataFim]);
        echo "💎 [#{$alunoId}] {$nome} (Vitalício) -> Período encerrado em {$dataFim} (vitalício)\n";
        $totalCriados++;

    } else {
        // Comunidade ou Inativo: verifica se temos log do kick
        if (isset($kicksPorAluno[$alunoId])) {
            $kickInfo = $kicksPorAluno[$alunoId];
            $dataFim = $kickInfo['data_fim'];
            $tipoSaida = $kickInfo['tipo_saida'];
            $obs = "Recuperado automaticamente do log {$kickInfo['log_origem']}";

            $stmtIns = $conn->prepare("
                INSERT INTO mentoria_periodos (aluno_id, data_inicio, data_fim, tipo_saida, observacao_saida) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([$alunoId, $dataInicio, $dataFim, $tipoSaida, $obs]);
            echo "🤖 [#{$alunoId}] {$nome} ({$status}) -> Saída recuperada do log: {$dataFim} ({$tipoSaida})\n";
            $totalCriados++;
        } else {
            // Sem log de kick: insere com data_fim = NULL ou estimada para preenchimento manual
            $stmtIns = $conn->prepare("
                INSERT INTO mentoria_periodos (aluno_id, data_inicio, data_fim, tipo_saida, observacao_saida) 
                VALUES (?, ?, NULL, 'kick_automatico', 'Histórico anterior: aguardando confirmação da data de saída')
            ");
            $stmtIns->execute([$alunoId, $dataInicio]);
            echo "⚠️ [#{$alunoId}] {$nome} ({$status}) -> Sem log de kick. Período criado com data_fim pendente de confirmação.\n";
            $totalCriados++;
        }
    }
}

echo "\n========================================\n";
echo "Resumo:\n";
echo "Total de alunos avaliados: {$totalProcessados}\n";
echo "Períodos criados:          {$totalCriados}\n";
echo "Ignorados (já existentes): {$totalIgnorados}\n";
echo "========================================\n";
echo "</pre>";
