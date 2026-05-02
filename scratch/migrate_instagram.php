<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<pre>";
try {
    // 1. Add instagram_link column to meetings table
    $conn->exec("ALTER TABLE meetings ADD COLUMN instagram_link VARCHAR(255) AFTER whatsapp_group_link");
    echo "Column instagram_link added to meetings table successfully.\n";
} catch (PDOException $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

try {
    // 2. Migrate data from old events table based on language matching if possible
    // The events table has language_id, so we can join on that.
    $stmt = $conn->query("SELECT language_id, instagram_link FROM events WHERE instagram_link IS NOT NULL AND instagram_link != ''");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateStmt = $conn->prepare("UPDATE meetings SET instagram_link = :ig WHERE language_id = :lang_id AND (instagram_link IS NULL OR instagram_link = '')");
    
    $count = 0;
    foreach ($events as $event) {
        $updateStmt->execute([':ig' => $event['instagram_link'], ':lang_id' => $event['language_id']]);
        $count += $updateStmt->rowCount();
    }
    echo "Migrated $count instagram_link records from events to meetings.\n";
    
} catch (PDOException $e) {
    echo "Error migrating data: " . $e->getMessage() . "\n";
}

echo "</pre>";
