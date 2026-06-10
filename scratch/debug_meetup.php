<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

$hoje = new DateTime();
$diaDaSemanaAtual = (int)$hoje->format('N'); // 1 = Segunda, 7 = Domingo
$horaAtualReal = (int)$hoje->format('H'); // 0 a 23
$dataDisparo = $hoje->format('Y-m-d');

echo "Hora Atual Real (H): $horaAtualReal\n";
echo "Dia da Semana: $diaDaSemanaAtual\n";
echo "Data Disparo: $dataDisparo\n\n";

$stmtTemplates = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario != 'Resumo do Dia'");
$templates = $stmtTemplates->fetchAll(PDO::FETCH_ASSOC);

echo "TEMPLATES ATIVOS:\n";
foreach($templates as $t) {
    echo "- ID: {$t['id']}, Cenario: {$t['cenario']}, MinutosAntes: {$t['minutos_antes']}\n";
}

$stmtMeetings = $conn->prepare("
    SELECT m.*, l.name as language_name, l.flag_emoji, l.instagram_link, l.greeting 
    FROM meetings m
    JOIN languages l ON m.language_id = l.id
    WHERE m.active = 1 AND m.day_of_week = ?
");
$stmtMeetings->execute([$diaDaSemanaAtual]);
$meetings = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

echo "\nENCONTROS DE HOJE:\n";
foreach ($meetings as $m) {
    $horaEncontroDB = $m['time_hour'];
    $horaEncontro = (int)$horaEncontroDB;
    echo "- ID: {$m['id']}, Idioma: {$m['language_name']}, TimeHour: $horaEncontroDB (cast: $horaEncontro)\n";
    
    foreach ($templates as $t) {
        $minutosAntes = (int)$t['minutos_antes'];
        $horasAntes = round($minutosAntes / 60);
        $horaAlvo = $horaEncontro - $horasAntes;
        
        echo "  -> Template: {$t['cenario']}. HoraAlvo: $horaAlvo (HoraEncontro: $horaEncontro - HorasAntes: $horasAntes). Vai disparar agora? " . ($horaAtualReal === $horaAlvo ? 'SIM' : 'NAO') . "\n";
    }
}
?>
