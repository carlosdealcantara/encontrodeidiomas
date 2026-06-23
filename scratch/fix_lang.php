<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $conn->exec("UPDATE languages SET odysee_channel_name = '@EncontrodeldiomasIngles' WHERE id = 1");
    echo "Fixed channel name.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
