<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$conn = connectDB();

echo "=======================================================\n";
echo "   TESTE DE RESOLUÇÃO DE NOMES DA MENTORIA (PROVA REAL)\n";
echo "=======================================================\n\n";

// 1. Pega alunos reais cadastrados no banco para testar a busca por telefone
echo "1. TESTANDO COM ALUNOS REAIS DA TABELA 'mentoria_alunos':\n";
echo "-------------------------------------------------------\n";

$stmtAlunos = $conn->query("SELECT id, nome, telefone, lang_id FROM mentoria_alunos WHERE telefone IS NOT NULL AND telefone != '' LIMIT 2");
$alunosReais = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);

if (empty($alunosReais)) {
    echo "Nenhum aluno encontrado no banco.\n";
} else {
    foreach ($alunosReais as $a) {
        $telOriginal = $a['telefone'];
        $nomeCadastrado = $a['nome'];
        
        // Simulação 1: JID como o WhatsApp costuma mandar (55 + DDD + sem o nono dígito)
        $telLimpo = preg_replace('/\D/', '', $telOriginal);
        $jidSimulado = (str_starts_with($telLimpo, '55') ? $telLimpo : '55' . $telLimpo) . "@s.whatsapp.net";
        $jidComDevice = (str_starts_with($telLimpo, '55') ? $telLimpo : '55' . $telLimpo) . ":2@s.whatsapp.net";

        echo "• Aluno no banco: [{$nomeCadastrado}] | Telefone gravado: {$telOriginal}\n";
        
        $resolvido1 = resolveMentoriaMemberName($conn, $jidSimulado);
        echo "  → Testando JID simulação 1 ({$jidSimulado}):\n";
        echo "    Resultado: [{$resolvido1}] " . ($resolvido1 === $nomeCadastrado ? "✅ SUCESSO" : "❌ FALHA") . "\n";

        $resolvido2 = resolveMentoriaMemberName($conn, $jidComDevice);
        echo "  → Testando JID simulação 2 com dispositivo ({$jidComDevice}):\n";
        echo "    Resultado: [{$resolvido2}] " . ($resolvido2 === $nomeCadastrado ? "✅ SUCESSO" : "❌ FALHA") . "\n\n";
    }
}

// 2. Testa com os JIDs recentes de pontuação manual (que usam formato @lid do WhatsApp)
echo "2. TESTANDO COM OS JIDs REAIS DOS ÚLTIMOS PONTOS ATRIBUÍDOS:\n";
echo "-------------------------------------------------------\n";

$stmtPts = $conn->query("SELECT DISTINCT member_jid, member_name FROM mentoria_dedicated_pts WHERE member_name IS NOT NULL AND member_name != '' LIMIT 3");
$pontosReais = $stmtPts->fetchAll(PDO::FETCH_ASSOC);

foreach ($pontosReais as $p) {
    $jidReal = $p['member_jid'];
    $nomeEsperado = $p['member_name'];

    // Simula também variação de dispositivo no JID LID (:3@lid)
    $jidDevice = preg_replace('/@/', ':3@', $jidReal);

    $resolvido = resolveMentoriaMemberName($conn, $jidReal);
    $resolvidoDev = resolveMentoriaMemberName($conn, $jidDevice);

    echo "• Participante: [{$nomeEsperado}]\n";
    echo "  → JID original ({$jidReal}): [{$resolvido}] " . ($resolvido === $nomeEsperado ? "✅ SUCESSO" : "❌ FALHA") . "\n";
    echo "  → JID com dispositivo ({$jidDevice}): [{$resolvidoDev}] " . ($resolvidoDev === $nomeEsperado ? "✅ SUCESSO" : "❌ FALHA") . "\n\n";
}

// 3. Se o usuário passar um JID personalizado via URL: ?jid=...
if (!empty($_GET['jid'])) {
    echo "3. TESTE PERSONALIZADO VIA URL (?jid={$_GET['jid']}):\n";
    echo "-------------------------------------------------------\n";
    $custom = resolveMentoriaMemberName($conn, $_GET['jid']);
    echo "JID informado: {$_GET['jid']}\n";
    echo "Nome resolvido: [{$custom}]\n";
}
