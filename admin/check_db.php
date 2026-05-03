<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$links = $conn->query("SELECT id, title, order_index, layout_type FROM useful_links ORDER BY order_index DESC, title ASC")->fetchAll();

echo "--- DUMP DE PRIORIDADES (ORDEM DESC) ---\n";
foreach ($links as $l) {
    echo "ID: {$l['id']} | Prioridade: {$l['order_index']} | Layout: {$l['layout_type']} | Título: {$l['title']}\n";
}
echo "---------------------------------------\n";
