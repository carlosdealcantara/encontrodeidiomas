<?php
require '../config.php';
echo 'Timezone: ' . date_default_timezone_get() . "\n";
echo 'Time: ' . date('Y-m-d H:i:s') . "\n";
$stmt = $conn->query("SELECT * FROM meetup_whatsapp_templates");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
