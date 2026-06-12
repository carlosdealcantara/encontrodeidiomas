<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/whatsapp_helper.php';

try {
    $config = getMentoriaConfig();
    $groupJid = $config['groups']['our_meetups']['jid'] ?? null;
    
    if (!$groupJid) {
        die("JID do Our Meetups não encontrado na config.");
    }
    
    $conn = connectDB();
    $stmt = $conn->prepare("UPDATE meetup_schedule SET group_jid = ?");
    $stmt->execute([$groupJid]);
    
    echo "Sucesso! Atualizados " . $stmt->rowCount() . " registros para o JID: $groupJid\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
