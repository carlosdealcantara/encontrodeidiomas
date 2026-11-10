<?php
/**
 * Helper centralizado para rastreamento de churn / períodos de atividade dos alunos da mentoria.
 * Registra entrada e saída em `mentoria_periodos`.
 */

require_once __DIR__ . '/../config.php';

/**
 * Garante que a tabela mentoria_periodos exista.
 */
function garantir_tabela_periodos($conn) {
    static $verificado = false;
    if ($verificado) return;

    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS mentoria_periodos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aluno_id INT NOT NULL,
                data_inicio DATE NOT NULL,
                data_fim DATE NULL,
                tipo_saida ENUM('kick_automatico', 'cancelamento', 'vitalicio', 'outro') NULL,
                kick_revertido TINYINT(1) DEFAULT 0,
                observacao_saida TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_aluno (aluno_id),
                INDEX idx_fim (data_fim),
                FOREIGN KEY (aluno_id) REFERENCES mentoria_alunos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $verificado = true;
    } catch (Exception $e) {
        error_log("Erro ao verificar/criar mentoria_periodos: " . $e->getMessage());
    }
}

/**
 * Registra a saída de um aluno (fechamento de período).
 * 
 * @param PDO $conn
 * @param int $aluno_id
 * @param string $tipo_saida 'kick_automatico' | 'cancelamento' | 'vitalicio' | 'outro'
 * @param string|null $observacao
 * @param string|null $data_saida_especifica Se informada (ex: Y-m-d), usa ela. Senão hoje.
 */
function registrar_saida_periodo($conn, $aluno_id, $tipo_saida = 'kick_automatico', $observacao = null, $data_saida_especifica = null) {
    if (!$aluno_id) return false;
    garantir_tabela_periodos($conn);

    $hoje = $data_saida_especifica ?: date('Y-m-d');

    try {
        // Busca o período aberto mais recente deste aluno
        $stmt = $conn->prepare("
            SELECT id, data_inicio FROM mentoria_periodos 
            WHERE aluno_id = ? AND data_fim IS NULL 
            ORDER BY data_inicio DESC, id DESC LIMIT 1
        ");
        $stmt->execute([(int)$aluno_id]);
        $periodo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($periodo) {
            $stmtUp = $conn->prepare("
                UPDATE mentoria_periodos 
                SET data_fim = ?, tipo_saida = ?, observacao_saida = ? 
                WHERE id = ?
            ");
            $stmtUp->execute([$hoje, $tipo_saida, $observacao, $periodo['id']]);
            return true;
        } else {
            // Se não tinha período aberto (ex: aluno antigo que acabou de ser migrado ou nunca teve registro de entrada)
            // busca a data de início oficial do aluno na tabela mentoria_alunos
            $stmtAluno = $conn->prepare("SELECT data_inicio FROM mentoria_alunos WHERE id = ?");
            $stmtAluno->execute([(int)$aluno_id]);
            $alunoData = $stmtAluno->fetch(PDO::FETCH_ASSOC);
            $dataInicio = !empty($alunoData['data_inicio']) ? $alunoData['data_inicio'] : $hoje;

            $stmtIns = $conn->prepare("
                INSERT INTO mentoria_periodos (aluno_id, data_inicio, data_fim, tipo_saida, observacao_saida)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([(int)$aluno_id, $dataInicio, $hoje, $tipo_saida, $observacao]);
            return true;
        }
    } catch (Exception $e) {
        error_log("Erro em registrar_saida_periodo (aluno_id: {$aluno_id}): " . $e->getMessage());
        return false;
    }
}

/**
 * Registra a entrada de um aluno (abertura de novo período).
 * Se o aluno saiu há <= 7 dias, marca o período anterior como kick_revertido = 1.
 * 
 * @param PDO $conn
 * @param int $aluno_id
 * @param string|null $data_inicio_especifica Se informada (ex: Y-m-d), usa ela. Senão hoje.
 * @param int $dias_tolerancia Limite de dias para considerar burocracia/kick revertido (default 7).
 */
function registrar_entrada_periodo($conn, $aluno_id, $data_inicio_especifica = null, $dias_tolerancia = 7) {
    if (!$aluno_id) return false;
    garantir_tabela_periodos($conn);

    $hoje = $data_inicio_especifica ?: date('Y-m-d');

    try {
        // Verifica se já tem um período aberto atualmente
        $stmtAberto = $conn->prepare("
            SELECT id FROM mentoria_periodos 
            WHERE aluno_id = ? AND data_fim IS NULL 
            ORDER BY data_inicio DESC, id DESC LIMIT 1
        ");
        $stmtAberto->execute([(int)$aluno_id]);
        if ($stmtAberto->fetch()) {
            // Já está com período aberto ativo, não precisa abrir outro duplicado
            return true;
        }

        // Verifica o último período fechado para ver se foi recente (reversão de kick)
        $stmtUltimo = $conn->prepare("
            SELECT id, data_fim FROM mentoria_periodos 
            WHERE aluno_id = ? AND data_fim IS NOT NULL 
            ORDER BY data_fim DESC, id DESC LIMIT 1
        ");
        $stmtUltimo->execute([(int)$aluno_id]);
        $ultimoFechado = $stmtUltimo->fetch(PDO::FETCH_ASSOC);

        if ($ultimoFechado && !empty($ultimoFechado['data_fim'])) {
            $dataFimUltimo = new DateTime($ultimoFechado['data_fim']);
            $dataInicioNova = new DateTime($hoje);
            $intervaloDias = (int)$dataFimUltimo->diff($dataInicioNova)->format('%r%a');

            // Se a diferença for positiva ou zero e até a tolerância (ex: <= 7 dias)
            if ($intervaloDias >= 0 && $intervaloDias <= $dias_tolerancia) {
                $stmtRev = $conn->prepare("UPDATE mentoria_periodos SET kick_revertido = 1 WHERE id = ?");
                $stmtRev->execute([$ultimoFechado['id']]);
            }
        }

        // Abre novo período
        $stmtIns = $conn->prepare("
            INSERT INTO mentoria_periodos (aluno_id, data_inicio, data_fim)
            VALUES (?, ?, NULL)
        ");
        $stmtIns->execute([(int)$aluno_id, $hoje]);
        return true;
    } catch (Exception $e) {
        error_log("Erro em registrar_entrada_periodo (aluno_id: {$aluno_id}): " . $e->getMessage());
        return false;
    }
}
