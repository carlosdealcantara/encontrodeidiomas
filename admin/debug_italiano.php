<?php
/**
 * DEBUG: Diagnóstico do Italiano (meeting_id=2)
 * Verifica meeting_sessions, grupos e compatibilidade de disparo
 * Acesse: dev.viaEi.com/admin/debug_italiano.php
 */
require_once __DIR__ . '/../config.php';
$conn = connectDB();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Debug Italiano</title>
<style>
body { font-family: monospace; background:#111; color:#eee; padding:20px; }
h2 { color:#ffa; border-bottom:1px solid #555; padding-bottom:6px; }
table { border-collapse:collapse; width:100%; margin-bottom:20px; }
th { background:#333; color:#ffa; padding:8px; text-align:left; }
td { padding:6px 8px; border-bottom:1px solid #333; }
tr:hover td { background:#1e1e1e; }
.ok { color:#4f4; }
.warn { color:#fa0; }
.err { color:#f44; }
pre { background:#1a1a1a; padding:12px; overflow:auto; border:1px solid #444; }
</style>
</head>
<body>

<h2>🔍 Diagnóstico: Encontro de Italiano (meeting_id = 2)</h2>

<?php
// 1. Dados do meeting
$stmtM = $conn->prepare("SELECT m.*, l.name as lang_name, l.id as lid FROM meetings m JOIN languages l ON m.language_id = l.id WHERE m.id = 2");
$stmtM->execute();
$meeting = $stmtM->fetch();
?>

<h2>1. Dados do meeting (id=2)</h2>
<table>
  <tr><th>Campo</th><th>Valor</th></tr>
  <?php foreach ($meeting as $k => $v): if (is_int($k)) continue; ?>
  <tr>
    <td><?= htmlspecialchars($k) ?></td>
    <td>
      <?php
      if ($k === 'active') {
          echo $v ? "<span class='ok'>✅ SIM (active=1)</span>" : "<span class='err'>❌ NÃO (active=0)</span>";
      } else {
          echo htmlspecialchars((string)$v);
      }
      ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php
// 2. meeting_sessions para este meeting
$stmtSess = $conn->prepare("SELECT * FROM meeting_sessions WHERE meeting_id = 2 ORDER BY id ASC");
$stmtSess->execute();
$sessions = $stmtSess->fetchAll();
?>

<h2>2. meeting_sessions para meeting_id = 2</h2>
<?php if (empty($sessions)): ?>
  <p class="err">❌ NENHUMA sessão encontrada! Este é o problema — sem meeting_sessions, o encontro não aparece no cron.</p>
<?php else: ?>
<table>
  <tr><th>id</th><th>meeting_id</th><th>day_of_week</th><th>time_hour</th><th>active</th></tr>
  <?php foreach ($sessions as $s): ?>
  <tr>
    <td><?= $s['id'] ?></td>
    <td><?= $s['meeting_id'] ?></td>
    <td><?= $s['day_of_week'] ?> <?= ['','Seg','Ter','Qua','Qui','Sex','Sáb','Dom'][$s['day_of_week']] ?? '?' ?></td>
    <td><?= $s['time_hour'] ?></td>
    <td>
      <?php if ($s['active']): ?>
        <span class="ok">✅ ativo</span>
      <?php else: ?>
        <span class="err">❌ INATIVO — encontro ignorado pelo cron!</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php
// 3. Simula a query do cron para o dia em que o incidente ocorreu
// 2026-09-28 = segunda-feira (day_of_week=1 no ISO)
$diaIncidente = 1; // segunda-feira (ISO: 1=Seg)
$stmtSim = $conn->prepare("
    SELECT m.id, m.active, ms.id AS session_id, ms.day_of_week, ms.time_hour, ms.active as sess_active,
           l.name as language_name
    FROM meetings m
    JOIN meeting_sessions ms ON ms.meeting_id = m.id AND ms.active = 1
    JOIN languages l ON m.language_id = l.id
    WHERE m.active = 1 AND ms.day_of_week = ?
    ORDER BY ms.time_hour ASC
");
$stmtSim->execute([$diaIncidente]);
$meetingsNoIncidente = $stmtSim->fetchAll();
?>

<h2>3. Encontros que aparecem no cron numa Segunda-Feira (day_of_week=1) — dia do incidente</h2>
<table>
  <tr><th>meeting_id</th><th>language</th><th>session_id</th><th>day_of_week</th><th>time_hour</th><th>m.active</th><th>sess.active</th></tr>
  <?php foreach ($meetingsNoIncidente as $row): ?>
  <tr>
    <td><?= $row['id'] ?> <?= $row['id'] == 2 ? "<span class='ok'>← ITALIANO</span>" : '' ?></td>
    <td><?= htmlspecialchars($row['language_name']) ?></td>
    <td><?= $row['session_id'] ?></td>
    <td><?= $row['day_of_week'] ?></td>
    <td><?= $row['time_hour'] ?></td>
    <td><?= $row['active'] ? "<span class='ok'>1</span>" : "<span class='err'>0</span>" ?></td>
    <td><?= $row['sess_active'] ? "<span class='ok'>1</span>" : "<span class='err'>0</span>" ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (empty($meetingsNoIncidente)): ?>
    <tr><td colspan="7" class="err">❌ Nenhum encontro ativo na segunda-feira!</td></tr>
  <?php endif; ?>
</table>

<?php
// 4. Grupos ativos e seus language_ids
$stmtG = $conn->query("SELECT * FROM meetup_whatsapp_groups WHERE ativo = 1 AND bot_presente = 1 ORDER BY categoria ASC");
$groups = $stmtG->fetchAll();
$langIdItaliano = $meeting['lid'] ?? null;
?>

<h2>4. Grupos ativos — elegibilidade para Italiano (language_id = <?= $langIdItaliano ?>)</h2>
<table>
  <tr><th>id</th><th>nome</th><th>categoria</th><th>comunidade</th><th>language_ids</th><th>Receberia Italiano?</th></tr>
  <?php foreach ($groups as $g):
      $ids = json_decode($g['language_ids'] ?? '[]', true);
      $isMulti = $g['categoria'] === 'multi_idioma';
      $isEspecifico = $g['categoria'] === 'especifico';
      $temItaliano = is_array($ids) && in_array($langIdItaliano, $ids);
      $podeEnviar = $isMulti || ($isEspecifico && $temItaliano);
  ?>
  <tr>
    <td><?= $g['id'] ?></td>
    <td><?= htmlspecialchars($g['nome']) ?></td>
    <td><?= $g['categoria'] ?></td>
    <td><?= $g['comunidade'] ?? 'brasil' ?></td>
    <td><?= htmlspecialchars($g['language_ids'] ?? '') ?></td>
    <td>
      <?php if ($podeEnviar): ?>
        <span class="ok">✅ SIM <?= $isMulti ? '(multi_idioma)' : '(tem Italiano)' ?></span>
      <?php else: ?>
        <span class="err">❌ NÃO <?= $isEspecifico && !$temItaliano ? "(language_id $langIdItaliano não está em language_ids)" : '' ?></span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php
// 5. Logs de disparo dos últimos 7 dias para Italiano
$stmtLogs = $conn->prepare("
    SELECT l.*, t.cenario, g.nome as grupo_nome
    FROM meetup_whatsapp_logs l
    LEFT JOIN meetup_whatsapp_templates t ON l.template_id = t.id
    LEFT JOIN meetup_whatsapp_groups g ON l.grupo_id = g.id
    WHERE l.meeting_id = 2
    ORDER BY l.data_disparo DESC, l.id DESC
    LIMIT 30
");
$stmtLogs->execute();
$logs = $stmtLogs->fetchAll();
?>

<h2>5. Logs recentes — meeting_id = 2 (Italiano) — últimos 30 registros</h2>
<?php if (empty($logs)): ?>
  <p class="err">❌ Nenhum log encontrado para o Italiano. O disparo nunca ocorreu ou os logs foram apagados.</p>
<?php else: ?>
<table>
  <tr><th>id</th><th>data_disparo</th><th>cenario</th><th>grupo</th><th>template_id</th><th>semana_iso</th></tr>
  <?php foreach ($logs as $log): ?>
  <tr>
    <td><?= $log['id'] ?></td>
    <td><?= $log['data_disparo'] ?></td>
    <td><?= htmlspecialchars($log['cenario'] ?? '—') ?></td>
    <td><?= htmlspecialchars($log['grupo_nome'] ?? '—') ?></td>
    <td><?= $log['template_id'] ?></td>
    <td><?= $log['semana_iso'] ?? '—' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php
// 6. Templates ativos com timing
$stmtT = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario != 'Resumo do Dia' ORDER BY minutos_antes DESC");
$templates = $stmtT->fetchAll();

// Horário do encontro de italiano (pega da session)
$hItaliano = null;
foreach ($sessions as $s) {
    if ($s['day_of_week'] == 1 && $s['active']) { // segunda
        $hItaliano = (int)$s['time_hour'];
        break;
    }
}
if (!$hItaliano && !empty($sessions)) {
    $hItaliano = (int)$sessions[0]['time_hour'];
}
$minEncontro = $hItaliano ? $hItaliano * 60 : null;
?>

<h2>6. Templates ativos e suas janelas de disparo <?= $hItaliano ? "(encontro às {$hItaliano}:00)" : "" ?></h2>
<table>
  <tr><th>id</th><th>cenario</th><th>escopo</th><th>frequencia</th><th>minutos_antes</th><th>comunidade_alvo</th>
  <?php if ($minEncontro): ?><th>Disparo às (aprox)</th><?php endif; ?>
  </tr>
  <?php foreach ($templates as $t): ?>
  <?php
    $alvo = $minEncontro !== null ? ($minEncontro - (int)$t['minutos_antes']) : null;
    $alvoStr = $alvo !== null ? sprintf('%02d:%02d', intdiv($alvo, 60), $alvo % 60) : '—';
  ?>
  <tr>
    <td><?= $t['id'] ?></td>
    <td><?= htmlspecialchars($t['cenario']) ?></td>
    <td><?= $t['escopo'] ?? 'por_encontro' ?></td>
    <td><?= $t['frequencia'] ?? 'diario' ?></td>
    <td><?= $t['minutos_antes'] ?></td>
    <td><?= $t['comunidade_alvo'] ?? 'brasil' ?></td>
    <?php if ($minEncontro): ?><td><?= $alvoStr ?></td><?php endif; ?>
  </tr>
  <?php endforeach; ?>
</table>

<?php
// 7. Janelas de execução do cron com simulação real dos últimos crono
echo "<h2>7. Simulação: janela de tolerância do cron</h2>";
echo "<p>O cron roda a cada 5min. O template 'Aviso de Início' tem <code>minutos_antes = 0</code>, janela de tolerância: diffMin deve estar em [0, 4].</p>";

if ($hItaliano && $minEncontro !== null) {
    $templateAvisoInicio = null;
    foreach ($templates as $t) {
        if (stripos($t['cenario'], 'Início') !== false || stripos($t['cenario'], 'Inicio') !== false || (int)$t['minutos_antes'] === 0) {
            $templateAvisoInicio = $t;
            break;
        }
    }
    if ($templateAvisoInicio) {
        $minAntes = (int)$templateAvisoInicio['minutos_antes'];
        $alvo = $minEncontro - $minAntes;
        echo "<p>Template: <strong>" . htmlspecialchars($templateAvisoInicio['cenario']) . "</strong> | minutos_antes={$minAntes} | alvo={$alvo}min ({$hItaliano}:00)</p>";
        echo "<p>Janela válida: cron deve rodar entre o minuto <strong>{$alvo}</strong> e <strong>" . ($alvo + 4) . "</strong></p>";
        echo "<p>Ou seja: a execução do cron deve acontecer entre <strong>" . sprintf('%02d:%02d', intdiv($alvo,60), $alvo%60) . "</strong> e <strong>" . sprintf('%02d:%02d', intdiv($alvo+4,60), ($alvo+4)%60) . "</strong></p>";
        echo "<p class='warn'>⚠️ Se o cron executou em {$hItaliano}:05 ou depois, o disparo do Italiano teria sido pulado.</p>";
    }
}
?>

<h2>8. Sistema de contenção atual</h2>
<?php
$settings = ['wpp_meetups_hourly_ativo', 'wpp_meetups_daily_ativo'];
foreach ($settings as $key):
    $val = getSystemSetting($conn, $key, '0');
?>
<p><?= $key ?>: <?= $val == '1' ? "<span class='ok'>✅ Ativo (1)</span>" : "<span class='err'>❌ Desativado (0)</span>" ?></p>
<?php endforeach; ?>

</body>
</html>
