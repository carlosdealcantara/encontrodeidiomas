<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Migração do Banco de Dados - Meetup WhatsApp V6</h2>";

try {
    // Adiciona coluna escopo em meetup_whatsapp_templates
    try {
        $conn->exec("ALTER TABLE meetup_whatsapp_templates ADD COLUMN escopo ENUM('por_encontro', 'diario') NOT NULL DEFAULT 'por_encontro' COMMENT 'por_encontro = dispara para cada meeting; diario = dispara 1x/dia antes do primeiro encontro'");
        echo "<p>✅ Coluna 'escopo' adicionada com sucesso em meetup_whatsapp_templates.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>ℹ️ Coluna 'escopo' já existe. Pulando.</p>";
        } else {
            throw $e;
        }
    }

    // Garante que o template "Convite para Host" já existente tenha escopo = 'diario'
    $affected = $conn->exec("UPDATE meetup_whatsapp_templates SET escopo = 'diario', frequencia = 'semanal' WHERE cenario = 'Convite para Host'");
    if ($affected > 0) {
        echo "<p>✅ Template 'Convite para Host' atualizado para escopo='diario' e frequencia='semanal'.</p>";
    } else {
        echo "<p>ℹ️ Nenhum template 'Convite para Host' encontrado para atualizar (normal se ainda não foi criado).</p>";
    }

    echo "<h3>✅ Migração V6 concluída com sucesso!</h3>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Erro fatal: " . $e->getMessage() . "</p>";
}
