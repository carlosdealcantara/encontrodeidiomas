<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

try {
    $footer = "*Nº: Máximo de participantes simultâneos | Max simultaneous participants.\n🚀 Stay tuned for the next one! | Fique de olho para participar do próximo!*";
    
    $stmt = $conn->prepare("
        INSERT IGNORE INTO settings (setting_key, category, label, type, setting_value)
        VALUES ('weekly_summary_footer', 'WhatsApp', 'Rodapé do Resumo Semanal (Replays)', 'textarea', ?)
    ");
    $stmt->execute([$footer]);
    echo "Setting 'weekly_summary_footer' adicionada com sucesso.\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
