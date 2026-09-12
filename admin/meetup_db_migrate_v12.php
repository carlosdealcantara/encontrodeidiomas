<?php
// ============================================================
// MIGRAÇÃO V12 - Criação da tabela meeting_sessions
// e migração dos horários existentes
// ============================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $conn = connectDB();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Migração V12 — Estruturação de Sessões de Encontros (Pai/Filho)</h1>";

    // 1. Criar tabela meeting_sessions se não existir
    $conn->exec("
        CREATE TABLE IF NOT EXISTS meeting_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            day_of_week INT NOT NULL,
            time_hour INT NOT NULL,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_meeting (meeting_id),
            INDEX idx_day_time (day_of_week, time_hour),
            CONSTRAINT fk_meeting_sessions_meeting 
                FOREIGN KEY (meeting_id) REFERENCES meetings(id) 
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "<p>✅ Tabela <code>meeting_sessions</code> verificada/criada com sucesso.</p>";

    // 2. Migrar horários existentes de meetings para meeting_sessions (se ainda não migrados)
    // Verifica se meetings ainda tem a coluna day_of_week
    $colCheck = $conn->query("SHOW COLUMNS FROM meetings LIKE 'day_of_week'")->fetch();
    $migratedCount = 0;

    if ($colCheck) {
        // Copia sessões que ainda não existem em meeting_sessions
        $stmtMigrate = $conn->exec("
            INSERT INTO meeting_sessions (meeting_id, day_of_week, time_hour, active)
            SELECT m.id, m.day_of_week, m.time_hour, m.active
            FROM meetings m
            WHERE NOT EXISTS (
                SELECT 1 FROM meeting_sessions ms 
                WHERE ms.meeting_id = m.id 
                  AND ms.day_of_week = m.day_of_week 
                  AND ms.time_hour = m.time_hour
            )
            AND m.day_of_week IS NOT NULL 
            AND m.time_hour IS NOT NULL
        ");
        $migratedCount = $stmtMigrate;
        echo "<p>✅ Sessões migradas a partir da tabela meetings: <b>$migratedCount</b>.</p>";
    } else {
        echo "<p>ℹ️ Coluna <code>day_of_week</code> não mais presente em meetings; migração prévia já realizada.</p>";
    }

    $totalSessions = $conn->query("SELECT COUNT(*) FROM meeting_sessions")->fetchColumn();
    echo "<p>📊 Total atual de sessões em <code>meeting_sessions</code>: <b>$totalSessions</b>.</p>";
    echo "<p><a href='meetings.php'>Voltar para Gestão de Encontros</a></p>";

} catch (PDOException $e) {
    echo "<h1>❌ Erro na Migração V12</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
