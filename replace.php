<?php
$files = ['meetup_groups.php', 'meetup_templates.php', 'wpp_broadcast.php', 'conectar_whatsapp.php', 'wpp_resumo_semanal.php'];
foreach ($files as $f) {
    $path = "admin/" . $f;
    $content = file_get_contents($path);
    $content = preg_replace('/<!-- WhatsApp Sub-Nav -->\s*<div[^>]*>.*?<\/div>/s', "<!-- Sub-Nav -->\n        <?php include __DIR__ . '/includes/whatsapp_subnav.php'; ?>", $content);
    file_put_contents($path, $content);
}
echo "Done";
