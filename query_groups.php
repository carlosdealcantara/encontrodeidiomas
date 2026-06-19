<?php
require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$sql = "SELECT group_id, nome FROM tb_grupos WHERE group_id LIKE '%@g.us' AND group_id NOT IN ('13477461732-1553354997@g.us', '120363225749665362@g.us', '120363148227096134@g.us') LIMIT 3";
$res = $conn->query($sql);
$out = [];
while($row = $res->fetch_assoc()) $out[] = $row;
echo json_encode($out);
