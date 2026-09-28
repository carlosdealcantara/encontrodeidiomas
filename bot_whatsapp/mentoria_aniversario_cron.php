<?php
/**
 * CRON: Aniversários da Mentoria (Meia-noite) — Multi-idioma
 * Frequência: 1x/dia, às 00:00 BRT
 * Posta parabéns no Lounge do idioma do aluno.
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
try { $conn->exec("ALTER TABLE mentoria_alunos ADD COLUMN data_nascimento DATE NULL DEFAULT NULL AFTER data_inicio"); } catch (Exception $e) {}

// -------------------------------------------------------
// Busca aniversariantes do dia (com lang_id do aluno)
// -------------------------------------------------------
$stmt = $conn->query("
    SELECT id, nome, telefone, data_nascimento, COALESCE(lang_id, 'en') as lang_id
    FROM mentoria_alunos 
    WHERE status_aluno IN ('Ativo', 'Vitalício', 'Comunidade') 
    AND data_nascimento IS NOT NULL 
    AND DAY(data_nascimento) = DAY(CURRENT_DATE) 
    AND MONTH(data_nascimento) = MONTH(CURRENT_DATE)
");
$aniversariantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($aniversariantes)) {
    echo "ℹ️ Nenhum aniversariante hoje ($hoje).\n";
    exit;
}

// Cache de configs por idioma para evitar múltiplas chamadas à API
$configCache = [];

// Templates default por idioma
$defaultTemplates = [
    'en' => "🎂 *Happy Birthday, {nome}!* 🎉\n\nToday is a special day — one of our amazing Mentorship members is celebrating their birthday! 🥳\n\nWe hope this new year of life brings you lots of growth, joy, and of course... fluency! 🌟\n\nDrop a 🎂 or send a birthday message to make {nome}'s day even more special! 💬 @{numero}",
    'es' => "🎂 *¡Feliz Cumpleaños, {nome}!* 🎉\n\n¡Hoy es un día especial — uno de nuestros increíbles miembros de la Mentoría está celebrando su cumpleaños! 🥳\n\n¡Esperamos que este nuevo año de vida te traiga mucho crecimiento, alegría y, por supuesto... fluidez! 🌟\n\n¡Deja un 🎂 o envía un mensaje de felicitación para que el día de {nome} sea aún más especial! 💬 @{numero}",
];

$enviados = 0;

foreach ($aniversariantes as $aluno) {
    $langId  = $aluno['lang_id'] ?? 'en';

    // Carrega config do idioma (com cache)
    if (!isset($configCache[$langId])) {
        $configCache[$langId] = getMentoriaConfig($langId);
    }
    $config = $configCache[$langId];

    // Determina o Lounge correto para o idioma do aluno
    $loungeJid = $config['groups']['the_lounge']['jid'] ?? null;
    if (!$loungeJid) {
        // Fallback: tenta o config EN
        if (!isset($configCache['en'])) $configCache['en'] = getMentoriaConfig('en');
        $loungeJid = $configCache['en']['groups']['the_lounge']['jid'] ?? null;
    }

    if (!$loungeJid) {
        echo "❌ JID do Lounge não configurado para [{$langId}] e fallback EN também falhou. Pulando {$aluno['nome']}.\n";
        continue;
    }

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

    // Template localizado
    $defaultTpl = $defaultTemplates[$langId] ?? $defaultTemplates['en'];
    $tpl        = $config['templates']['birthday'] ?? $defaultTpl;

    $primeiroNome = trim(explode(' ', $aluno['nome'])[0]);
    $msg          = str_replace(['{nome}', '{numero}'], [$primeiroNome, $telefone], $tpl);

    // Envia mensagem mencionando a pessoa no Lounge correto
    $res = enviarWhatsAppMention($loungeJid, $msg, [$memberJid]);

    if ($res['success'] || (isset($res['httpCode']) && $res['httpCode'] >= 200 && $res['httpCode'] < 300)) {
        $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes) VALUES ('aniversario', ?, ?, ?)")
             ->execute([$hoje, $telefone, $langId]);
        echo "✅ Feliz aniversário enviado para {$aluno['nome']} (idioma: [{$langId}]) no The Lounge!\n";
        $enviados++;
    } else {
        echo "❌ Erro ao enviar para {$aluno['nome']}: " . json_encode($res) . "\n";
    }
}

echo "\n🏁 Processo finalizado. $enviados mensagens enviadas.\n";
