<?php
require 'config.php';
require 'includes/whatsapp_helper.php';
$conf = getMentoriaConfig();
$j = $conf['groups']['our_meetups']['jid'] ?? '';
if ($j) {
    $c = connectDB();
    $c->query("UPDATE meetup_schedule SET group_jid='$j'");
    echo "Updated to: $j";
} else {
    echo "No JID found in config";
}
