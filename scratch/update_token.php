<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $newToken = 'GGvENFkMmsBB9V7ZWpfty4X8BRWskkcR';
    $conn->exec("UPDATE languages SET odysee_auth_token = '$newToken' WHERE name = 'Inglês'");
    $conn->exec("UPDATE odysee_publish_queue SET status = 'pending' WHERE language_id = (SELECT id FROM languages WHERE name = 'Inglês' LIMIT 1)");
    
    echo "TOKEN ATUALIZADO PARA INGLÊS!\n";
    echo "STATUS RESETADO PARA PENDING!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
