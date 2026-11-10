<?php
/**
 * API para salvar/ajustar a data de saída de alunos da mentoria.
 * Usado pelo painel administrativo para preencher histórico ou editar saídas.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/periodos_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$conn = connectDB();
garantir_tabela_periodos($conn);

$aluno_id = !empty($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : 0;
$data_fim = !empty($_POST['data_fim']) ? trim($_POST['data_fim']) : null;
$tipo_saida = !empty($_POST['tipo_saida']) ? trim($_POST['tipo_saida']) : 'kick_automatico';
$observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : null;

if (!$aluno_id) {
    echo json_encode(['success' => false, 'error' => 'ID do aluno inválido']);
    exit;
}

try {
    // Busca o período mais recente deste aluno
    $stmt = $conn->prepare("
        SELECT id, data_inicio, data_fim FROM mentoria_periodos 
        WHERE aluno_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$aluno_id]);
    $periodo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($periodo) {
        $stmtUp = $conn->prepare("
            UPDATE mentoria_periodos 
            SET data_fim = ?, tipo_saida = ?, observacao_saida = COALESCE(?, observacao_saida) 
            WHERE id = ?
        ");
        $stmtUp->execute([$data_fim, $tipo_saida, $observacao, $periodo['id']]);
        echo json_encode(['success' => true, 'periodo_id' => $periodo['id'], 'data_fim' => $data_fim]);
    } else {
        // Se ainda não tinha período, busca data_inicio do aluno
        $stmtA = $conn->prepare("SELECT data_inicio FROM mentoria_alunos WHERE id = ?");
        $stmtA->execute([$aluno_id]);
        $aData = $stmtA->fetch(PDO::FETCH_ASSOC);
        $dInicio = !empty($aData['data_inicio']) ? $aData['data_inicio'] : ($data_fim ?: date('Y-m-d'));

        $stmtIns = $conn->prepare("
            INSERT INTO mentoria_periodos (aluno_id, data_inicio, data_fim, tipo_saida, observacao_saida) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([$aluno_id, $dInicio, $data_fim, $tipo_saida, $observacao]);
        echo json_encode(['success' => true, 'periodo_id' => $conn->lastInsertId(), 'data_fim' => $data_fim]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
