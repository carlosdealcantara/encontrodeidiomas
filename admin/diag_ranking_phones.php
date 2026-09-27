<?php
/**
 * DIAGNÓSTICO: Compara telefones do banco com JIDs do activity.json
 * Acesse: dev.viaei.com/scratch/diag_ranking_phones.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$conn = connectDB();
$hoje = date('Y-m-d');

// ── 1. Phones do banco por idioma ─────────────────────────────────
echo "=== BANCO: mentoria_alunos (ativos) ===\n";
$stmt = $conn->query("SELECT lang_id, telefone, status_aluno FROM mentoria_alunos WHERE status_aluno IN ('Ativo','Vitalício','Comunidade') ORDER BY lang_id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function normalizePhone(string $raw): array {
    $clean = preg_replace('/\D/', '', $raw);
    $variants = [$clean];
    if (strlen($clean) === 11) $variants[] = '55' . $clean;
    if (strlen($clean) === 13 && str_starts_with($clean, '55')) $variants[] = substr($clean, 2);
    return array_unique($variants);
}

$byLang = [];
foreach ($rows as $row) {
    $lang = $row['lang_id'];
    $raw  = $row['telefone'];
    $normalized = normalizePhone($raw);
    $byLang[$lang][] = ['raw' => $raw, 'normalized' => $normalized, 'status' => $row['status_aluno']];
    echo "  lang={$lang} | raw={$raw} | normalized=" . implode(', ', $normalized) . " | status={$row['status_aluno']}\n";
}

// ── 2. JIDs do activity.json hoje ─────────────────────────────────
echo "\n=== ACTIVITY.JSON: JIDs de hoje ({$hoje}) ===\n";
$activity = fetchBaileysActivity($hoje);

function phoneFromJid(string $jid): string {
    $p = preg_replace('/\D/', '', explode('@', $jid)[0]);
    return preg_replace('/:\d+$/', '', $p);
}

$jidPhones = [];
foreach ($activity as $groupJid => $members) {
    foreach ($members as $memberJid => $stats) {
        if (str_ends_with($memberJid, '@g.us')) continue;
        $phone = phoneFromJid($memberJid);
        $jidPhones[$memberJid] = $phone;
        echo "  jid={$memberJid} | phone_extraído={$phone}\n";
    }
}

// ── 3. Verificação do filtro por idioma ───────────────────────────
echo "\n=== VERIFICAÇÃO DO FILTRO ===\n";
foreach (['en', 'es'] as $lang) {
    echo "\n-- Simulando lang={$lang} --\n";

    // Phones de OUTROS idiomas (devem ser excluídos)
    $outrosPhones = [];
    foreach ($rows as $row) {
        if ($row['lang_id'] === $lang) continue;
        foreach (normalizePhone($row['telefone']) as $v) $outrosPhones[] = $v;
    }
    $outrosPhones = array_unique($outrosPhones);

    foreach ($jidPhones as $jid => $phone) {
        $inOutros    = in_array($phone, $outrosPhones, true);
        $resultado   = $inOutros ? '❌ EXCLUÍDO (outro idioma)' : '✅ INCLUÍDO';
        echo "  {$resultado} | jid={$jid} | phone={$phone} | in_outros=" . ($inOutros ? 'SIM' : 'NÃO') . "\n";
        if (!$inOutros && $inOutros === false) {
            // Mostra por que não bateu (debug)
            $proximos = array_filter($outrosPhones, fn($p) => substr($p, -8) === substr($phone, -8));
            if ($proximos) echo "         ⚠ Candidatos próximos em outros: " . implode(', ', $proximos) . "\n";
        }
    }
}
