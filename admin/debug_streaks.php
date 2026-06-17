<?php
require_once '../config.php';
$conn = connectDB();

if (isset($_GET['update_julyana'])) {
    // Achar a Julyana (nome = 'Julyana' ou parecido) e setar streak para 4
    $stmt = $conn->prepare("UPDATE mentoria_desafio_streaks SET current_streak = 4 WHERE member_name LIKE '%Julyana%' OR member_name LIKE '%July%'");
    $stmt->execute();
    echo "Update executed: " . $stmt->rowCount() . " rows affected.\n\n";
}

$stmt = $conn->query("SELECT * FROM mentoria_desafio_streaks ORDER BY id DESC");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($res);
echo "</pre>";
