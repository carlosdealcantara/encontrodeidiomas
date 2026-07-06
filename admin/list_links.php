<?php
require_once '../config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT titulo_final, odysee_url FROM mentoria_odysee_queue WHERE status='done' ORDER BY drive_file_name ASC");
while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['titulo_final'] . " - " . $r['odysee_url'] . "<br>\n";
}
?>
