<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = connectDB();
    
    $novoTexto = "🤖 *AVISO DE SUSPENSÃO DE ACESSO*\n\nOlá, {nome}. Como o pagamento do seu ciclo não foi identificado até a data de vencimento, o sistema pausou o seu acesso ao grupo de aulas ao vivo.\n\n⚠️ *Para reativar seu acesso:*\nConforme previsto em nosso acordo, em caso de encerramento do ciclo sem renovação na data, a reativação segue a tabela vigente atual:\n\n• *Nova mensalidade:* {valor}\n• *Consulte a tabela oficial:* {url_site}\n\nAssim que realizar a transferência, basta enviar o comprovante aqui nesta conversa para restabelecermos o seu acesso às aulas.";

    $stmt = $conn->prepare("UPDATE mentoria_mensagens SET texto = ? WHERE dias_antes = -1");
    $stmt->execute([$novoTexto]);
    $affected = $stmt->rowCount();

    echo "SUCESSO: {$affected} linha(s) de mensagem de suspensao atualizada(s) no banco de dados!\n\n";

    $stmtList = $conn->query("SELECT id, cenario, dias_antes, lang_id, texto FROM mentoria_mensagens WHERE dias_antes = -1");
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "ID: {$r['id']} | Idioma: {$r['lang_id']} | Cenário: {$r['cenario']}\n";
        echo "Texto:\n{$r['texto']}\n";
        echo "--------------------------------------------------------\n";
    }

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}
