<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Migração do Banco de Dados - Meetup WhatsApp V5</h2>";

try {
    // Adiciona coluna semana_iso em meetup_whatsapp_logs
    try {
        $conn->exec("ALTER TABLE meetup_whatsapp_logs ADD COLUMN semana_iso SMALLINT NULL DEFAULT NULL COMMENT 'Número ISO da semana do ano (para controle de frequência semanal)'");
        echo "<p>✅ Coluna 'semana_iso' adicionada com sucesso em meetup_whatsapp_logs.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>ℹ️ Coluna 'semana_iso' já existe em meetup_whatsapp_logs. Pulando.</p>";
        } else {
            throw $e;
        }
    }

    // Adiciona coluna frequencia em meetup_whatsapp_templates
    try {
        $conn->exec("ALTER TABLE meetup_whatsapp_templates ADD COLUMN frequencia ENUM('diario', 'semanal') NOT NULL DEFAULT 'diario' COMMENT 'diario = pode disparar todo dia; semanal = no máximo 1x por semana ISO por idioma/grupo'");
        echo "<p>✅ Coluna 'frequencia' adicionada com sucesso em meetup_whatsapp_templates.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>ℹ️ Coluna 'frequencia' já existe em meetup_whatsapp_templates. Pulando.</p>";
        } else {
            throw $e;
        }
    }

    echo "<h3>Migração V5 concluída com sucesso!</h3>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Erro fatal: " . $e->getMessage() . "</p>";
}
