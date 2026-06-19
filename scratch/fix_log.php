<?php
$json = file_get_contents(__DIR__ . '/activity_log.json');
$data = json_decode($json, true);

// As datas que podem ter os erros (hoje ou ontem)
$dates = ['2026-06-16', '2026-06-17'];

$fixed = 0;

foreach ($dates as $date) {
    if (!isset($data[$date])) continue;
    
    foreach ($data[$date] as $groupJid => &$members) {
        foreach ($members as $memberJid => &$stats) {
            $name = strtolower($stats['name'] ?? '');
            
            // Corrige Rayza (apagar as 4 mensagens indevidas)
            if (strpos($name, 'rayza') !== false) {
                if (($stats['messages'] ?? 0) > 0) {
                    echo "Corrigindo Rayza no grupo $groupJid em $date: de {$stats['messages']} para 0.\n";
                    $stats['messages'] = 0;
                    $fixed++;
                }
            }
            
            // Corrige Flávia (apagar 1 mensagem indevida que foi duplicata da imagem)
            if (strpos($name, 'flávia') !== false || strpos($name, 'flavia') !== false) {
                if (($stats['messages'] ?? 0) > 0) {
                    echo "Corrigindo Flávia no grupo $groupJid em $date: zerando {$stats['messages']} mensagens indevidas.\n";
                    $stats['messages'] = 0;
                    $fixed++;
                }
            }
        }
    }
}

if ($fixed > 0) {
    file_put_contents(__DIR__ . '/activity_log.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Pronto! Foram feitas $fixed correções locais.\n";
} else {
    echo "Nenhuma correção necessária ou nomes não encontrados.\n";
}
