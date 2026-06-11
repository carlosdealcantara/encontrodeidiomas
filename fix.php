<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/whatsapp_helper.php';

$config = getMentoriaConfig();
$group_jid = $config['groups']['our_meetups']['jid'] ?? '';

if ($group_jid) {
    $conn = connectDB();
    $stmt = $conn->prepare("UPDATE meetup_schedule SET group_jid = ?");
    $stmt->execute([$group_jid]);
    echo "Sucesso! JID atualizado para: " . htmlspecialchars($group_jid);
} else {
    echo "Erro: JID do grupo não encontrado nas configurações.";
}
?>
