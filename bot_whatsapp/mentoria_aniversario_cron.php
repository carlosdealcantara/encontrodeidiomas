<?php
/**
 * CRON: Aniversários da Mentoria (Meia-noite)
 * Frequência: 1x/dia, às 00:00 BRT
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$conn = connectDB();
$hoje = date('Y-m-d');

try {
    $conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_auto_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        data_execucao DATE NOT NULL,
        membro_jid VARCHAR(100) NULL,
        detalhes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_execucao (tipo, data_execucao, membro_jid)
    )");
} catch (Exception $e) {}

// Adiciona coluna data_nascimento se não existir
try {
    $conn->exec("ALTER TABLE mentoria_alunos ADD COLUMN data_nascimento DATE NULL DEFAULT NULL AFTER data_inicio");
} catch (Exception $e) {}

$config = getMentoriaConfig();
$loungeJid = $config['groups']['the_lounge']['jid'] ?? null;

if (!$loungeJid) {
    die("❌ Erro: JID do grupo The Lounge não configurado.");
}

// Busca aniversariantes do dia
$stmt = $conn->query("
    SELECT id, nome, telefone, data_nascimento 
    FROM mentoria_alunos 
    WHERE status_aluno IN ('Ativo', 'Vitalício') 
    AND data_nascimento IS NOT NULL 
    AND DAY(data_nascimento) = DAY(CURRENT_DATE) 
    AND MONTH(data_nascimento) = MONTH(CURRENT_DATE)
");
$aniversariantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($aniversariantes)) {
    echo "ℹ️ Nenhum aniversariante hoje ($hoje).\n";
    exit;
}

$defaultTpl = "🎂 *Happy Birthday, {nome}!* 🎉\n\nToday is a special day — one of our amazing Mentorship members is celebrating their birthday! 🥳\n\nWe hope this new year of life brings you lots of growth, joy, and of course... fluency! 🌟\n\nDrop a 🎂 or send a birthday message to make {nome}'s day even more special! 💬 @{numero}";
$tpl = $config['templates']['birthday'] ?? $defaultTpl;

$enviados = 0;

foreach ($aniversariantes as $aluno) {
    $telefone = preg_replace('/\D/', '', $aluno['telefone']);
    if (strlen($telefone) <= 11) {
        $telefone = "55" . $telefone;
    }
    $memberJid = $telefone . "@s.whatsapp.net";
    
    // Trava anti-duplicidade
    $check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'aniversario' AND data_execucao = ? AND membro_jid = ?");
    $check->execute([$hoje, $telefone]);
    if ($check->rowCount() > 0 && !isset($_GET['force'])) {
        echo "✅ Aviso já enviado hoje para {$aluno['nome']}. Ignorando...\n";
        continue;
    }

    $primeiroNome = trim(explode(' ', $aluno['nome'])[0]);
    $msg = str_replace(['{nome}', '{numero}'], [$primeiroNome, $telefone], $tpl);

    // Enviar mensagem mencionando a pessoa
    $res = enviarWhatsAppMention($loungeJid, $msg, [$memberJid]);
    
    if ($res['success'] || (isset($res['httpCode']) && $res['httpCode'] >= 200 && $res['httpCode'] < 300)) {
        $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid) VALUES ('aniversario', ?, ?)")->execute([$hoje, $telefone]);
        echo "✅ Feliz aniversário enviado para {$aluno['nome']} no The Lounge!\n";
        $enviados++;
    } else {
        echo "❌ Erro ao enviar para {$aluno['nome']}: " . json_encode($res) . "\n";
    }
}

echo "\n🏁 Processo finalizado. $enviados mensagens enviadas.\n";
