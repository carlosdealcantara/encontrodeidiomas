<?php
/**
 * ============================================================
 * MIGRAÇÃO V17 - Template de Comprovante Recebido
 * ============================================================
 * Insere o template "Comprovante Recebido" (dias_antes = -999)
 * para Inglês ('en') e Espanhol ('es') se ainda não existirem.
 */
require_once __DIR__ . '/../config.php';

echo "<h2>Iniciando Migração V17 — Template Comprovante Recebido</h2>";

try {
    $conn = connectDB();

    $templatesNovos = [
        [
            'lang_id' => 'en',
            'cenario' => 'Comprovante Recebido',
            'dias_antes' => -999,
            'texto' => "Recebido, {nome}! 📄 Muito obrigado pelo envio do comprovante. Nosso sistema registrou a entrega e em breve daremos baixa na sua renovação. Pode relaxar, seu acesso segue normalmente! 👍",
            'ativo' => 1,
            'ativo_telegram' => 1
        ],
        [
            'lang_id' => 'es',
            'cenario' => 'Comprovante Recebido',
            'dias_antes' => -999,
            'texto' => "¡Recibido, {nome}! 📄 Muchas gracias por enviar el comprobante. Nuestro sistema registró la entrega y pronto confirmaremos tu renovación. ¡No te preocupes, tu acceso sigue normalmente! 👍",
            'ativo' => 1,
            'ativo_telegram' => 1
        ]
    ];

    foreach ($templatesNovos as $tpl) {
        $stmtCheck = $conn->prepare("SELECT id FROM mentoria_mensagens WHERE cenario = ? AND lang_id = ?");
        $stmtCheck->execute([$tpl['cenario'], $tpl['lang_id']]);
        if ($stmtCheck->rowCount() === 0) {
            $stmtIns = $conn->prepare("
                INSERT INTO mentoria_mensagens (lang_id, cenario, dias_antes, texto, ativo, ativo_telegram)
                VALUES (:lang_id, :cenario, :dias_antes, :texto, :ativo, :ativo_telegram)
            ");
            $stmtIns->execute($tpl);
            echo "<p>✅ Template <b>{$tpl['cenario']}</b> ({$tpl['lang_id']}) inserido com sucesso!</p>";
        } else {
            echo "<p>ℹ️ Template <b>{$tpl['cenario']}</b> ({$tpl['lang_id']}) já existe no banco.</p>";
        }
    }

    echo "<h3 style='color:green;'>🎉 Migração V17 concluída com 100% de sucesso!</h3>";
    echo "<p><a href='mentoria.php?tab=cobrancas'>← Ir para Templates de Cobrança</a></p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Erro na Migração V17: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
