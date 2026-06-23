<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

// Channel names confirmados via API LBRY
$canais = [
    'Inglês'      => '@EncontrodeIdiomasIngles',
    'Espanhol'    => '@EncontrodeIdiomasEspanhol',
    'Francês'     => '@EncontrodeIdiomasFrances',
    'Alemão'      => '@EncontrodeIdiomasAlemao',
    'Libras'      => '@EncontrodeIdiomasLibras',
];

$ok = 0;
foreach ($canais as $name => $channel) {
    $stmt = $conn->prepare("UPDATE languages SET odysee_channel_name = ? WHERE name = ?");
    $stmt->execute([$channel, $name]);
    $ok += $stmt->rowCount();
    echo "✅ $name → $channel\n";
}

echo "\nTotal atualizado: $ok idiomas.\n";
?>
