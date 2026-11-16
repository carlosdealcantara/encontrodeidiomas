<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$conn = connectDB();

echo "=======================================================\n";
echo "   TESTE DE RESOLUÇÃO DE NOMES DA MENTORIA (PROVA REAL)\n";
echo "=======================================================\n\n";

// 1. Pega alunos reais que POSSUEM telefone cadastrado
echo "1. TESTANDO COM ALUNOS REAIS COM TELEFONE CADASTRADO:\n";
echo "-------------------------------------------------------\n";

$stmtAlunos = $conn->query("SELECT id, nome, telefone, lang_id FROM mentoria_alunos WHERE telefone IS NOT NULL AND telefone != '' AND telefone != 'Em branco' AND LENGTH(telefone) >= 8 LIMIT 2");
$alunosReais = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);

if (empty($alunosReais)) {
    echo "Nenhum aluno com telefone válido encontrado no banco.\n";
} else {
    foreach ($alunosReais as $a) {
        $telOriginal = $a['telefone'];
        $nomeCadastrado = $a['nome'];
        
        $telLimpo = preg_replace('/\D/', '', $telOriginal);
        $jidSimulado = (str_starts_with($telLimpo, '55') ? $telLimpo : '55' . $telLimpo) . "@s.whatsapp.net";
        $jidComDevice = (str_starts_with($telLimpo, '55') ? $telLimpo : '55' . $telLimpo) . ":2@s.whatsapp.net";

        echo "• Aluno no banco: [{$nomeCadastrado}] | Telefone gravado: {$telOriginal}\n";
        
        $resolvido1 = resolveMentoriaMemberName($conn, $jidSimulado);
        echo "  → Testando JID simulação ({$jidSimulado}):\n";
        echo "    Resultado: [{$resolvido1}] " . ($resolvido1 === $nomeCadastrado ? "✅ SUCESSO" : "❌ FALHA") . "\n";

        $resolvido2 = resolveMentoriaMemberName($conn, $jidComDevice);
        echo "  → Testando JID com dispositivo linkado ({$jidComDevice}):\n";
        echo "    Resultado: [{$resolvido2}] " . ($resolvido2 === $nomeCadastrado ? "✅ SUCESSO" : "❌ FALHA") . "\n\n";
    }
}

// 2. Testa os JIDs que antes davam 'Unknown' na tabela mentoria_dedicated_pts
echo "2. TESTANDO CASOS HISTÓRICOS QUE ANTES DAVAM 'Unknown':\n";
echo "-------------------------------------------------------\n";

$stmtOld = $conn->query("SELECT id, member_jid, member_name FROM mentoria_dedicated_pts WHERE member_name = 'Unknown' AND member_jid != 'test' ORDER BY id DESC LIMIT 2");
$antigosUnknown = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

if (empty($antigosUnknown)) {
    echo "Nenhum registro antigo com 'Unknown'.\n";
} else {
    foreach ($antigosUnknown as $old) {
        $jid = $old['member_jid'];
        $resolvidoAgora = resolveMentoriaMemberName($conn, $jid);
        echo "• Ponto id #{$old['id']} | JID gravado: {$jid}\n";
        echo "  Antes (no banco): [{$old['member_name']}]\n";
        echo "  Agora (pela nova função): [{$resolvidoAgora}] " . ($resolvidoAgora !== 'Unknown' ? "✅ RECUPERADO COM SUCESSO!" : "⚠️ Continua Unknown") . "\n\n";
    }
}

// 3. Se passar via URL: ?jid=...
if (!empty($_GET['jid'])) {
    echo "3. TESTE PERSONALIZADO VIA URL:\n";
    echo "-------------------------------------------------------\n";
    $custom = resolveMentoriaMemberName($conn, $_GET['jid']);
    echo "JID: {$_GET['jid']}\n";
    echo "Nome resolvido: [{$custom}]\n";
}
