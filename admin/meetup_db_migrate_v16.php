<?php
/**
 * ============================================================
 * MIGRAÇÃO V16 - Expansão Multi-idioma da Mentoria
 * ============================================================
 * Adiciona suporte a múltiplos idiomas mantendo compatibilidade
 * 100% retroativa com os alunos e dados existentes de Inglês ('en').
 */
require_once __DIR__ . '/../config.php';

echo "<h2>Iniciando Migração V16 — Suporte Multi-idioma da Mentoria</h2>";

try {
    $conn = connectDB();

    // 1. Tabela de idiomas da mentoria
    $conn->exec("
        CREATE TABLE IF NOT EXISTS mentoria_langs (
            lang_id VARCHAR(10) PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            bandeira VARCHAR(10) NOT NULL DEFAULT '🌐',
            ativo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p>✅ Tabela <code>mentoria_langs</code> verificada/criada.</p>";

    // Inserir idiomas padrão se não existirem
    $stmtCheckLang = $conn->prepare("SELECT COUNT(*) FROM mentoria_langs WHERE lang_id = ?");
    
    $stmtCheckLang->execute(['en']);
    if ($stmtCheckLang->fetchColumn() == 0) {
        $conn->prepare("INSERT INTO mentoria_langs (lang_id, nome, bandeira, ativo) VALUES ('en', 'Inglês', '🇺🇸', 1)")->execute();
        echo "<p>➕ Idioma 'en' (Inglês 🇺🇸) cadastrado.</p>";
    }
    
    $stmtCheckLang->execute(['es']);
    if ($stmtCheckLang->fetchColumn() == 0) {
        $conn->prepare("INSERT INTO mentoria_langs (lang_id, nome, bandeira, ativo) VALUES ('es', 'Espanhol', '🇪🇸', 1)")->execute();
        echo "<p>➕ Idioma 'es' (Espanhol 🇪🇸) cadastrado.</p>";
    }

    // Helper para adicionar coluna com segurança caso não exista
    function addColumnIfNotExists(PDO $conn, string $table, string $column, string $definition) {
        try {
            $stmt = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            if ($stmt->rowCount() == 0) {
                $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                echo "<p>✅ Coluna <code>$column</code> adicionada à tabela <code>$table</code>.</p>";
            } else {
                echo "<p>ℹ️ Coluna <code>$column</code> já existia na tabela <code>$table</code>.</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:orange;'>⚠️ Aviso em <code>$table.$column</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    // 2. Coluna lang_id em mentoria_alunos
    addColumnIfNotExists($conn, 'mentoria_alunos', 'lang_id', "VARCHAR(10) NOT NULL DEFAULT 'en'");

    // 3. Coluna lang_id em class_schedule
    addColumnIfNotExists($conn, 'class_schedule', 'lang_id', "VARCHAR(10) NOT NULL DEFAULT 'en'");

    // 4. Coluna lang_id em class_attendances
    addColumnIfNotExists($conn, 'class_attendances', 'lang_id', "VARCHAR(10) NOT NULL DEFAULT 'en'");

    // 5. Coluna lang_id em mentoria_mensagens
    addColumnIfNotExists($conn, 'mentoria_mensagens', 'lang_id', "VARCHAR(10) NOT NULL DEFAULT 'en'");

    // 6. Coluna lang_id em mentoria_odysee_queue
    addColumnIfNotExists($conn, 'mentoria_odysee_queue', 'lang_id', "VARCHAR(10) NOT NULL DEFAULT 'en'");

    // 7. Seed de templates de cobrança para Espanhol ('es') se ainda não existirem
    $stmtEsCount = $conn->prepare("SELECT COUNT(*) FROM mentoria_mensagens WHERE lang_id = 'es'");
    $stmtEsCount->execute();
    if ($stmtEsCount->fetchColumn() == 0) {
        echo "<h4>Criando templates iniciais de cobrança para Espanhol (🇪🇸)...</h4>";

        $templatesEs = [
            [
                'cenario' => 'Confirmação de Pagamento',
                'dias_antes' => -999,
                'texto' => "🤖 MENSAJE AUTOMÁTICO:\n\n¡Hola {nome}! Pasando para confirmar que recibimos tu pago y tu renovación ya está garantizada en el sistema. 🎉\n\n¡Muchas gracias por seguir con nosotros! Tu próximo vencimiento quedó para el {data}.\n\nCualquier duda, ¡aquí estoy para ayudarte!",
                'ativo' => 1,
                'ativo_telegram' => 1
            ],
            [
                'cenario' => 'Aviso Amigável (Prévio)',
                'dias_antes' => 3,
                'texto' => "🤖 MENSAJE AUTOMÁTICO:\n\n¡Hola, {nome}! ¿Todo bien? 🇪🇸\n\nPasando para avisarte amistosamente que el acceso a nuestra mentoría vence en 3 días.\n\nPara garantizar tu continuidad sin interrupciones en las clases y actividades, realiza tu pago a través de la clave PIX abajo y envíame el comprobante aquí.",
                'ativo' => 1,
                'ativo_telegram' => 1
            ],
            [
                'cenario' => 'Aviso de Vencimento (Amanhã)',
                'dias_antes' => 1,
                'texto' => "🤖 MENSAJE AUTOMÁTICO:\n\n¡Hola, {nome}! 🇪🇸\n\nTe recordamos que tu acceso a la mentoría de español vence mañana.\n\nPara mantener tus accesos activos a la comunidad y a los encuentros, por favor realiza tu renovación por PIX y avísanos por aquí.",
                'ativo' => 1,
                'ativo_telegram' => 1
            ],
            [
                'cenario' => 'Vencimento Hoje',
                'dias_antes' => 0,
                'texto' => "🤖 MENSAJE AUTOMÁTICO:\n\n¡Hola, {nome}! 🇪🇸\n\nHoy es el día de vencimiento de tu mensualidad de la mentoría.\n\nPuedes hacer el pago con los datos de PIX a continuación. Al enviarlo hoy, aseguras tu acceso continuo sin que se interrumpa tu participación.",
                'ativo' => 1,
                'ativo_telegram' => 1
            ],
            [
                'cenario' => 'Suspensão de Acesso',
                'dias_antes' => -1,
                'texto' => "🤖 MENSAJE AUTOMÁTICO:\n\nHola, {nome}. Tu mensualidad venció ayer y aún no identificamos el comprobante. Por políticas de la mentoría, los accesos a los grupos y clases quedan pausados temporalmente.\n\nPara reactivar tu participación de inmediato, solo envíanos el comprobante por aquí.",
                'ativo' => 1,
                'ativo_telegram' => 1
            ]
        ];

        $stmtInsertEs = $conn->prepare("
            INSERT INTO mentoria_mensagens (lang_id, cenario, dias_antes, texto, ativo, ativo_telegram) 
            VALUES ('es', :cenario, :dias_antes, :texto, :ativo, :ativo_telegram)
        ");

        foreach ($templatesEs as $tpl) {
            $stmtInsertEs->execute($tpl);
        }
        echo "<p>✅ 5 templates de cobrança em Espanhol (🇪🇸) cadastrados com sucesso!</p>";
    } else {
        echo "<p>ℹ️ Templates de Espanhol já existem em <code>mentoria_mensagens</code>.</p>";
    }

    echo "<h3 style='color:green;'>🎉 Migração V16 concluída com 100% de sucesso!</h3>";
    echo "<p><a href='mentoria.php'>← Voltar para o Painel da Mentoria</a></p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Erro na Migração V16: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
