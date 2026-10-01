<?php
/**
 * ============================================================
 * MIGRAÇÃO V18 - Campos CPF, E-mail e Responsável Financeiro
 * ============================================================
 * Adiciona 3 novos campos à tabela mentoria_alunos:
 *   - cpf                       VARCHAR(20)  → CPF do aluno
 *   - email                     VARCHAR(255) → E-mail do aluno
 *   - responsavel_financeiro_id INT NULL     → FK para outro aluno
 *                                               (NULL = é o próprio pagador)
 *
 * Lógica: Se responsavel_financeiro_id IS NOT NULL, o sistema usa
 *   o status_pagamento e proximo_vencimento do responsável para
 *   decidir cobranças, aviso e kick deste aluno.
 */
require_once __DIR__ . '/../config.php';

echo "<h2>Iniciando Migração V18 — CPF, E-mail e Responsável Financeiro</h2>";

try {
    $conn = connectDB();

    $alteracoes = [
        [
            'coluna'  => 'cpf',
            'sql'     => "ALTER TABLE mentoria_alunos ADD COLUMN cpf VARCHAR(20) DEFAULT NULL AFTER telefone",
            'label'   => 'CPF do aluno',
        ],
        [
            'coluna'  => 'email',
            'sql'     => "ALTER TABLE mentoria_alunos ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER cpf",
            'label'   => 'E-mail do aluno',
        ],
        [
            'coluna'  => 'responsavel_financeiro_id',
            'sql'     => "ALTER TABLE mentoria_alunos ADD COLUMN responsavel_financeiro_id INT DEFAULT NULL AFTER email",
            'label'   => 'ID do Responsável Financeiro (NULL = pagador autônomo)',
        ],
    ];

    foreach ($alteracoes as $item) {
        // Verifica se a coluna já existe (idempotente)
        $stmtCheck = $conn->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'mentoria_alunos'
              AND COLUMN_NAME  = ?
        ");
        $stmtCheck->execute([$item['coluna']]);
        $existe = (int)$stmtCheck->fetchColumn();

        if ($existe === 0) {
            $conn->exec($item['sql']);
            echo "<p>✅ Coluna <b><code>{$item['coluna']}</code></b> adicionada — {$item['label']}.</p>";
        } else {
            echo "<p>ℹ️ Coluna <b><code>{$item['coluna']}</code></b> já existia. Nenhuma ação necessária.</p>";
        }
    }

    echo "<h3 style='color:green;'>🎉 Migração V18 concluída com 100% de sucesso!</h3>";
    echo "<p><a href='mentoria.php'>← Ir para a Mentoria</a></p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Erro na Migração V18: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
